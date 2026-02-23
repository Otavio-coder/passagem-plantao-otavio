<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\EMR\Core\{Patient, Person, Bed, Sector, Doctor, Hospital};

class TasyService
{
    use UsesRepositories;

    // ==================== CONSTANTES ====================
    private const CACHE_TTL_SECTOR = 900;  // 15 minutos
    private const CACHE_TTL_PATIENT = 600; // 10 minutos
    private const CACHE_TTL_PENDING_EVENTS = 600; //  10 minutos para pending events

    private const DEFAULT_SCALE_DATA = [
        'score' => null,
        'needs_assessment' => true,
        'increased' => false,
        'classification' => 'Não classificado',
        'styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
        'shift' => null,
        'timestamp' => null,
    ];

    // ==================== MÉTODOS PÚBLICOS PRINCIPAIS ====================

    /**
     * Busca pacientes de um setor para SBAR Report
     */
    public function getSectorPatientsForSbar(int $sectorId): array
    {
        $cacheKey = "sector_patients_sbar_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECTOR, function() use ($sectorId) {
            $sectorContext = $this->getSectorContext($sectorId);
            $rawBeds = $this->fetchSectorBedsRaw($sectorId);
            $attendanceNumbers = $this->extractAttendanceNumbers($rawBeds);

            // Busca dados em batch (otimizado)
            $batchData = $this->fetchBatchData($sectorId, $attendanceNumbers);

            // Formata cada paciente
            return $rawBeds->map(function($bed) use ($sectorContext, $batchData) {
                return $this->formatPatientForSbar($bed, $sectorContext, $batchData);
            })->values()->toArray();
        });
    }

    /**
     * Busca dados básicos do paciente (sem CPOE) para Patient Modal
     */
    public function getPatientBasicData(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) return null;

        $cacheKey = "patient_basic_modal_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function() use ($attendanceNumber) {
            $patient = $this->loadPatientWithRelations($attendanceNumber);
            if (!$patient) return null;

            $basicData = $this->buildBasicDataFromEloquent($patient);
            $batchClinicalData = $this->fetchBatchClinicalData([$attendanceNumber]);
            $clinicalData = $this->extractClinicalDataForPatient($attendanceNumber, $batchClinicalData, $patient->person_id);
            $scalesData = $this->fetchScalesData($attendanceNumber, $patient->isPediatric());

            return $this->assemblePatientData($basicData, $clinicalData, $scalesData);
        });
    }

    /**
     * Busca apenas dados CPOE do paciente
     */
    public function getPatientCPOEData(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) return null;

        $cacheKey = "patient_cpoe_modal_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function() use ($attendanceNumber) {
            return $this->fetchCPOEData($attendanceNumber);
        });
    }

    /**
     * Busca dados completos do paciente (básico + CPOE)
     */
    public function getFullPatientData(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) return null;

        $cacheKey = "patient_full_data_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PATIENT, function() use ($attendanceNumber) {
            $basicData = $this->getPatientBasicData($attendanceNumber);
            if (!$basicData) return null;

            $cpoeData = $this->fetchCPOEData($attendanceNumber);
            return $this->mergeCPOEWithBasicData($basicData, $cpoeData);
        });
    }

    // ==================== CACHE MANAGEMENT ====================

    public function clearSectorCache(int $sectorId): void
    {
        Cache::forget("sector_patients_sbar_{$sectorId}");
        Cache::forget("pending_events_sector_{$sectorId}");
    }

    public function clearPatientCache(int $attendanceNumber): void
    {
        $keys = [
            "patient_full_data_{$attendanceNumber}",
            "patient_basic_modal_{$attendanceNumber}",
            "patient_cpoe_modal_{$attendanceNumber}",
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
            return $this->clinical()->getPatientActiveAlerts($attendanceNumber, $personId);
        } catch (\Throwable $e) {
            Log::warning('TasyService: Failed to fetch alerts', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    // ==================== MÉTODOS PRIVADOS - DATA FETCHING ====================

    /**
     * Carrega paciente com todas as relações necessárias
     */
    private function loadPatientWithRelations(int $attendanceNumber): ?Patient
    {
        return Patient::with([
            'person',
            'bed.sector.hospital',
            'doctor',
            'attendingDoctor'
        ])->find($attendanceNumber);
    }

    /**
     * Busca dados brutos dos leitos de um setor
     */
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
                TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS internment_days
            FROM tasy.unidade_atendimento ua
            JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
            LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento AND atp.dt_alta IS NULL
            LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
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
        $scales = $this->fetchBatchScales($attendanceNumbers);
        $pendingEvents = $this->fetchPendingEventsBySector($sectorId);

        return [
            'clinical_details' => $clinicalBatch['clinical_details'] ?? [],
            'surgery' => $clinicalBatch['surgery'] ?? [],
            'surgery_detailed' => $clinicalBatch['surgery_detailed'] ?? [],
            'multidisciplinary' => $clinicalBatch['multidisciplinary'] ?? [],
            'priority_exams' => $clinicalBatch['priority_exams'] ?? [],
            'scales' => $scales,
            'pending_events' => $pendingEvents,
        ];
    }

    private function fetchBatchClinicalData(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [
                'surgery' => [],
                'surgery_detailed' => [],
                'multidisciplinary' => [],
                'priority_exams' => [],
                'clinical_details' => [],
            ];
        }

        // getBatchClinicalDetails já chama getFutureSurgeriesForAttendances e
        // getPriorityExamsForAttendances internamente — aproveitamos o resultado.
        $clinicalDetails = $this->clinical()->getBatchClinicalDetails($attendanceNumbers);
        $multidisciplinary = $this->clinical()->getMultidisciplinaryTeams($attendanceNumbers);

        $surgeryMap = [];
        $surgeryDetailedMap = [];
        $priorityExamsMap = [];

        foreach ($clinicalDetails as $nr => $detail) {
            $surgeryDetailedMap[$nr] = $detail->procedimentos_cirurgicos ?? [];
            $surgeryMap[$nr] = !empty($detail->procedimentos_cirurgicos);
            $priorityExamsMap[$nr] = $detail->prioridade_exames ?? null;
        }

        return [
            'surgery' => $surgeryMap,
            'surgery_detailed' => $surgeryDetailedMap,
            'multidisciplinary' => $multidisciplinary,
            'priority_exams' => $priorityExamsMap,
            'clinical_details' => $clinicalDetails,
        ];
    }

    /**
     * Busca escalas em batch
     */
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
                'prioridade_exames' => null,
                'procedimentos_cirurgicos' => [],
                'alerts' => [],
                'multidisciplinary' => $this->getDefaultMultidisciplinary(),
                'has_allergy' => false,
                'has_isolation' => false,
            ];
        }

        $alerts = [];
        if ($personId) {
            try {
                $alerts = $this->clinical()->getPatientActiveAlerts($attendanceNumber, $personId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch alerts', [
                    'attendance' => $attendanceNumber,
                    'person_id' => $personId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $multidisciplinaryEval = null;
        try {
            $multidisciplinaryEval = $this->clinical()->getMultidisciplinaryTeamEvaluations($attendanceNumber);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch multidisciplinary evaluations', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
        }

        $hasAllergy = $this->checkHasAllergy($details->alergias_detalhadas ?? null);
        $hasIsolation = $this->checkHasIsolation($details->medida_bloqueio ?? null);

        return (object)[
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
            'prioridade_exames' => $details->prioridade_exames ?? null,
            'procedimentos_cirurgicos' => $details->procedimentos_cirurgicos ?? [],
            'alerts' => $alerts,
            'multidisciplinary' => $multidisciplinaryEval ?? $this->getDefaultMultidisciplinary(),
            'has_allergy' => $hasAllergy,
            'has_isolation' => $hasIsolation,
        ];
    }

    /**
     * Busca escalas de um paciente específico
     */
    private function fetchScalesData(int $attendanceNumber, bool $isPediatric = false): ?array
    {
        if (!$attendanceNumber) return null;
        $map = $this->scales()->getPatientsScalesUnified([$attendanceNumber], []);
        return $map[$attendanceNumber] ?? null;
    }

    /**
     * Busca dados CPOE de um paciente
     */
    private function fetchCPOEData(int $attendanceNumber): object
    {
        return (object)[
            'cpoe_procedures' => $this->cpoe()->getPatientCpoeProcedures($attendanceNumber),
            'cpoe_medications' => $this->cpoe()->getPatientMedications($attendanceNumber),
            'cpoe_nutrition' => $this->cpoe()->getPatientNutrition($attendanceNumber),
            'cpoe_recommendations' => $this->cpoe()->getPatientRecommendations($attendanceNumber),
            'cpoe_interventions' => $this->cpoe()->getPatientInterventions($attendanceNumber)
        ];
    }

    private function fetchPendingEventsBySector(int $sectorId): array
    {
        $cacheKey = "pending_events_sector_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_PENDING_EVENTS, function() use ($sectorId) {
            $results = DB::connection('tasy')->select("
                SELECT
                    ua.nr_atendimento,
                    ap.cd_pessoa_fisica,
                    pf.dt_obito,
                    ap.dt_alta,
                    ap.dt_alta_medico,
                    ap.dt_previsto_alta,
                    ma2.ds_motivo_alta,
                    apa.dt_previsto_alta as dt_previsto_alta_tabela,
                    apa.dt_registro as dt_registro_previsao,
                    COUNT(DISTINCT CASE
                        WHEN pp.nr_seq_proc_interno IS NOT NULL
                            AND pp.dt_baixa IS NULL
                        THEN pp.nr_seq_proc_interno
                    END) as proc_count,
                    COUNT(DISTINCT CASE
                        WHEN ae.nr_sequencia IS NOT NULL
                        THEN ae.nr_sequencia
                    END) as exam_count,
                    COUNT(DISTINCT CASE
                        WHEN hemo.nr_sequencia IS NOT NULL
                        THEN hemo.nr_sequencia
                    END) as hemo_count,
                    COUNT(DISTINCT CASE
                        WHEN aq.nr_sequencia IS NOT NULL
                        THEN aq.nr_sequencia
                    END) as quimio_count
                FROM tasy.unidade_atendimento ua
                INNER JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
                INNER JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
                LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
                LEFT JOIN (
                    SELECT nr_atendimento, dt_previsto_alta, dt_registro,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) as rn
                    FROM tasy.atend_previsao_alta
                    WHERE dt_registro >= SYSDATE - 10
                ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
                LEFT JOIN tasy.prescr_medica pm ON pm.nr_atendimento = ua.nr_atendimento
                    AND pm.dt_liberacao IS NOT NULL
                LEFT JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                    AND pp.dt_prev_execucao BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                    AND pp.nr_seq_proc_interno NOT IN (1341, 5970)
                LEFT JOIN tasy.agenda_paciente ae ON ae.nr_atendimento = ua.nr_atendimento
                    AND ae.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                    AND ae.ie_status_agenda NOT IN ('C', 'S')
                LEFT JOIN tasy.cpoe_hemoterapia hemo ON hemo.nr_atendimento = ua.nr_atendimento
                    AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                    AND hemo.dt_suspensao IS NULL
                LEFT JOIN tasy.agenda_quimioterapia_pep_v aq ON aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
                    AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                WHERE ua.cd_setor_atendimento = :sector_id
                    AND ua.ie_situacao = 'A'
                    AND ap.dt_alta IS NULL
                GROUP BY
                    ua.nr_atendimento,
                    ap.cd_pessoa_fisica,
                    pf.dt_obito,
                    ap.dt_alta,
                    ap.dt_alta_medico,
                    ap.dt_previsto_alta,
                    ma2.ds_motivo_alta,
                    apa.dt_previsto_alta,
                    apa.dt_registro
            ", ['sector_id' => $sectorId]);

            $map = [];
            $needsProcDetails = [];
            $needsExamDetails = [];
            $needsHemoDetails = [];
            $needsQuimioDetails = [];

            foreach ($results as $row) {
                $nr = $row->nr_atendimento;

                // Óbito tem prioridade
                if (!empty($row->dt_obito)) {
                    $horaObito = date('d/m/Y H:i', strtotime($row->dt_obito));
                    $map[$nr] = [[
                        'icon' => 'alert-circle.svg',
                        'text' => '[ÓBITO] - Horário: ' . $horaObito,
                        'type' => 'obito'
                    ]];
                    continue;
                }

                $events = [];

                // Alta/Alta Médica/Previsão
                if (!empty($row->dt_alta)) {
                    $text = '[ALTA] ' . date('d/m H:i', strtotime($row->dt_alta));
                    if (!empty($row->ds_motivo_alta)) {
                        $text .= ' Motivo: ' . $row->ds_motivo_alta;
                    }
                    if (!empty($row->dt_previsto_alta)) {
                        $text .= ' | Previsão: ' . date('d/m H:i', strtotime($row->dt_previsto_alta));
                    }
                    $events[] = [
                        'icon' => 'alta.svg',
                        'text' => $text,
                        'type' => 'alta'
                    ];
                } elseif (!empty($row->dt_alta_medico)) {
                    $text = '[ALTA MÉDICA] ' . date('d/m H:i', strtotime($row->dt_alta_medico));
                    if (!empty($row->dt_previsto_alta)) {
                        $text .= ' | Previsão: ' . date('d/m H:i', strtotime($row->dt_previsto_alta));
                    }
                    $events[] = [
                        'icon' => 'alta.svg',
                        'text' => $text,
                        'type' => 'alta_medica'
                    ];
                } elseif (!empty($row->dt_previsto_alta)) {
                    $events[] = [
                        'icon' => 'alert-circle.svg',
                        'text' => '[PREVISÃO DE ALTA] ' . date('d/m H:i', strtotime($row->dt_previsto_alta)),
                        'type' => 'previsao_alta'
                    ];
                }

                // Previsão de Alta da tabela atend_previsao_alta (permanente quando registrado nos últimos 10 dias)
                if (!empty($row->dt_previsto_alta_tabela)) {
                    $dtPrevistoFormatted = date('d/m/Y H:i', strtotime($row->dt_previsto_alta_tabela));
                    $dtRegistroFormatted = !empty($row->dt_registro_previsao)
                        ? date('d/m/Y', strtotime($row->dt_registro_previsao))
                        : '';
                    $text = '[PREVISÃO DE ALTA] ' . $dtPrevistoFormatted;
                    if ($dtRegistroFormatted) {
                        $text .= ' (Reg: ' . $dtRegistroFormatted . ')';
                    }
                    $events[] = [
                        'icon' => 'alta.svg',
                        'text' => $text,
                        'type' => 'previsao_alta_registrada'
                    ];
                }

                // Marca quais precisam de detalhes
                if ($row->proc_count > 0) $needsProcDetails[] = $nr;
                if ($row->exam_count > 0) $needsExamDetails[] = $nr;
                if ($row->hemo_count > 0) $needsHemoDetails[] = $nr;
                if ($row->quimio_count > 0) $needsQuimioDetails[$nr] = $row->cd_pessoa_fisica;

                $map[$nr] = $events;
            }

            // ✅ Busca detalhes apenas se necessário
            if (!empty($needsProcDetails)) {
                $this->addProcedureDetails($map, $needsProcDetails);
            }

            if (!empty($needsExamDetails)) {
                $this->addExamDetails($map, $needsExamDetails);
            }

            if (!empty($needsHemoDetails)) {
                $this->addHemotherapyDetails($map, $needsHemoDetails);
            }

            if (!empty($needsQuimioDetails)) {
                $this->addChemotherapyDetails($map, $needsQuimioDetails);
            }

            return $map;
        });
    }

    private function addProcedureDetails(array &$map, array $attendanceNumbers): void
    {
        $chunks = array_chunk($attendanceNumbers, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $procs = DB::connection('tasy')->select("
                SELECT DISTINCT
                    pm.nr_atendimento,
                    pp.nr_seq_proc_interno,
                    pp.dt_prev_execucao,
                    TASY.OBTER_DESC_PROC_INTERNO(pp.nr_seq_proc_interno) as descricao
                FROM tasy.prescr_medica pm
                INNER JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                WHERE pm.nr_atendimento IN ($placeholders)
                  AND pp.dt_prev_execucao BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                  AND pp.dt_baixa IS NULL
                  AND pm.dt_liberacao IS NOT NULL
                  AND pp.nr_seq_proc_interno NOT IN (1341, 5970)
                ORDER BY pm.nr_atendimento, pp.dt_prev_execucao
            ", $chunk);

            foreach ($procs as $proc) {
                $map[$proc->nr_atendimento][] = [
                    'icon' => 'outpatient-department.svg',
                    'text' => '[Proc] ' . $proc->descricao . ' ' . date('d/m H:i', strtotime($proc->dt_prev_execucao)),
                    'type' => 'procedimento'
                ];
            }
        }
    }

    private function addExamDetails(array &$map, array $attendanceNumbers): void
    {
        $chunks = array_chunk($attendanceNumbers, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $exams = DB::connection('tasy')->select("
            SELECT
                ae.nr_atendimento,
                ae.dt_agenda,
                ae.hr_inicio,
                ae.nr_seq_sala,
                TASY.OBTER_DS_SETOR_ATENDIMENTO(TASY.OBTER_SETOR_AGENDA(ae.cd_agenda)) as nome_setor,
                COALESCE(
                    TASY.obter_exame_agenda(ae.cd_procedimento, ae.ie_origem_proced, ae.nr_seq_proc_interno),
                    TASY.Obter_exame_procedimento(ae.cd_procedimento, ae.ie_origem_proced, ae.nr_atendimento),
                    ae.ds_cirurgia,
                    'Exame agendado'
                ) as descricao,
                TO_CHAR(ae.dt_agenda, 'DD/MM') as data_formatada,
                TO_CHAR(ae.hr_inicio, 'HH24:MI') as hora_formatada
            FROM tasy.agenda_paciente ae
            WHERE ae.nr_atendimento IN ($placeholders)
              AND ae.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
              AND ae.ie_status_agenda NOT IN ('C', 'S')
            ORDER BY ae.nr_atendimento, ae.dt_agenda, ae.hr_inicio
        ", $chunk);

            foreach ($exams as $exam) {
                // Monta data/hora
                $dataHora = '';
                if (!empty($exam->data_formatada)) {
                    $dataHora = $exam->data_formatada;
                    if (!empty($exam->hora_formatada)) {
                        $dataHora .= ' ' . $exam->hora_formatada;
                    }
                }

                // Monta o texto do exame
                $text = '[Exame] ' . ($exam->descricao ?? 'Exame não especificado');

                // Adiciona data/hora se disponível
                if (!empty($dataHora)) {
                    $text .= ' ' . $dataHora;
                }

                // Monta informação de local (setor ou sala)
                $local = '';
                if (!empty($exam->nome_setor)) {
                    $local = trim($exam->nome_setor);
                }
                if (!empty($exam->nr_seq_sala)) {
                    $local .= ($local ? ' - ' : '') . 'Sala ' . $exam->nr_seq_sala;
                }

                if (!empty($local)) {
                    $text .= ' | ' . $local;
                }

                $map[$exam->nr_atendimento][] = [
                    'icon' => 'tac.svg',
                    'text' => $text,
                    'type' => 'exame'
                ];
            }
        }
    }

    private function addHemotherapyDetails(array &$map, array $attendanceNumbers): void
    {
        foreach (array_chunk($attendanceNumbers, 50) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::connection('tasy')->select("
            SELECT nr_atendimento, dt_programada, ie_tipo_hemoterap, qt_procedimento,
                   qt_vol_hemocomp, ie_via_aplicacao, ie_filtrado, ie_irradiado,
                   ie_lavado, ie_urgencia, NVL(ds_observacao, ds_justificativa) ds_obs
            FROM tasy.cpoe_hemoterapia
            WHERE nr_atendimento IN ($in)
              AND dt_programada BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
              AND dt_suspensao IS NULL AND ie_item_valido = 'S'
            ORDER BY nr_atendimento, dt_programada
        ", $chunk);

            foreach ($rows as $r) {
                $flags = array_filter([
                    ($r->ie_filtrado  ?? 'N') === 'S' ? 'Filtrado'  : null,
                    ($r->ie_irradiado ?? 'N') === 'S' ? 'Irradiado' : null,
                    ($r->ie_lavado    ?? 'N') === 'S' ? 'Lavado'    : null,
                ]);

                $text = sprintf(
                    '[Hemoterapia] %s %s - Qtde: %s - Vol: %sml - Via: %s%s%s%s',
                    date('d/m H:i', strtotime($r->dt_programada)),
                    $r->ie_tipo_hemoterap ?? 'Tipo não informado',
                    $r->qt_procedimento ?? 'N/A',
                    $r->qt_vol_hemocomp ?? 'N/A',
                    $r->ie_via_aplicacao ?? 'N/A',
                    $flags ? ' (' . implode(', ', $flags) . ')' : '',
                    ($r->ie_urgencia ?? 'N') === 'S' ? ' [URGENTE]' : '',
                    !empty($r->ds_obs) ? ' Obs: ' . trim($r->ds_obs) : ''
                );

                $map[$r->nr_atendimento][] = [
                    'icon' => 'blood-drop.svg',
                    'text' => $text,
                    'type' => 'hemoterapia'
                ];
            }
        }
    }

    private function addChemotherapyDetails(array &$map, array $attendanceToPerson): void
    {
        $personIds = array_unique(array_values($attendanceToPerson));
        $chunks = array_chunk($personIds, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $quimios = DB::connection('tasy')->select("
                SELECT
                    aq.cd_pessoa_fisica,
                    aq.dt_agenda,
                    aq.ds_local,
                    aq.nm_medico_resp,
                    aq.ds_protocolo_medic,
                    aq.nr_ciclo
                FROM tasy.agenda_quimioterapia_pep_v aq
                WHERE aq.cd_pessoa_fisica IN ($placeholders)
                  AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                ORDER BY aq.cd_pessoa_fisica, aq.dt_agenda
            ", $chunk);

            foreach ($quimios as $quimio) {
                $nr = array_search($quimio->cd_pessoa_fisica, $attendanceToPerson);
                if ($nr === false) continue;

                $text = '[Quimio] ' . date('d/m H:i', strtotime($quimio->dt_agenda)) . ' ';
                $text .= ($quimio->ds_local ?? '') . ' ';
                $text .= ($quimio->nm_medico_resp ?? '') . ' ';
                $text .= ($quimio->ds_protocolo_medic ?? '') . ' ';
                $text .= ($quimio->nr_ciclo ? $quimio->nr_ciclo . 'ª' : '');

                $map[$nr][] = [
                    'icon' => 'infusion-pump.svg',
                    'text' => trim($text),
                    'type' => 'quimioterapia'
                ];
            }
        }
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
                'error' => $e->getMessage()
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
                    'error' => $e->getMessage()
                ]);
            }
        }

        return (object)[
            'nr_atendimento' => $patient->nr_atendimento,
            'cd_pessoa_fisica' => $patient->cd_pessoa_fisica,
            'nm_pessoa_fisica' => $patient->person?->nm_pessoa_fisica ?? 'Nome não informado',
            'nr_prontuario' => $patient->person?->nr_prontuario ?? 'N/A',
            'birth_date' => $birthDate ? Carbon::parse($birthDate)->format('d/m/Y') : null,
            'age' => $age,
            'age_months' => $ageMonths,
            'age_days' => $ageDays,
            'age_detailed' => $this->formatDetailedAgeFromParts($age, $ageMonths, $ageDays),
            'sexo' => $patient->person?->ie_sexo ?? 'N/A',
            'convenio' => $convenio,
            'medico_responsavel' => $medicoResponsavel,
            'dt_entrada' => $patient->dt_entrada,
            'internment_days' => $internmentDays,
            'is_new_patient' => $internmentDays === null || $internmentDays < 1,
            'hospital_name' => $patient->bed?->sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado',
            'sector_name' => $patient->bed?->sector?->ds_setor_atendimento ?? 'Setor não identificado',
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
            (array)$basicData,
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
                'prioridade_exames' => $clinicalData->prioridade_exames,
                'procedimentos_cirurgicos' => $clinicalData->procedimentos_cirurgicos,
                'alerts' => $clinicalData->alerts,
                'multidisciplinary' => $clinicalData->multidisciplinary,
            ]
        );

        $data = $this->addScalesToData($data, $scalesData, $basicData->age ?? 99);

        return (object)$data;
    }

    private function mergeCPOEWithBasicData(object $basicData, object $cpoeData): object
    {
        $combined = (array) $basicData;
        $combined['cpoe_procedures'] = $cpoeData->cpoe_procedures;
        $combined['cpoe_medications'] = $cpoeData->cpoe_medications;
        $combined['cpoe_nutrition'] = $cpoeData->cpoe_nutrition;
        $combined['cpoe_recommendations'] = $cpoeData->cpoe_recommendations;
        $combined['cpoe_interventions'] = $cpoeData->cpoe_interventions;

        return (object) $combined;
    }

    private function formatPatientForSbar($bed, array $sectorContext, array $batchData): array
    {
        $attendanceNumber = $bed->nr_atendimento;
        $age = $bed->age ?? 99;
        $isPediatric = $age < 18;
        $internmentDays = is_numeric($bed->internment_days ?? null) ? floatval($bed->internment_days) : null;
        $isNewPatient = ($internmentDays === null || $internmentDays < 1);

        $clinicalDetails = $batchData['clinical_details'][$attendanceNumber] ?? null;

        $hasAllergy = $this->checkHasAllergy($clinicalDetails->alergias_detalhadas ?? null);
        $hasIsolation = $this->checkHasIsolation($clinicalDetails->medida_bloqueio ?? null);

        $patientData = [
            'cd_unidade_basica' => $bed->cd_unidade_basica ?? 'N/A',
            'bed_sequence' => $bed->bed_sequence ?? 0,
            'bed_status' => $bed->bed_status ?? 'A',
            'cd_setor_atendimento' => $bed->cd_setor_atendimento ?? null,
            'ds_setor_atendimento' => $bed->ds_setor_atendimento ?? 'Setor não identificado',
            'hospital_id' => $sectorContext['hospital_id'] ?? null,
            'hospital_name' => $sectorContext['hospital_name'] ?? 'Hospital não identificado',
            'nr_atendimento' => $attendanceNumber,
            'is_occupied' => (bool)($bed->is_occupied ?? false),
            'has_patient' => (bool)($bed->has_patient ?? false),
            'cd_pessoa_fisica' => $bed->cd_pessoa_fisica ?? null,
            'nm_pessoa_fisica' => $bed->nm_pessoa_fisica ?? 'Nome não informado',
            'nr_prontuario' => $bed->nr_prontuario ?? 'N/A',
            'birth_date' => $bed->birth_date ? Carbon::parse($bed->birth_date)->format('d/m/Y') : null,
            'age' => $age,
            'age_detailed' => $this->formatDetailedAgeFromParts($age, $bed->age_months ?? null, $bed->age_days ?? null),
            'sexo' => $bed->sexo ?? 'N/A',
            'convenio' => $bed->convenio ?? 'Não informado',
            'medico_responsavel' => $bed->medico_responsavel ?? 'Não informado',
            'dt_entrada' => $bed->dt_entrada ?? null,
            'internment_days' => $internmentDays,
            'is_new_patient' => $isNewPatient,
            'is_pediatric' => $isPediatric,
            'has_surgery' => $batchData['surgery'][$attendanceNumber] ?? false,
            'procedimentos_cirurgicos' => $batchData['surgery_detailed'][$attendanceNumber] ?? [],
            'multidisciplinary' => $batchData['multidisciplinary'][$attendanceNumber] ?? $this->getDefaultMultidisciplinary(),
            'priority_exams' => $batchData['priority_exams'][$attendanceNumber] ?? null,
            'diagnosticos_comorbidades' => $clinicalDetails->diagnosticos_comorbidades ?? null,
            'medida_bloqueio' => $clinicalDetails->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $clinicalDetails->motivos_isolamento ?? null,
            'avaliacao_enf' => $clinicalDetails->avaliacao_enf ?? null,
            'plano_educ' => $clinicalDetails->plano_educ ?? null,
            'pe_data' => $clinicalDetails->pe_data ?? null,
            'ds_queda' => $clinicalDetails->ds_queda ?? 'Não',
            'diag' => $clinicalDetails->diag ?? null,
            'dispositivos' => $clinicalDetails->dispositivos ?? null,
            'alergias_detalhadas' => $clinicalDetails->alergias_detalhadas ?? null,
            'materiais' => $clinicalDetails->materiais ?? null,
            'alerts' => [],
            'has_allergy' => $hasAllergy,
            'has_isolation' => $hasIsolation,
            'pending_events' => $batchData['pending_events'][$attendanceNumber] ?? [],
        ];

        $rawScales = $batchData['scales'][$attendanceNumber] ?? null;
        $patientData = $this->addScalesToData($patientData, $rawScales, $age);
        $patientData = $this->applyCardStyling($patientData, $isPediatric);

        return $patientData;
    }

    // ==================== MÉTODOS PRIVADOS - SCALES ====================

    private function addScalesToData(array $data, ?array $scales, int $age): array
    {
        $isPediatric = $age < 18;

        if (!$isPediatric) {
            $mews = $scales['mews'] ?? self::DEFAULT_SCALE_DATA;
            $data['mews_score'] = $mews['score'];
            $data['mews_previous_score'] = $mews['previous_score'] ?? null;
            $data['mews_previous_timestamp'] = $mews['previous_timestamp'] ?? null;
            $data['mews_needs_assessment'] = $mews['needs_assessment'];
            $data['mews_increased'] = $mews['increased'];
            $data['mews_classification'] = $mews['classification'];
            $data['mews_styling'] = $mews['styling'];
            $data['mews_shift'] = $mews['shift'];
            $data['mews_timestamp'] = $mews['timestamp'];
        } else {
            $pews = $scales['pews'] ?? self::DEFAULT_SCALE_DATA;
            $data['pews_score'] = $pews['score'];
            $data['pews_previous_score'] = $pews['previous_score'] ?? null;
            $data['pews_previous_timestamp'] = $pews['previous_timestamp'] ?? null;
            $data['pews_needs_assessment'] = $pews['needs_assessment'];
            $data['pews_increased'] = $pews['increased'];
            $data['pews_classification'] = $pews['classification'];
            $data['pews_styling'] = $pews['styling'];
            $data['pews_shift'] = $pews['shift'];
            $data['pews_timestamp'] = $pews['timestamp'];
        }

        foreach (['braden', 'morse', 'pain', 'vte'] as $scaleName) {
            $scale = $scales[$scaleName] ?? self::DEFAULT_SCALE_DATA;
            $data["{$scaleName}_score"] = $scale['score'];
            $data["{$scaleName}_previous_score"] = $scale['previous_score'] ?? null;
            $data["{$scaleName}_previous_timestamp"] = $scale['previous_timestamp'] ?? null;
            $data["{$scaleName}_needs_assessment"] = $scale['needs_assessment'];
            $data["{$scaleName}_increased"] = $scale['increased'];
            $data["{$scaleName}_classification"] = $scale['classification'];
            $data["{$scaleName}_styling"] = $scale['styling'];
            $data["{$scaleName}_shift"] = $scale['shift'];
            $data["{$scaleName}_timestamp"] = $scale['timestamp'];
        }

        return $data;
    }

    private function applyCardStyling(array $data, bool $isPediatric): array
    {
        if (!($data['has_patient'] ?? false)) {
            $data['gradient_class'] = 'from-gray-200 to-gray-300';
            $data['border_class'] = 'border border-gray-300';
            $data['text_color_class'] = 'text-gray-600';
            $data['gradient_style'] = 'background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);';
            return $data;
        }

        $isNewPatient = $data['is_new_patient'] ?? false;
        $score = !$isPediatric ? ($data['mews_score'] ?? null) : ($data['pews_score'] ?? null);

        if ($isNewPatient) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #ecfdf5 0%, #bbf7d0 100%);';
            $data['gradient_class'] = 'from-green-50 to-green-200';
            $data['border_class'] = 'border-2 border-green-400';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score === null) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);';
            $data['gradient_class'] = 'from-blue-50 to-blue-100';
            $data['border_class'] = 'border border-gray-300';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score >= 5) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #fff1f2 0%, #fee2e2 100%);';
            $data['gradient_class'] = 'from-red-50 to-red-100';
            $data['border_class'] = 'border-2 border-red-400';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score == 4) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);';
            $data['gradient_class'] = 'from-orange-50 to-orange-100';
            $data['border_class'] = 'border-2 border-orange-400';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score == 3) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);';
            $data['gradient_class'] = 'from-yellow-50 to-yellow-100';
            $data['border_class'] = 'border-2 border-yellow-400';
            $data['text_color_class'] = 'text-gray-800';
        } else {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);';
            $data['gradient_class'] = 'from-blue-50 to-blue-100';
            $data['border_class'] = 'border border-gray-300';
            $data['text_color_class'] = 'text-gray-800';
        }

        return $data;
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
            'sector_name' => $sector?->ds_setor_atendimento ?? '',
            'hospital_id' => $sector?->hospital?->nr_sequencia ?? null,
            'hospital_name' => $sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado'
        ];
    }

    private function formatDetailedAgeFromParts(?int $years, ?int $months, ?int $days): string
    {
        if ($years === null && $months === null && $days === null) {
            return 'Idade não informada';
        }

        $parts = [];
        if ($years > 0) $parts[] = $years . 'a';
        if ($months > 0) $parts[] = $months . 'm';
        if ($days > 0) $parts[] = $days . 'd';

        return !empty($parts) ? implode(' ', $parts) : 'Recém-nascido';
    }

    private function getDefaultMultidisciplinary(): array
    {
        return [
            'fisioterapia' => false,
            'psicologia' => false,
            'nutricao' => false,
            'fonoaudiologia' => false,
            'servico_social' => false,
            'acessos_vasculares' => false,
        ];
    }

    private function checkHasAllergy(?string $allergies): bool
    {
        if (empty($allergies)) {
            return false;
        }

        $allergies = trim($allergies);

        $noAllergyIndicators = [
            'Sem alergias registradas',
            'sem alergia',
            'não possui',
            'não informado',
            'nenhuma alergia',
            'nega alergia',
        ];

        $lowerAllergies = mb_strtolower($allergies);

        foreach ($noAllergyIndicators as $indicator) {
            if (str_contains($lowerAllergies, mb_strtolower($indicator))) {
                return false;
            }
        }

        return true;
    }

    private function checkHasIsolation(?string $isolationStatus): bool
    {
        if (empty($isolationStatus)) {
            return false;
        }

        $status = trim(mb_strtolower($isolationStatus));

        return $status === 'sim';
    }
}
