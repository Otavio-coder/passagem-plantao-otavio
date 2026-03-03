<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\EMR\Core\{Patient, Person, Bed, Sector, Doctor, Hospital};
use App\Services\Tasy\SbarFormatter;

class TasyService
{
    use UsesRepositories;

    // ==================== CONSTANTES ====================
    private const CACHE_TTL_SECTOR  = 900;  // 15 minutos
    private const CACHE_TTL_PATIENT = 600;  // 10 minutos

    private SbarFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new SbarFormatter();
    }

    // ==================== MÉTODOS PÚBLICOS PRINCIPAIS ====================

    /**
     * Busca pacientes de um setor para SBAR Report
     */
    public function getSectorPatientsForSbar(int $sectorId): array
    {
        $cacheKey = "sector_patients_sbar_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECTOR, function () use ($sectorId) {
            $sectorContext    = $this->getSectorContext($sectorId);
            $rawBeds          = $this->fetchSectorBedsRaw($sectorId);
            $attendanceNumbers = $this->extractAttendanceNumbers($rawBeds);

            $batchData = $this->fetchBatchData($sectorId, $attendanceNumbers);

            return $rawBeds->map(function ($bed) use ($sectorContext, $batchData) {
                return $this->formatter->formatPatientForSbar($bed, $sectorContext, $batchData);
            })->values()->toArray();
        });
    }

    /**
     * Busca dados básicos do paciente para Patient Modal
     */
    public function getPatientBasicData(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) return null;

        $cacheKey = "patient_basic_modal_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function () use ($attendanceNumber) {
            $patient = $this->loadPatientWithRelations($attendanceNumber);
            if (!$patient) return null;

            $basicData        = $this->buildBasicDataFromEloquent($patient);
            $batchClinicalData = $this->fetchBatchClinicalData([$attendanceNumber]);
            $clinicalData     = $this->extractClinicalDataForPatient($attendanceNumber, $batchClinicalData, $patient->person_id);
            $scalesData       = $this->fetchScalesData($attendanceNumber, $patient->isPediatric());

            return $this->assemblePatientData($basicData, $clinicalData, $scalesData);
        });
    }

    /**
     * Busca dados de recomendações/prescrições do paciente
     */
    public function getPatientRecomendacoesData(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) return null;

        $cacheKey = "patient_recomendacoes_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function () use ($attendanceNumber) {
            return $this->fetchRecomendacoesData($attendanceNumber);
        });
    }

    /**
     * Busca dados completos do paciente (básico + recomendações)
     */
    public function getFullPatientData(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) return null;

        $cacheKey = "patient_full_data_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function () use ($attendanceNumber) {
            $basicData = $this->getPatientBasicData($attendanceNumber);
            if (!$basicData) return null;

            $recomendacoesData = $this->fetchRecomendacoesData($attendanceNumber);
            return $this->mergeRecomendacoesWithBasicData($basicData, $recomendacoesData);
        });
    }

    // ==================== CACHE MANAGEMENT ====================

    public function clearSectorCache(int $sectorId): void
    {
        Cache::forget("sector_patients_sbar_{$sectorId}");
        Cache::forget("pending_events_sector_{$sectorId}");
        Cache::forget("sector_pending_fast_{$sectorId}");
    }

    public function clearPatientCache(int $attendanceNumber): void
    {
        $keys = [
            "patient_full_data_{$attendanceNumber}",
            "patient_basic_modal_{$attendanceNumber}",
            "patient_recomendacoes_{$attendanceNumber}",
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
            return $this->alerts()->getPatientActiveAlerts($attendanceNumber, $personId);
        } catch (\Throwable $e) {
            Log::warning('TasyService: Failed to fetch alerts', [
                'attendance' => $attendanceNumber,
                'error'      => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ==================== MÉTODOS PRIVADOS - DATA FETCHING ====================

    private function loadPatientWithRelations(int $attendanceNumber): ?Patient
    {
        return Patient::with([
            'person',
            'bed.sector.hospital',
            'doctor',
            'attendingDoctor',
        ])->find($attendanceNumber);
    }

    private function fetchSectorBedsRaw(int $sectorId)
    {
        $sector = Sector::find($sectorId);
        if (!$sector) return collect([]);

        $results = DB::connection('tasy')->select("
            SELECT
                ua.cd_unidade_basica,
                ua.nr_seq_interno as bed_sequence,
                ua.ie_situacao as bed_status,
                ua.cd_setor_atendimento,
                sa.ds_setor_atendimento,
                sa.nr_seq_agrupamento as hospital_id,
                CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as is_occupied,
                CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as has_patient,
                atp.nr_atendimento,
                atp.cd_pessoa_fisica,
                TASY.obter_nome_paciente(atp.nr_atendimento) AS nm_pessoa_fisica,
                pf.nr_prontuario,
                pf.dt_nascimento AS birth_date,
                FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS age,
                MOD(FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)), 12) AS age_months,
                TRUNC(SYSDATE - ADD_MONTHS(pf.dt_nascimento, FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)))) AS age_days,
                pf.ie_sexo AS sexo,
                TASY.obter_desc_convenio(TASY.obter_convenio_atendimento(atp.nr_atendimento)) AS convenio,
                TASY.obter_medico_resp_atend(atp.nr_atendimento, 'N') AS medico_responsavel,
                atp.dt_entrada,
                TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS internment_days,
                atp.dt_alta_medico,
                ma.ds_motivo_alta,
                apa.dt_previsto_alta AS apa_dt_previsto_alta,
                apa.dt_registro AS apa_dt_registro
            FROM tasy.unidade_atendimento ua
            JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
            LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento AND atp.dt_alta IS NULL
            LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
            LEFT JOIN tasy.motivo_alta ma ON atp.cd_motivo_alta_medica = ma.cd_motivo_alta
            LEFT JOIN (
                SELECT nr_atendimento, dt_previsto_alta, dt_registro,
                       ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) AS rn
                FROM tasy.atend_previsao_alta
                WHERE dt_registro >= SYSDATE - 10
            ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
            WHERE ua.cd_setor_atendimento = :sector_id
            AND ua.ie_situacao = 'A'
            ORDER BY
                CASE WHEN ua.nr_seq_interno IS NOT NULL THEN ua.nr_seq_interno ELSE 999999 END ASC,
                ua.cd_unidade_basica ASC
        ", ['sector_id' => $sectorId]);

        return collect($results);
    }

    private function fetchBatchData(int $sectorId, array $attendanceNumbers): array
    {
        $clinicalBatch = $this->fetchBatchClinicalData($attendanceNumbers);
        $scales        = $this->fetchBatchScales($attendanceNumbers);

        $pendingEventsService = new \App\Services\PatientPendingEventsService();
        $pendingRaw = $pendingEventsService->getPendingEventsForSector($sectorId);

        $pendingEventsMap = [];
        $dischargeInfoMap = [];
        foreach ($pendingRaw as $nr => $data) {
            $pendingEventsMap[$nr] = $data['events'] ?? [];
            $dischargeInfoMap[$nr] = !empty($data['discharge']) ? $data['discharge'] : null;
        }

        return [
            'clinical_details'           => $clinicalBatch['clinical_details'] ?? [],
            'surgery'                    => $clinicalBatch['surgery'] ?? [],
            'surgery_detailed'           => $clinicalBatch['surgery_detailed'] ?? [],
            'multidisciplinary'          => $clinicalBatch['multidisciplinary'] ?? [],
            'multidisciplinary_requests' => $clinicalBatch['multidisciplinary_requests'] ?? [],
            'priority_exams'             => $clinicalBatch['priority_exams'] ?? [],
            'scales'                     => $scales,
            'pending_events'             => $pendingEventsMap,
            'discharge_info'             => $dischargeInfoMap,
        ];
    }

    private function fetchBatchClinicalData(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [
                'surgery'                    => [],
                'surgery_detailed'           => [],
                'multidisciplinary'          => [],
                'multidisciplinary_requests' => [],
                'priority_exams'             => [],
                'clinical_details'           => [],
            ];
        }

        $clinicalDetails        = $this->clinical()->getBatchClinicalDetails($attendanceNumbers);
        $surgeries              = $this->surgery()->getFutureSurgeriesForAttendances($attendanceNumbers);
        $multidisciplinary      = $this->multidisciplinary()->getMultidisciplinaryTeams($attendanceNumbers);
        $multidisciplinaryReqs  = $this->multidisciplinary()->getMultidisciplinaryRequestsBatch($attendanceNumbers);
        $priorityExams          = $this->exams()->getPriorityExamsForAttendances($attendanceNumbers);

        return [
            'surgery'                    => $surgeries['surgery'] ?? [],
            'surgery_detailed'           => $surgeries['surgery_detailed'] ?? [],
            'multidisciplinary'          => $multidisciplinary,
            'multidisciplinary_requests' => $multidisciplinaryReqs,
            'priority_exams'             => $priorityExams,
            'clinical_details'           => $clinicalDetails,
        ];
    }

    private function fetchBatchScales(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) return [];
        return $this->scales()->getPatientsScalesUnified($attendanceNumbers, []);
    }

    private function extractClinicalDataForPatient(int $attendanceNumber, array $batchData, ?int $personId): object
    {
        $details = $batchData['clinical_details'][$attendanceNumber] ?? null;

        if (!$details) {
            return (object)[
                'diagnosticos_comorbidades' => null,
                'medida_bloqueio'           => 'Não',
                'motivos_isolamento'        => null,
                'avaliacao_enf'             => null,
                'plano_educ'               => null,
                'pe_data'                  => null,
                'ds_queda'                 => 'Não',
                'diag'                     => null,
                'dispositivos'             => null,
                'alergias_detalhadas'      => null,
                'materiais'                => null,
                'prioridade_exames'        => null,
                'procedimentos_cirurgicos' => [],
                'alerts'                   => [],
                'multidisciplinary'        => $this->formatter->getDefaultMultidisciplinary(),
                'has_allergy'              => false,
                'has_isolation'            => false,
            ];
        }

        $alerts = [];
        if ($personId) {
            try {
                $alerts = $this->alerts()->getPatientActiveAlerts($attendanceNumber, $personId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch alerts', [
                    'attendance' => $attendanceNumber,
                    'person_id'  => $personId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $multidisciplinaryEval     = null;
        $multidisciplinaryRequests = [];
        try {
            $multidisciplinaryEval     = $this->multidisciplinary()->getMultidisciplinaryTeamEvaluations($attendanceNumber);
            $multidisciplinaryRequests = $this->multidisciplinary()->getDetailedMultidisciplinaryRequests($attendanceNumber);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch multidisciplinary evaluations', [
                'attendance' => $attendanceNumber,
                'error'      => $e->getMessage(),
            ]);
        }

        $hasAllergy   = $this->formatter->checkHasAllergy($details->alergias_detalhadas ?? null);
        $hasIsolation = $this->formatter->checkHasIsolation($details->medida_bloqueio ?? null);

        return (object)[
            'diagnosticos_comorbidades' => $details->diagnosticos_comorbidades ?? null,
            'medida_bloqueio'           => $details->medida_bloqueio ?? 'Não',
            'motivos_isolamento'        => $details->motivos_isolamento ?? null,
            'avaliacao_enf'             => $details->avaliacao_enf ?? null,
            'plano_educ'               => $details->plano_educ ?? null,
            'pe_data'                  => $details->pe_data ?? null,
            'ds_queda'                 => $details->ds_queda ?? 'Não',
            'diag'                     => $details->diag ?? null,
            'dispositivos'             => $details->dispositivos ?? null,
            'alergias_detalhadas'      => $details->alergias_detalhadas ?? null,
            'materiais'                => $details->materiais ?? null,
            'prioridade_exames'        => $batchData['priority_exams'][$attendanceNumber] ?? null,
            'procedimentos_cirurgicos' => $batchData['surgery_detailed'][$attendanceNumber] ?? [],
            'alerts'                   => $alerts,
            'multidisciplinary'        => $multidisciplinaryEval ?? $this->formatter->getDefaultMultidisciplinary(),
            'multidisciplinary_requests' => $multidisciplinaryRequests,
            'has_allergy'              => $hasAllergy,
            'has_isolation'            => $hasIsolation,
        ];
    }

    private function fetchScalesData(int $attendanceNumber, bool $isPediatric = false): ?array
    {
        if (!$attendanceNumber) return null;
        $map = $this->scales()->getPatientsScalesUnified([$attendanceNumber], []);
        return $map[$attendanceNumber] ?? null;
    }

    private function fetchRecomendacoesData(int $attendanceNumber): object
    {
        return (object)[
            'procedimentos'  => $this->prescricoes()->getProcedimentos($attendanceNumber),
            'medicamentos'   => $this->prescricoes()->getMedicamentos($attendanceNumber),
            'nutricao'       => $this->prescricoes()->getNutricao($attendanceNumber),
            'recomendacoes'  => $this->prescricoes()->getRecomendacoes($attendanceNumber),
            'intervencoes'   => $this->prescricoes()->getIntervencoes($attendanceNumber),
        ];
    }

    // ==================== MÉTODOS PRIVADOS - DATA ASSEMBLY ====================

    private function buildBasicDataFromEloquent(Patient $patient): object
    {
        $birthDate = $patient->person?->dt_nascimento;
        $age       = 99;
        $ageMonths = 0;
        $ageDays   = 0;

        if ($birthDate) {
            $birth     = Carbon::parse($birthDate);
            $now       = now();
            $age       = $birth->age;
            $ageMonths = $birth->copy()->addYears($age)->diffInMonths($now);
            $ageDays   = $birth->copy()->addYears($age)->addMonths($ageMonths)->diffInDays($now);
        }

        $internmentDays = null;
        if ($patient->dt_entrada) {
            $internmentDays = floor(Carbon::parse($patient->dt_entrada)->floatDiffInDays(now()));
        }

        $convenio = 'Convênio não informado';
        try {
            $convenioResult = DB::connection('tasy')->selectOne("
                SELECT TASY.obter_desc_convenio(TASY.obter_convenio_atendimento(?)) as convenio
                FROM DUAL
            ", [$patient->nr_atendimento]);

            if ($convenioResult && !empty($convenioResult->convenio)) {
                $convenio = trim($convenioResult->convenio);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch convenio', [
                'attendance' => $patient->nr_atendimento,
                'error'      => $e->getMessage(),
            ]);
        }

        $medicoResponsavel = 'Não informado';
        if ($patient->doctor && !empty($patient->doctor->nm_pessoa_fisica)) {
            $medicoResponsavel = trim($patient->doctor->nm_pessoa_fisica);
        } else {
            try {
                $medicoResult = DB::connection('tasy')->selectOne("
                    SELECT TASY.obter_medico_resp_atend(?, 'N') as medico
                    FROM DUAL
                ", [$patient->nr_atendimento]);

                if ($medicoResult && !empty($medicoResult->medico)) {
                    $medicoResponsavel = trim($medicoResult->medico);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch medico', [
                    'attendance' => $patient->nr_atendimento,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return (object)[
            'nr_atendimento'         => $patient->nr_atendimento,
            'cd_pessoa_fisica'       => $patient->cd_pessoa_fisica,
            'nm_pessoa_fisica'       => $patient->person?->nm_pessoa_fisica ?? 'Nome não informado',
            'nr_prontuario'          => $patient->person?->nr_prontuario ?? 'N/A',
            'birth_date'             => $birthDate ? Carbon::parse($birthDate)->format('d/m/Y') : null,
            'age'                    => $age,
            'age_months'             => $ageMonths,
            'age_days'               => $ageDays,
            'age_detailed'           => $this->formatter->formatDetailedAgeFromParts($age, $ageMonths, $ageDays),
            'sexo'                   => $patient->person?->ie_sexo ?? 'N/A',
            'convenio'               => $convenio,
            'medico_responsavel'     => $medicoResponsavel,
            'dt_entrada'             => $patient->dt_entrada,
            'internment_days'        => $internmentDays,
            'is_new_patient'         => $internmentDays === null || $internmentDays < 1,
            'hospital_name'          => $patient->bed?->sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado',
            'sector_name'            => $patient->bed?->sector?->ds_setor_atendimento ?? 'Setor não identificado',
            'cd_setor_atendimento'   => $patient->bed?->sector?->cd_setor_atendimento ?? null,
            'bed_name'               => $patient->bed?->cd_unidade_basica ?? 'N/A',
            'is_pediatric'           => $patient->isPediatric(),
            'has_patient'            => true,
            'is_occupied'            => true,
        ];
    }

    private function assemblePatientData(object $basicData, object $clinicalData, ?array $scalesData): object
    {
        $data = array_merge(
            (array)$basicData,
            [
                'has_patient'              => true,
                'is_occupied'              => true,
                'diagnosticos_comorbidades'=> $clinicalData->diagnosticos_comorbidades,
                'medida_bloqueio'          => $clinicalData->medida_bloqueio,
                'motivos_isolamento'       => $clinicalData->motivos_isolamento,
                'avaliacao_enf'            => $clinicalData->avaliacao_enf,
                'plano_educ'              => $clinicalData->plano_educ,
                'pe_data'                 => $clinicalData->pe_data,
                'ds_queda'                => $clinicalData->ds_queda,
                'diag'                    => $clinicalData->diag,
                'dispositivos'            => $clinicalData->dispositivos,
                'alergias_detalhadas'     => $clinicalData->alergias_detalhadas,
                'materiais'               => $clinicalData->materiais,
                'prioridade_exames'       => $clinicalData->prioridade_exames,
                'procedimentos_cirurgicos'=> $clinicalData->procedimentos_cirurgicos,
                'alerts'                  => $clinicalData->alerts,
                'multidisciplinary'       => $clinicalData->multidisciplinary,
            ]
        );

        $data = $this->formatter->addScalesToData($data, $scalesData, $basicData->age ?? 99);

        return (object)$data;
    }

    private function mergeRecomendacoesWithBasicData(object $basicData, object $recomendacoesData): object
    {
        $combined = (array)$basicData;
        $combined['procedimentos'] = $recomendacoesData->procedimentos;
        $combined['medicamentos']  = $recomendacoesData->medicamentos;
        $combined['nutricao']      = $recomendacoesData->nutricao;
        $combined['recomendacoes'] = $recomendacoesData->recomendacoes;
        $combined['intervencoes']  = $recomendacoesData->intervencoes;

        return (object)$combined;
    }

    // ==================== MÉTODOS PRIVADOS - HELPERS ====================

    private function extractAttendanceNumbers($beds): array
    {
        return $beds->pluck('nr_atendimento')->filter()->values()->toArray();
    }

    private function getSectorContext(int $sectorId): array
    {
        $sector = Sector::with('hospital')->find($sectorId);

        return [
            'sector_id'    => $sectorId,
            'sector_name'  => $sector?->ds_setor_atendimento ?? '',
            'hospital_id'  => $sector?->hospital?->nr_sequencia ?? null,
            'hospital_name'=> $sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado',
        ];
    }
}
