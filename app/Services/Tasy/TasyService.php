<?php

namespace App\Services\Tasy;

use App\Models\EMR\Core\Patient;
use App\Services\UsesRepositories;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de acesso ao EMR Tasy via conexão Oracle.
 *
 * Responsabilidades:
 * - Buscar e montar o payload completo de um paciente individual (modal SBAR).
 * - Gerenciar cache de dados de paciente (detalhes e prescrições).
 * - Pré-aquecer cache de lotes para abertura instantânea do modal.
 *
 * Diferença em relação ao PatientDataLoader:
 * - PatientDataLoader opera por setor (lista de leitos).
 * - TasyService opera por paciente individual (nr_atendimento), chamado pelo modal de detalhes.
 */
class TasyService
{
    use UsesRepositories;

    // ==================== CONSTANTES ====================
    private const CACHE_TTL_PATIENT = 600;  // 10 minutos

    /**
     * Versão da chave de cache dos detalhes do paciente.
     * Incremente ao alterar a estrutura do array retornado por getSbarPatientDetails()
     * para forçar invalidação automática do cache existente em produção sem precisar de
     * `cache:clear` manual.
     */
    private const SBAR_CACHE_VERSION = 4;

    /** Versão da chave de cache de prescrições — incremente ao mudar estrutura de getPatientPrescriptions(). */
    private const PRESCRIPTIONS_CACHE_VERSION = 5;

