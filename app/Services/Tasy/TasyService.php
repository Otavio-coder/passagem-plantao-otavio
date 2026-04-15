<?php

namespace App\Services\Tasy;

use App\Models\EMR\Core\Patient;
use App\Models\EMR\Core\Sector;
use App\Services\PendingEvents\PatientPendingEventsService;
use App\Services\UsesRepositories;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Fork\Fork;

class TasyService
{
    use UsesRepositories;

    // ==================== CONSTANTES ====================
    private const CACHE_TTL_SECTOR = 900;  // 15 minutos

    private const CACHE_TTL_PATIENT = 600;  // 10 minutos

    private const SBAR_CACHE_VERSION = 3;

    private const PRESCRIPTIONS_CACHE_VERSION = 5;

    private TasyFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new TasyFormatter;
    }

    // ==================== MÉTODOS PÚBLICOS PRINCIPAIS ====================

    /**
     * Busca pacientes de um setor para SBAR Report
     */
    public function getSectorPatientsForSbar(int $sectorId): array
    {
        $cacheKey = $this->sectorPatientsCacheKey($sectorId);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECTOR, function () use ($sectorId) {
            $t0 = microtime(true);

            $sectorContext = $this->getSectorContext($sectorId);

            $t1 = microtime(true);
            $rawBeds = $this->fetchSectorBedsRaw($sectorId);
            $t2 = microtime(true);

            $attendanceNumbers = $this->extractAttendanceNumbers($rawBeds);

            $t3 = microtime(true);
            $batchData = $this->fetchBatchData($sectorId, $attendanceNumbers);
            $t4 = microtime(true);

            $result = $rawBeds->map(function ($bed) use ($sectorContext, $batchData) {
                return $this->formatter->formatPatientForSbar($bed, $sectorContext, $batchData);
            })->values()->toArray();
            $t5 = microtime(true);

            try {
                Log::debug('[TasyService] getSectorPatientsForSbar timing', [
                    'sector_id' => $sectorId,
                    'patient_count' => count($result),
                    'beds_ms' => round(($t2 - $t1) * 1000),
                    'batch_ms' => round(($t4 - $t3) * 1000),
                    'format_ms' => round(($t5 - $t4) * 1000),
                    'total_ms' => round(($t5 - $t0) * 1000),
                ]);
            } catch (\Throwable) {
            }

            return $result;
        });
    }

    /**
     * Busca dados básicos do paciente para Patient Modal
     */
    public function getPatientBasicData(int $attendanceNumber): ?object
    {
        if (! $attendanceNumber) {
            return null;
        }

        $cacheKey = $this->patientModalCacheKey($attendanceNumber);

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

        return $this->clinical()->getCidHistory($attendanceNumber);
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
     * Pre-warms the sector patients cache in the background.
     * Skips sectors whose cache is already populated.
     */
    public function warmSectorCache(int $sectorId): bool
    {
        if (Cache::has($this->sectorPatientsCacheKey($sectorId))) {
            return false;
        }

        try {
            $this->getSectorPatientsForSbar($sectorId);

            return true;
        } catch (\Throwable $e) {
            Log::warning('TasyService: Failed to warm sector cache', [
                'sector_id' => $sectorId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==================== CACHE MANAGEMENT ====================

    public function clearSectorCache(int $sectorId): void
    {
        Cache::forget($this->sectorPatientsCacheKey($sectorId));
        Cache::forget("sector_pending_fast_{$sectorId}");
    }

    public function clearPatientCache(int $attendanceNumber): void
    {
        $keys = [
            $this->patientModalCacheKey($attendanceNumber),
            $this->prescriptionsCacheKey($attendanceNumber),
            "patient_therapeutic_plan_{$attendanceNumber}", // legacy key (kept for cache eviction)
            "patient_therapeutic_plan_v4_{$attendanceNumber}", // legacy key (kept for cache eviction)
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
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function sectorPatientsCacheKey(int $sectorId): string
    {
        return 'sector_patients_sbar_v'.self::SBAR_CACHE_VERSION."_{$sectorId}";
    }

    private function patientModalCacheKey(int $attendanceNumber): string
    {
        return 'patient_basic_modal_v'.self::SBAR_CACHE_VERSION."_{$attendanceNumber}";
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
            'doctor',
            'attendingDoctor',
        ])->find($attendanceNumber);
    }

    private function fetchSectorBedsRaw(int $sectorId)
    {
        $sector = Sector::find($sectorId);
        if (! $sector) {
            return collect([]);
        }

        $results = DB::connection('tasy')->select("
            SELECT
                ua.cd_unidade_basica,
                ua.nr_seq_interno as bed_sequence,
                ua.nr_seq_apresent as bed_display_order,
                ua.ie_situacao as bed_status,
                ua.cd_setor_atendimento,
                NVL(sa.ds_prescricao, sa.ds_setor_atendimento) AS ds_setor_atendimento,
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
                ua.nr_seq_apresent ASC,
                ua.cd_unidade_basica ASC
        ", ['sector_id' => $sectorId]);

        return collect($results);
    }

    private function fetchBatchData(int $sectorId, array $attendanceNumbers): array
    {
        $ta = microtime(true);
        $mode = 'sequential';
        $clinicalMs = 0;
        $scalesMs = 0;
        $pendingMs = 0;

        if (function_exists('pcntl_fork')) {
            $mode = 'parallel_fork';

            [$clinicalPayload, $scalesPayload, $pendingPayload] = Fork::new()
                ->after(child: function () {
                    DB::connection('tasy')->reconnect();
                    DB::connection('mysql')->reconnect();
                    try {
                        app('redis')->connection('default')->client()->close();
                    } catch (\Throwable) {
                    }
                })
                ->run(
                    function () use ($attendanceNumbers) {
                        $tStart = microtime(true);
                        $data = $this->fetchBatchClinicalData($attendanceNumbers);

                        return [
                            'data' => $data,
                            'ms' => round((microtime(true) - $tStart) * 1000),
                        ];
                    },
                    function () use ($attendanceNumbers) {
                        $tStart = microtime(true);
                        $data = $this->fetchBatchScales($attendanceNumbers);

                        return [
                            'data' => $data,
                            'ms' => round((microtime(true) - $tStart) * 1000),
                        ];
                    },
                    function () use ($sectorId) {
                        $tStart = microtime(true);
                        $data = (new PatientPendingEventsService)->getPendingEventsForSector($sectorId);

                        return [
                            'data' => $data,
                            'ms' => round((microtime(true) - $tStart) * 1000),
                        ];
                    },
                );

            $clinicalBatch = $clinicalPayload['data'] ?? [];
            $scales = $scalesPayload['data'] ?? [];
            $pendingRaw = $pendingPayload['data'] ?? [];

            $clinicalMs = (int) ($clinicalPayload['ms'] ?? 0);
            $scalesMs = (int) ($scalesPayload['ms'] ?? 0);
            $pendingMs = (int) ($pendingPayload['ms'] ?? 0);
        } else {
            $tClinical = microtime(true);
            $clinicalBatch = $this->fetchBatchClinicalData($attendanceNumbers);
            $clinicalMs = (int) round((microtime(true) - $tClinical) * 1000);

            $tScales = microtime(true);
            $scales = $this->fetchBatchScales($attendanceNumbers);
            $scalesMs = (int) round((microtime(true) - $tScales) * 1000);

            $tPending = microtime(true);
            $pendingRaw = (new PatientPendingEventsService)->getPendingEventsForSector($sectorId);
            $pendingMs = (int) round((microtime(true) - $tPending) * 1000);
        }

        $tb = microtime(true);
        $parallelMs = (int) round(($tb - $ta) * 1000);
        $maxBranchMs = max($clinicalMs, $scalesMs, $pendingMs);
        $sumBranchMs = $clinicalMs + $scalesMs + $pendingMs;
        $estimatedOverheadMs = $mode === 'parallel_fork'
            ? max(0, $parallelMs - $maxBranchMs)
            : max(0, $parallelMs - $sumBranchMs);

        try {
            Log::debug('[TasyService] fetchBatchData breakdown', [
                'sector_id' => $sectorId,
                'nr_count' => count($attendanceNumbers),
                'mode' => $mode,
                'parallel_ms' => $parallelMs,
                'clinical_branch_ms' => $clinicalMs,
                'scales_branch_ms' => $scalesMs,
                'pending_branch_ms' => $pendingMs,
                'max_branch_ms' => $maxBranchMs,
                'sum_branch_ms' => $sumBranchMs,
                'estimated_overhead_ms' => $estimatedOverheadMs,
            ]);
        } catch (\Throwable) {
        }

        $pendingEventsMap = [];
        $dischargeInfoMap = [];
        foreach ($pendingRaw as $nr => $data) {
            $pendingEventsMap[$nr] = $data['events'] ?? [];
            $dischargeInfoMap[$nr] = ! empty($data['discharge']) ? $data['discharge'] : null;
        }

        return [
            'clinical_details' => $clinicalBatch['clinical_details'] ?? [],
            'surgery' => $clinicalBatch['surgery'] ?? [],
            'surgery_detailed' => $clinicalBatch['surgery_detailed'] ?? [],
            'multidisciplinary' => $clinicalBatch['multidisciplinary'] ?? [],
            'multidisciplinary_requests' => $clinicalBatch['multidisciplinary_requests'] ?? [],
            'scales' => $scales,
            'pending_events' => $pendingEventsMap,
            'discharge_info' => $dischargeInfoMap,
        ];
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

        $t1 = microtime(true);
        $clinicalDetails = $this->clinical()->getBatchClinicalDetails($attendanceNumbers);
        $t2 = microtime(true);
        $surgeries = $this->surgery()->getFutureSurgeriesForAttendances($attendanceNumbers);
        $t3 = microtime(true);
        $multidisciplinary = $this->multidisciplinary()->getMultidisciplinaryTeams($attendanceNumbers);
        $t4 = microtime(true);
        $multidisciplinaryReqs = $this->multidisciplinary()->getMultidisciplinaryRequestsBatch($attendanceNumbers);
        $t5 = microtime(true);

        try {
            Log::debug('[TasyService] fetchBatchClinicalData breakdown', [
                'clinical_details_ms' => round(($t2 - $t1) * 1000),
                'surgeries_ms' => round(($t3 - $t2) * 1000),
                'multi_teams_ms' => round(($t4 - $t3) * 1000),
                'multi_requests_ms' => round(($t5 - $t4) * 1000),
            ]);
        } catch (\Throwable) {
        }

        return [
            'surgery' => $surgeries['surgery'] ?? [],
            'surgery_detailed' => $surgeries['surgery_detailed'] ?? [],
            'multidisciplinary' => $multidisciplinary,
            'multidisciplinary_requests' => $multidisciplinaryReqs,
            'clinical_details' => $clinicalDetails,
        ];
    }

    private function fetchBatchScales(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        return $this->scales()->getPatientsScalesUnified($attendanceNumbers, []);
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

        return $map[$attendanceNumber] ?? null;
    }

    // ==================== MÉTODOS PRIVADOS - DATA ASSEMBLY ====================

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
            $ageMonths = $birth->copy()->addYears($age)->diffInMonths($now);
            $ageDays = $birth->copy()->addYears($age)->addMonths($ageMonths)->diffInDays($now);
        }

        $internmentDays = null;
        if ($patient->dt_entrada) {
            $internmentDays = floor(Carbon::parse($patient->dt_entrada)->floatDiffInDays(now()));
        }

        $convenio = 'Convênio não informado';
        try {
            $convenioResult = DB::connection('tasy')->selectOne('
                SELECT TASY.obter_desc_convenio(TASY.obter_convenio_atendimento(?)) as convenio
                FROM DUAL
            ', [$patient->nr_atendimento]);

            if ($convenioResult && ! empty($convenioResult->convenio)) {
                $convenio = trim($convenioResult->convenio);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch convenio', [
                'attendance' => $patient->nr_atendimento,
                'error' => $e->getMessage(),
            ]);
        }

        $medicoResponsavel = 'Não informado';
        if ($patient->doctor && ! empty($patient->doctor->nm_pessoa_fisica)) {
            $medicoResponsavel = trim($patient->doctor->nm_pessoa_fisica);
        } else {
            try {
                $medicoResult = DB::connection('tasy')->selectOne("
                    SELECT TASY.obter_medico_resp_atend(?, 'N') as medico
                    FROM DUAL
                ", [$patient->nr_atendimento]);

                if ($medicoResult && ! empty($medicoResult->medico)) {
                    $medicoResponsavel = trim($medicoResult->medico);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch medico', [
                    'attendance' => $patient->nr_atendimento,
                    'error' => $e->getMessage(),
                ]);
            }
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

    // ==================== MÉTODOS PRIVADOS - HELPERS ====================

    private function extractAttendanceNumbers($beds): array
    {
        return $beds->pluck('nr_atendimento')->filter()->values()->toArray();
    }

    private function getSectorContext(int $sectorId): array
    {
        $sector = Sector::with('hospital')->find($sectorId);

        return [
            'sector_id' => $sectorId,
            'sector_name' => $sector?->ds_prescricao ?? $sector?->ds_setor_atendimento ?? '',
            'hospital_id' => $sector?->hospital?->nr_sequencia ?? null,
            'hospital_name' => $sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado',
        ];
    }
}
