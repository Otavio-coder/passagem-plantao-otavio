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
    private const CACHE_TTL_SECTOR = 300;  // 5 minutos
    private const CACHE_TTL_PATIENT = 180; // 3 minutos

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

            // Busca dados em batch (otimizado e centralizado)
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

            // Usa a mesma função centralizada de dados clínicos (batch com 1 paciente)
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
     * Busca dados completos do paciente (básico + CPOE) - Usado quando necessário
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
            WHERE ua.cd_setor_atendimento = :sector_id AND ua.ie_situacao = 'A'
            ORDER BY
                CASE WHEN ua.nr_seq_interno IS NOT NULL THEN ua.nr_seq_interno ELSE 999999 END ASC,
                ua.cd_unidade_basica ASC
        ", ['sector_id' => $sectorId]);

        return collect($results);
    }

    /**
     * ✅ CENTRALIZADO: Busca todos os dados necessários em batch (otimizado)
     * Usado tanto para setor quanto para paciente individual
     */
    private function fetchBatchData(int $sectorId, array $attendanceNumbers): array
    {
        $clinicalBatch = $this->fetchBatchClinicalData($attendanceNumbers);

        return [
            // Mantém estrutura aninhada para compatibilidade
            'clinical' => $clinicalBatch,
            'clinical_details' => $clinicalBatch['clinical_details'] ?? [],
            'surgery' => $clinicalBatch['surgery'] ?? [],
            'surgery_detailed' => $clinicalBatch['surgery_detailed'] ?? [],
            'multidisciplinary' => $clinicalBatch['multidisciplinary'] ?? [],
            'priority_exams' => $clinicalBatch['priority_exams'] ?? [],
            'scales' => $this->fetchBatchScales($attendanceNumbers),
            'pending_events' => $this->fetchPendingEventsBySector($sectorId),
        ];
    }

    /**
     * ✅ CENTRALIZADO: Busca dados clínicos completos em batch
     * Serve tanto para busca por setor quanto para paciente individual
     * Usa métodos reais do PatientClinicalRepository
     */
    private function fetchBatchClinicalData(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [
                'surgery' => [],
                'surgery_detailed' => [],
                'multidisciplinary' => [],
                'priority_exams' => [],
                'clinical_details' => [], // Dados completos do getPatientClinicalDetails
            ];
        }

        // 1. Dados cirúrgicos (já está em batch)
        $surgeryData = $this->clinical()->getFutureSurgeriesForAttendances($attendanceNumbers);

        // 2. Equipes multidisciplinares (já está em batch)
        $multidisciplinary = $this->clinical()->getMultidisciplinaryTeams($attendanceNumbers);

        // 3. Exames prioritários (já está em batch)
        $priorityExams = $this->clinical()->getPriorityExamsForAttendances($attendanceNumbers);

        // 4. Dados clínicos detalhados individuais (iteração necessária pois não tem batch)
        $clinicalDetails = [];
        foreach ($attendanceNumbers as $nr) {
            try {
                // getPatientClinicalDetails retorna um objeto com todos os dados clínicos
                $details = $this->clinical()->getPatientClinicalDetails($nr);
                $clinicalDetails[$nr] = $details;
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch clinical details', [
                    'attendance' => $nr,
                    'error' => $e->getMessage()
                ]);
                $clinicalDetails[$nr] = null;
            }
        }

        return [
            'surgery' => $surgeryData['surgery'] ?? [],
            'surgery_detailed' => $surgeryData['surgery_detailed'] ?? [],
            'multidisciplinary' => $multidisciplinary,
            'priority_exams' => $priorityExams,
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

    /**
     * ✅ NOVO: Extrai dados clínicos para um paciente específico do batch
     * Usado por getPatientBasicData para manter consistência
     * Usa dados de getPatientClinicalDetails que já vêm completos
     */
    private function extractClinicalDataForPatient(int $attendanceNumber, array $batchData, ?int $personId): object
    {
        // Pega os dados clínicos completos que vieram do getPatientClinicalDetails
        $details = $batchData['clinical_details'][$attendanceNumber] ?? null;

        // Se não tem dados, retorna padrão
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

        // Busca alertas individuais (getPatientActiveAlerts precisa de person_id)
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

        // Busca avaliações multidisciplinares individuais
        $multidisciplinaryEval = null;
        try {
            $multidisciplinaryEval = $this->clinical()->getMultidisciplinaryTeamEvaluations($attendanceNumber);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch multidisciplinary evaluations', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
        }

        // ✅ Calcula flags
        $hasAllergy = $this->checkHasAllergy($details->alergias_detalhadas ?? null);
        $hasIsolation = $this->checkHasIsolation($details->medida_bloqueio ?? null);

        return (object)[
            // Dados que vêm do getPatientClinicalDetails
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

            // Dados buscados individualmente
            'alerts' => $alerts,
            'multidisciplinary' => $multidisciplinaryEval ?? $this->getDefaultMultidisciplinary(),

            // ✅ Flags
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

    /**
     * Busca pendências por setor
     */
    private function fetchPendingEventsBySector(int $sectorId): array
    {
        $results = DB::connection('tasy')->select("
        SELECT
            ua.nr_atendimento,
            pf.dt_obito,
            -- ALTA
            ap.dt_alta,
            ap.dt_alta_medico,
            ap.dt_previsto_alta,
            ma2.ds_motivo_alta,
            -- PROCEDIMENTOS
            (SELECT COUNT(*)
             FROM tasy.prescr_medica pm
             JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
             WHERE pm.nr_atendimento = ua.nr_atendimento
               AND pp.dt_prev_execucao BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
               AND pp.dt_baixa IS NULL
               AND pm.dt_liberacao IS NOT NULL
               AND pp.nr_seq_proc_interno NOT IN (1341, 5970)
            ) as proc_count,
            -- EXAMES
            (SELECT COUNT(*)
             FROM tasy.agenda_paciente ae
             WHERE ae.nr_atendimento = ua.nr_atendimento
               AND ae.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
               AND ae.ie_status_agenda NOT IN ('C', 'S')
            ) as exam_count,
            -- HEMOTERAPIA
            (SELECT COUNT(*)
             FROM tasy.cpoe_hemoterapia hemo
             WHERE hemo.nr_atendimento = ua.nr_atendimento
               AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
               AND hemo.dt_suspensao IS NULL
            ) as hemo_count,
            -- QUIMIOTERAPIA
            (SELECT COUNT(*)
             FROM tasy.agenda_quimioterapia_pep_v aq
             WHERE aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
               AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
            ) as quimio_count
        FROM tasy.unidade_atendimento ua
        JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
        JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
        LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
        WHERE ua.cd_setor_atendimento = :sector_id
          AND ua.ie_situacao = 'A'
          AND ap.dt_alta IS NULL
    ", ['sector_id' => $sectorId]);

        $map = [];

        foreach ($results as $row) {
            $nr_atendimento = $row->nr_atendimento;

            // Caso especial: Óbito
            if (!empty($row->dt_obito)) {
                $horaObito = date('d/m/Y H:i', strtotime($row->dt_obito));
                $map[$nr_atendimento] = [[
                    'icon' => 'alert-circle.svg',
                    'text' => '[ÓBITO] - Horário: ' . $horaObito,
                    'type' => 'obito'
                ]];
                continue;
            }

            $events = [];

            // ALTA / ALTA MÉDICA / PREVISÃO DE ALTA
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

            // PROCEDIMENTOS
            if ($row->proc_count > 0) {
                $procs = DB::connection('tasy')->select("
                SELECT DISTINCT
                    pp.nr_seq_proc_interno,
                    pp.dt_prev_execucao,
                    TASY.OBTER_DESC_PROC_INTERNO(pp.nr_seq_proc_interno) as descricao
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                WHERE pm.nr_atendimento = :nr_atendimento
                  AND pp.dt_prev_execucao BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                  AND pp.dt_baixa IS NULL
                  AND pm.dt_liberacao IS NOT NULL
                  AND pp.nr_seq_proc_interno NOT IN (1341, 5970)
                ORDER BY pp.dt_prev_execucao
            ", ['nr_atendimento' => $nr_atendimento]);

                foreach ($procs as $proc) {
                    $events[] = [
                        'icon' => 'outpatient-department.svg',
                        'text' => '[Proc] ' . $proc->descricao . ' ' . date('d/m H:i', strtotime($proc->dt_prev_execucao)),
                        'type' => 'procedimento'
                    ];
                }
            }

            // EXAMES
            if ($row->exam_count > 0) {
                $exams = DB::connection('tasy')->select("
                SELECT
                    ae.dt_agenda,
                    ae.hr_inicio,
                    COALESCE(
                        TASY.obter_exame_agenda(ae.cd_procedimento, ae.ie_origem_proced, ae.nr_seq_proc_interno),
                        TASY.Obter_exame_procedimento(ae.cd_procedimento, ae.ie_origem_proced, ae.nr_atendimento),
                        ae.ds_cirurgia,
                        'Exame agendado'
                    ) as descricao
                FROM tasy.agenda_paciente ae
                WHERE ae.nr_atendimento = :nr_atendimento
                  AND ae.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                  AND ae.ie_status_agenda NOT IN ('C', 'S')
                ORDER BY ae.dt_agenda, ae.hr_inicio
            ", ['nr_atendimento' => $nr_atendimento]);

                foreach ($exams as $exam) {
                    $events[] = [
                        'icon' => 'tac.svg',
                        'text' => '[Exame] ' . $exam->descricao . ' ' . date('d/m H:i', strtotime($exam->dt_agenda)),
                        'type' => 'exame'
                    ];
                }
            }

            // HEMOTERAPIA
            if ($row->hemo_count > 0) {
                $hemos = DB::connection('tasy')->select("
                SELECT
                    hemo.dt_programada,
                    hemo.IE_TIPO_HEMOTERAP,
                    hemo.QT_PROCEDIMENTO,
                    hemo.QT_VOL_HEMOCOMP,
                    hemo.IE_URGENCIA,
                    hemo.DS_OBSERVACAO,
                    hemo.DS_JUSTIFICATIVA
                FROM tasy.cpoe_hemoterapia hemo
                WHERE hemo.nr_atendimento = :nr_atendimento
                  AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                  AND hemo.dt_suspensao IS NULL
                ORDER BY hemo.dt_programada
            ", ['nr_atendimento' => $nr_atendimento]);

                foreach ($hemos as $hemo) {
                    $text = '[Hemoterapia] ' . date('d/m H:i', strtotime($hemo->dt_programada)) . ' ';
                    $text .= ($hemo->IE_TIPO_HEMOTERAP ?? 'Tipo não informado') . ' ';
                    $text .= 'Qtde:' . ($hemo->QT_PROCEDIMENTO ?? 'N/A') . ' ';
                    $text .= 'Vol:' . ($hemo->QT_VOL_HEMOCOMP ?? 'N/A');

                    if (($hemo->IE_URGENCIA ?? 'N') === 'S') {
                        $text .= ' [URGENTE]';
                    }

                    if (!empty($hemo->DS_OBSERVACAO) || !empty($hemo->DS_JUSTIFICATIVA)) {
                        $text .= ' Obs:' . trim(($hemo->DS_OBSERVACAO ?? '') . ' ' . ($hemo->DS_JUSTIFICATIVA ?? ''));
                    }

                    $events[] = [
                        'icon' => 'blood-drop.svg',
                        'text' => $text,
                        'type' => 'hemoterapia'
                    ];
                }
            }

            // QUIMIOTERAPIA
            if ($row->quimio_count > 0) {
                $quimios = DB::connection('tasy')->select("
                SELECT
                    aq.dt_agenda,
                    aq.ds_local,
                    aq.nm_medico_resp,
                    aq.ds_protocolo_medic,
                    aq.nr_ciclo
                FROM tasy.agenda_quimioterapia_pep_v aq
                WHERE aq.cd_pessoa_fisica = :cd_pessoa_fisica
                  AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '30' DAY
                ORDER BY aq.dt_agenda
            ", ['cd_pessoa_fisica' => $row->cd_pessoa_fisica ?? null]);

                foreach ($quimios as $quimio) {
                    $text = '[Quimio] ' . date('d/m H:i', strtotime($quimio->dt_agenda)) . ' ';
                    $text .= ($quimio->ds_local ?? '') . ' ';
                    $text .= ($quimio->nm_medico_resp ?? '') . ' ';
                    $text .= ($quimio->ds_protocolo_medic ?? '') . ' ';
                    $text .= ($quimio->nr_ciclo ? $quimio->nr_ciclo . 'ª' : '');

                    $events[] = [
                        'icon' => 'infusion-pump.svg',
                        'text' => trim($text),
                        'type' => 'quimioterapia'
                    ];
                }
            }

            $map[$nr_atendimento] = $events;
        }

        return $map;
    }

    // ==================== MÉTODOS PRIVADOS - DATA ASSEMBLY ====================

    /**
     * ✅ Monta dados básicos do paciente a partir do Eloquent
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

        // Tenta pegar do Eloquent
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
            // Identificação básica
            'nr_atendimento' => $patient->nr_atendimento,
            'cd_pessoa_fisica' => $patient->cd_pessoa_fisica,
            'nm_pessoa_fisica' => $patient->person?->nm_pessoa_fisica ?? 'Nome não informado',
            'nr_prontuario' => $patient->person?->nr_prontuario ?? 'N/A',

            // Dados demográficos
            'birth_date' => $birthDate ? Carbon::parse($birthDate)->format('d/m/Y') : null,
            'age' => $age,
            'age_months' => $ageMonths,
            'age_days' => $ageDays,
            'age_detailed' => $this->formatDetailedAgeFromParts($age, $ageMonths, $ageDays),
            'sexo' => $patient->person?->ie_sexo ?? 'N/A',

            // Dados administrativos
            'convenio' => $convenio,
            'medico_responsavel' => $medicoResponsavel,
            'dt_entrada' => $patient->dt_entrada,
            'internment_days' => $internmentDays,
            'is_new_patient' => $internmentDays === null || $internmentDays < 1,

            // Dados do hospital/setor
            'hospital_name' => $patient->bed?->sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado',
            'sector_name' => $patient->bed?->sector?->ds_setor_atendimento ?? 'Setor não identificado',
            'cd_setor_atendimento' => $patient->bed?->sector?->cd_setor_atendimento ?? null,
            'bed_name' => $patient->bed?->cd_unidade_basica ?? 'N/A',

            // Flags
            'is_pediatric' => $patient->isPediatric(),
            'has_patient' => true,
            'is_occupied' => true,
        ];
    }

    /**
     * Monta dados completos do paciente (básico + clínico + escalas)
     */
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

    /**
     * Mescla dados CPOE com dados básicos
     */
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

    /**
     * Formata dados do paciente para o SBAR Report
     */
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
            // Dados do leito
            'cd_unidade_basica' => $bed->cd_unidade_basica ?? 'N/A',
            'bed_sequence' => $bed->bed_sequence ?? 0,
            'bed_status' => $bed->bed_status ?? 'A',
            'cd_setor_atendimento' => $bed->cd_setor_atendimento ?? null,
            'ds_setor_atendimento' => $bed->ds_setor_atendimento ?? 'Setor não identificado',

            // Contexto do setor/hospital
            'hospital_id' => $sectorContext['hospital_id'] ?? null,
            'hospital_name' => $sectorContext['hospital_name'] ?? 'Hospital não identificado',

            // Dados do paciente
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

        // Adiciona escalas
        $rawScales = $batchData['scales'][$attendanceNumber] ?? null;
        $patientData = $this->addScalesToData($patientData, $rawScales, $age);

        // Estilização do card
        $patientData = $this->applyCardStyling($patientData, $isPediatric);

        return $patientData;
    }

    // ==================== MÉTODOS PRIVADOS - SCALES ====================

    /**
     * Adiciona dados de escalas ao array de dados
     */
    private function addScalesToData(array $data, ?array $scales, int $age): array
    {
        $isPediatric = $age < 18;

        // MEWS ou PEWS
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

        // Escalas comuns
        foreach (['braden', 'morse', 'dor', 'tev'] as $scaleName) {
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

    /**
     * Aplica estilização do card baseado em MEWS/PEWS
     */
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

    /**
     * Extrai números de atendimento de uma coleção de leitos
     */
    private function extractAttendanceNumbers($beds): array
    {
        return $beds->pluck('nr_atendimento')->filter()->values()->toArray();
    }

    /**
     * Obtém contexto do setor
     */
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

    /**
     * Formata idade detalhada a partir de partes (anos, meses, dias)
     */
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

    /**
     * Retorna estrutura padrão de multidisciplinar
     */
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

    /**
     * ✅ Verifica se paciente possui alergias registradas
     */
    private function checkHasAllergy(?string $allergies): bool
    {
        if (empty($allergies)) {
            return false;
        }

        $allergies = trim($allergies);

        // Lista de valores que indicam ausência de alergia
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

    /**
     * ✅ Verifica se paciente está em isolamento
     */
    private function checkHasIsolation(?string $isolationStatus): bool
    {
        if (empty($isolationStatus)) {
            return false;
        }

        $status = trim(mb_strtolower($isolationStatus));

        return $status === 'sim';
    }
}