    private TasyFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new TasyFormatter;
    }

    // ==================== MÉTODOS PÚBLICOS PRINCIPAIS ====================

    /**
     * Monta o payload completo de um paciente para o modal SBAR:
     * dados demográficos, dados clínicos (isolamento, dispositivos, alergias) e escalas (MEWS/PEWS, Braden, Morse, Dor, TEV).
     */
    public function getSbarPatientDetails(int $attendanceNumber): ?object
    {
        if (! $attendanceNumber) {
            return null;
        }

        $cacheKey = $this->sbarPatientDetailsCacheKey($attendanceNumber);

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function () use ($attendanceNumber) {
            $patient = $this->loadPatientWithRelations($attendanceNumber);
            if (! $patient) {
                return null;
            }

            $basicData = $this->buildBasicDataFromEloquent($patient);
            $batchClinicalData = $this->fetchBatchClinicalData([$attendanceNumber]);
            $clinicalData = $this->extractClinicalDataForPatient($attendanceNumber, $batchClinicalData, $patient->person_id);
            $scalesData = $this->fetchScalesData($attendanceNumber, $patient->isPediatric());

            return $this->assemblePatientData($basicData, $clinicalData, $scalesData);
        });
    }

    /**
     * Returns the deduplicated CID history for a single patient (latest record per CID code).
     */
    public function getPatientCidHistory(int $attendanceNumber): array
    {
        if (! $attendanceNumber) {
            return [];
        }

        return Cache::remember(
            "patient_cid_history_{$attendanceNumber}",
            self::CACHE_TTL_PATIENT,
            fn () => $this->clinical()->getCidHistory($attendanceNumber)
        );
    }

    /**
     * Fetches all prescriptions for a patient (medications, procedures, nutrition, etc.).
     *
     * Cache key: patient_prescriptions_v{version}_{nr} — 10 min TTL
     */
    public function getPatientPrescriptions(int $attendanceNumber): array
    {
        if (! $attendanceNumber) {
            return [];
        }

        $cacheKey = $this->prescriptionsCacheKey($attendanceNumber);

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function () use ($attendanceNumber) {
            return $this->prescriptions()->getPrescriptions($attendanceNumber);
        });
    }

    /**
     * Returns the 24-hour administration schedule for a patient's medications on a given date.
     * Short TTL (3 min) since nurses check doses in real time.
     *
     * Cache key: patient_med_schedule_{nr}_{date}
     */
    public function getMedicationSchedule(int $attendanceNumber, string $date): array
    {
        if (! $attendanceNumber || ! $date) {
            return [];
        }

        $cacheKey = "patient_med_schedule_{$attendanceNumber}_{$date}";

        return Cache::remember($cacheKey, 180, function () use ($attendanceNumber, $date) {
            return $this->prescriptions()->getDailyMedicationSchedule($attendanceNumber, $date);
        });
    }

    /**
     * Pre-warms the prescriptions cache for a batch of patients.
     * Called by the SBAR page after the sector loads so modal opens are instant.
     * Returns the number of patients whose cache was populated (skips already cached).
     */
    public function batchWarmPatientPrescriptions(array $attendanceNumbers): int
    {
        $warmed = 0;

        foreach ($attendanceNumbers as $nr) {
            $nr = (int) $nr;
            if (! $nr) {
                continue;
            }

            $cacheKey = $this->prescriptionsCacheKey($nr);
            if (Cache::has($cacheKey)) {
                continue;
            }

            try {
                Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function () use ($nr) {
                    return $this->prescriptions()->getPrescriptions($nr);
                });
                $warmed++;
            } catch (\Throwable $e) {
                Log::warning('TasyService: Failed to warm patient prescriptions', [
                    'attendance' => $nr,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $warmed;
    }

    /**
     * Pre-warms the patient details cache for a batch of patients.
     * Skips patients already cached. Returns count warmed.
     */
    public function batchWarmPatientDetails(array $attendanceNumbers): int
    {
        $warmed = 0;

        foreach ($attendanceNumbers as $nr) {
            $nr = (int) $nr;
            if (! $nr) {
                continue;
            }

            $cacheKey = $this->sbarPatientDetailsCacheKey($nr);
            if (Cache::has($cacheKey)) {
                continue;
            }

            try {
                $this->getSbarPatientDetails($nr);
                $warmed++;
            } catch (\Throwable $e) {
                Log::warning('TasyService: Failed to warm patient details', [
                    'attendance' => $nr,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $warmed;
    }

    // ==================== CACHE MANAGEMENT ====================

    public function clearPatientCache(int $attendanceNumber): void
    {
        $keys = [
            $this->sbarPatientDetailsCacheKey($attendanceNumber),
            $this->prescriptionsCacheKey($attendanceNumber),
            "patient_alerts_{$attendanceNumber}",
            "patient_cid_history_{$attendanceNumber}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public function clearAllPatientsCache(): void
    {
        Cache::flush();
    }

    public function getPatientAlerts(int $attendanceNumber, int $personId): array
    {
        try {
            return Cache::remember(
                "patient_alerts_{$attendanceNumber}",
                300, // 5 min — alertas podem mudar com mais frequência
                fn () => $this->alerts()->getPatientActiveAlerts($attendanceNumber, $personId)
            );
        } catch (\Throwable $e) {
            Log::warning('TasyService: Failed to fetch alerts', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function sbarPatientDetailsCacheKey(int $attendanceNumber): string
    {
        return 'sbar_patient_details_v'.self::SBAR_CACHE_VERSION."_{$attendanceNumber}";
    }

    private function prescriptionsCacheKey(int $attendanceNumber): string
    {
        return 'patient_prescriptions_v'.self::PRESCRIPTIONS_CACHE_VERSION."_{$attendanceNumber}";
    }

    // ==================== MÉTODOS PRIVADOS - DATA FETCHING ====================

    private function loadPatientWithRelations(int $attendanceNumber): ?Patient
    {
        return Patient::with([
            'person',
            'bed.sector.hospital',
        ])->find($attendanceNumber);
    }

    private function fetchBatchClinicalData(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [
                'surgery' => [],
                'surgery_detailed' => [],
                'multidisciplinary' => [],
                'multidisciplinary_requests' => [],
                'clinical_details' => [],
            ];
        }

        $clinicalDetails = $this->clinical()->getBatchClinicalDetails($attendanceNumbers);
        $surgeries = $this->surgery()->getFutureSurgeriesForAttendances($attendanceNumbers);
        $multidisciplinary = $this->multidisciplinary()->getMultidisciplinaryTeams($attendanceNumbers);
        $multidisciplinaryReqs = $this->multidisciplinary()->getMultidisciplinaryRequestsBatch($attendanceNumbers);

        return [
            'surgery' => $surgeries['surgery'] ?? [],
            'surgery_detailed' => $surgeries['surgery_detailed'] ?? [],
            'multidisciplinary' => $multidisciplinary,
            'multidisciplinary_requests' => $multidisciplinaryReqs,
            'clinical_details' => $clinicalDetails,
        ];
    }

    private function extractClinicalDataForPatient(int $attendanceNumber, array $batchData, ?int $personId): object
    {
        $details = $batchData['clinical_details'][$attendanceNumber] ?? null;

        if (! $details) {
            return (object) [
                'diagnosticos_comorbidades' => null,
                'medida_bloqueio' => 'Não',
                'motivos_isolamento' => null,
                'avaliacao_enf' => null,
                'plano_educ' => null,
                'pe_data' => null,
                'ds_queda' => 'Não',
                'diag' => null,
                'dispositivos' => null,
                'alergias_detalhadas' => null,
                'materiais' => null,
                'ultima_hemocultura' => null,
                'hemocultura_pendente' => false,
                'procedimentos_cirurgicos' => [],
                'alerts' => [],
                'multidisciplinary' => $this->formatter->getDefaultMultidisciplinary(),
                'has_allergy' => false,
                'has_isolation' => false,
            ];
        }

        $alerts = [];
        if ($personId) {
            try {
                $alerts = $this->alerts()->getPatientActiveAlerts($attendanceNumber, $personId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch alerts', [
                    'attendance' => $attendanceNumber,
                    'person_id' => $personId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $multidisciplinaryEval = null;
        $multidisciplinaryRequests = [];
        try {
            $multidisciplinaryEval = $this->multidisciplinary()->getMultidisciplinaryTeamEvaluations($attendanceNumber);
            $multidisciplinaryRequests = $this->multidisciplinary()->getDetailedMultidisciplinaryRequests($attendanceNumber);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch multidisciplinary evaluations', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);
        }

        $hasAllergy = $this->formatter->checkHasAllergy($details->alergias_detalhadas ?? null);
        $hasIsolation = $this->formatter->checkHasIsolation($details->medida_bloqueio ?? null);

        $cidHistory = [];
        try {
            $cidHistory = $this->clinical()->getCidHistory($attendanceNumber);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch CID history', ['attendance' => $attendanceNumber, 'error' => $e->getMessage()]);
        }

        return (object) [
            'diagnosticos_comorbidades' => $details->diagnosticos_comorbidades ?? null,
            'medida_bloqueio' => $details->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $details->motivos_isolamento ?? null,
            'avaliacao_enf' => $details->avaliacao_enf ?? null,
            'plano_educ' => $details->plano_educ ?? null,
            'pe_data' => $details->pe_data ?? null,
            'ds_queda' => $details->ds_queda ?? 'Não',
            'diag' => $details->diag ?? null,
            'dispositivos' => $details->dispositivos ?? null,
            'alergias_detalhadas' => $details->alergias_detalhadas ?? null,
            'materiais' => $details->materiais ?? null,
            'ultima_hemocultura' => $details->ultima_hemocultura ?? null,
            'hemocultura_pendente' => (int) ($details->hemocultura_pendente ?? 0) === 1,
            'procedimentos_cirurgicos' => $batchData['surgery_detailed'][$attendanceNumber] ?? [],
            'alerts' => $alerts,
            'multidisciplinary' => $multidisciplinaryEval ?? $this->formatter->getDefaultMultidisciplinary(),
            'multidisciplinary_requests' => $multidisciplinaryRequests,
            'has_allergy' => $hasAllergy,
            'has_isolation' => $hasIsolation,
            'cid_history' => $cidHistory,
        ];
    }

    private function fetchScalesData(int $attendanceNumber, bool $isPediatric = false): ?array
    {
        if (! $attendanceNumber) {
            return null;
        }

        $map = $this->scales()->getPatientsScalesUnified([$attendanceNumber], []);
        $unified = $map[$attendanceNumber] ?? null;

        if ($unified === null) {
            return null;
        }

        // Sub-scores detalhados (PA, FC, FR, Temp, componentes Braden/Morse/PEWS/TEV).
        // Chamado apenas no modal individual — não afeta a carga em batch do setor.
        try {
            $details = $this->scales()->getDetailedScalesForPatient($attendanceNumber);
            foreach ($details as $scaleKey => $scaleDetails) {
                $unified[$scaleKey]['details'] = $scaleDetails;
            }
        } catch (\Throwable $e) {
            Log::warning('TasyService: falha ao buscar sub-scores detalhados', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return $unified;
    }

    // ==================== MÉTODOS PRIVADOS - DATA ASSEMBLY ====================

    /**
     * Monta os dados demográficos e identificação do paciente a partir do modelo Eloquent.
     *
     * Convênio e médico responsável são buscados via stored functions Oracle em queries separadas,
     * pois essas funções executam lógica de negócio complexa (seleção de categoria de convênio,
     * resolução de médico com hierarquia de especialidade) que não é replicável com SQL direto
     * de forma confiável. Cada chamada é isolada em try/catch para não bloquear o restante
     * do payload caso o Oracle esteja temporariamente lento nessas funções.
     */
    private function buildBasicDataFromEloquent(Patient $patient): object
    {
        $birthDate = $patient->person?->dt_nascimento;
        $age = 99;
        $ageMonths = 0;
        $ageDays = 0;

        if ($birthDate) {
            $birth = Carbon::parse($birthDate);
            $now = now();
            $age = $birth->age;
            $ageMonths = (int) $birth->copy()->addYears($age)->diffInMonth($now);
            $ageDays = (int) $birth->copy()->addYears($age)->addMonths($ageMonths)->diffInDay($now);
        }

        $internmentDays = null;
        if ($patient->dt_entrada) {
            $internmentDays = (int) Carbon::parse($patient->dt_entrada)->diffInDay(now());
        }

        $convenio = 'Convênio não informado';
        $medicoResponsavel = 'Não informado';
        try {
            $row = DB::connection('tasy')->selectOne("
                SELECT TASY.obter_desc_convenio(TASY.obter_convenio_atendimento(?)) AS convenio,
                       TASY.obter_medico_resp_atend(?, 'N') AS medico
                FROM DUAL
            ", [$patient->nr_atendimento, $patient->nr_atendimento]);

            if ($row) {
                if (! empty($row->convenio)) {
                    $convenio = trim($row->convenio);
                }
                if (! empty($row->medico)) {
                    $medicoResponsavel = trim($row->medico);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch convenio/medico', [
                'attendance' => $patient->nr_atendimento,
                'error' => $e->getMessage(),
            ]);
        }

        return (object) [
            'nr_atendimento' => $patient->nr_atendimento,
            'cd_pessoa_fisica' => $patient->cd_pessoa_fisica,
            'nm_pessoa_fisica' => $patient->person?->nm_pessoa_fisica ?? 'Nome não informado',
            'nm_social' => $patient->person?->social_name ?? null,
            'nr_prontuario' => $patient->person?->nr_prontuario ?? 'N/A',
            'birth_date' => $birthDate ? Carbon::parse($birthDate)->format('d/m/Y') : null,
            'age' => $age,
            'age_months' => $ageMonths,
            'age_days' => $ageDays,
            'age_detailed' => $this->formatter->formatDetailedAgeFromParts($age, $ageMonths, $ageDays),
            'sexo' => $patient->person?->ie_sexo ?? 'N/A',
            'convenio' => $convenio,
            'medico_responsavel' => $medicoResponsavel,
            'dt_entrada' => $patient->dt_entrada,
            'internment_days' => $internmentDays,
            'is_new_patient' => $internmentDays === null || $internmentDays < 1,
            'hospital_name' => $patient->bed?->sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado',
            'sector_name' => $patient->bed?->sector?->ds_prescricao
                ?? $patient->bed?->sector?->ds_setor_atendimento
                ?? 'Setor não identificado',
            'cd_setor_atendimento' => $patient->bed?->sector?->cd_setor_atendimento ?? null,
            'bed_name' => $patient->bed?->cd_unidade_basica ?? 'N/A',
            'is_pediatric' => $patient->isPediatric(),
            'has_patient' => true,
            'is_occupied' => true,
        ];
    }

    private function assemblePatientData(object $basicData, object $clinicalData, ?array $scalesData): object
    {
        $data = array_merge(
            (array) $basicData,
            [
                'has_patient' => true,
                'is_occupied' => true,
                'diagnosticos_comorbidades' => $clinicalData->diagnosticos_comorbidades,
                'medida_bloqueio' => $clinicalData->medida_bloqueio,
                'motivos_isolamento' => $clinicalData->motivos_isolamento,
                'avaliacao_enf' => $clinicalData->avaliacao_enf,
                'plano_educ' => $clinicalData->plano_educ,
                'pe_data' => $clinicalData->pe_data,
                'ds_queda' => $clinicalData->ds_queda,
                'diag' => $clinicalData->diag,
                'dispositivos' => $clinicalData->dispositivos,
                'alergias_detalhadas' => $clinicalData->alergias_detalhadas,
                'materiais' => $clinicalData->materiais,
                'ultima_hemocultura' => $clinicalData->ultima_hemocultura,
                'hemocultura_pendente' => $clinicalData->hemocultura_pendente,
                'procedimentos_cirurgicos' => $clinicalData->procedimentos_cirurgicos,
                'alerts' => $clinicalData->alerts,
                'multidisciplinary' => $clinicalData->multidisciplinary,
                'cid_history' => $clinicalData->cid_history ?? [],
            ]
        );

        $data = $this->formatter->addScalesToData($data, $scalesData, $basicData->age ?? 99);

        return (object) $data;
    }
}
