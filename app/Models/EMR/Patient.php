<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Repositories\EMR\PatientScales;
use App\Repositories\EMR\PatientClinical;
use App\Repositories\EMR\PatientCPOE;
use App\Services\ScaleService;
use App\Models\System\SystemConfiguration;

class Patient extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    // Models auxiliares
    protected $hospitalModel;
    protected $sectorModel;
    protected $bedUnitModel;
    
    // Repositórios auxiliares
    protected $scalesRepo;
    protected $clinicalRepo;
    protected $cpoeRepo;
    
    // Services auxiliares
    protected $scaleService;
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Inicializa models auxiliares
        $this->hospitalModel = new Hospital();
        $this->sectorModel = new Sector();
        $this->bedUnitModel = new BedUnit();
        
        // Inicializa repositórios auxiliares
        $this->scalesRepo = new PatientScales();
        $this->clinicalRepo = new PatientClinical();
        $this->cpoeRepo = new PatientCPOE();
        
        // Inicializa services auxiliares
        $this->scaleService = new ScaleService();
    }
    
    /**
     * MÉTODO PRINCIPAL PARA SBAR REPORT
     * Retorna dados otimizados de todos os pacientes de um setor para o painel de cards
     * 
     * @param int $sectorId
     * @return array
     */
    public function getSectorPatientsForSbar($sectorId)
    {
        // Mantém cache para dados principais, mas pendências SEM cache
        $cacheKey = "sector_patients_sbar_{$sectorId}";
        return Cache::remember($cacheKey, 300, function() use ($sectorId) {
            $sectorData = $this->getSectorContext($sectorId);
            $rawData = $this->fetchSectorPatientsRawData($sectorId);
            $attendanceNumbers = $rawData->pluck('nr_atendimento')->filter()->values()->toArray();
            $batchData = $this->fetchBatchClinicalData($attendanceNumbers);

            // Busca pendências gerais (sem cache)
            $pendingEventsMap = $this->fetchPendingEventsBySector($sectorId);

            return $rawData->map(function($bed) use ($sectorData, $batchData, $pendingEventsMap) {
                $patientArr = $this->formatSbarPatientData($bed, $sectorData, $batchData);
                $attendanceNumber = $bed->nr_atendimento;
                $patientArr['pending_events'] = $pendingEventsMap[$attendanceNumber] ?? '';
                return $patientArr;
            })->values()->toArray();
        });
    }
    
    /**
     * MÉTODO PRINCIPAL PARA PATIENT MODAL
     * Retorna dados completos e detalhados de um paciente específico
     * 
     * @param int $attendanceNumber
     * @return object|null
     */
    public function getFullPatientData($attendanceNumber)
    {
        if (!$attendanceNumber) {
            return null;
        }
        
        $cacheKey = "patient_full_data_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            // 1. Busca dados básicos do paciente
            $basicData = $this->fetchPatientBasicData($attendanceNumber);
            
            if (!$basicData) {
                return null;
            }
            
            // 2. Enriquece com contexto de setor e hospital
            $contextData = $this->enrichWithContext($basicData);
            
            // 3. Busca dados clínicos detalhados
            $clinicalDetails = $this->fetchPatientClinicalDetails($attendanceNumber, $basicData->cd_pessoa_fisica);
            
            // 4. Busca escalas
            $scalesData = $this->fetchPatientScales($attendanceNumber);
            
            // 5. Busca dados CPOE detalhados
            $cpoeData = $this->fetchPatientCPOEDetails($attendanceNumber);
            
            // 6. Agrega tudo em um objeto único
            return $this->aggregateFullPatientData(
                $contextData,
                $clinicalDetails,
                $scalesData,
                $cpoeData
            );
        });
    }
    
    /**
     * Busca dados brutos de todos os pacientes/leitos de um setor
     */
    private function fetchSectorPatientsRawData($sectorId)
    {
        $results = DB::connection('tasy')->select("
            SELECT 
                -- Dados do leito
                ua.cd_unidade_basica,
                ua.nr_seq_interno as bed_sequence,
                ua.ie_situacao as bed_status,
                ua.cd_setor_atendimento,
                sa.ds_setor_atendimento,
                sa.cd_estabelecimento as hospital_id,
                
                -- Status de ocupação
                CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as is_occupied,
                CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as has_patient,
                
                -- Dados do paciente (NULL se leito vazio)
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
                
                -- MEWS Score do DIA (última avaliação do dia) com turno
                (SELECT em.qt_pontuacao 
                 FROM tasy.escala_mews em 
                 WHERE em.nr_atendimento = atp.nr_atendimento 
                   AND em.dt_liberacao IS NOT NULL 
                   AND em.dt_inativacao IS NULL 
                   AND TRUNC(em.dt_avaliacao) = TRUNC(SYSDATE)
                 ORDER BY em.dt_avaliacao DESC
                 FETCH FIRST 1 ROWS ONLY) AS mews_score,
                
                -- Turno da última avaliação MEWS
                (SELECT 
                    CASE 
                        WHEN TO_CHAR(em.dt_avaliacao, 'HH24:MI') BETWEEN '07:00' AND '12:59' THEN 'M'
                        WHEN TO_CHAR(em.dt_avaliacao, 'HH24:MI') BETWEEN '13:00' AND '18:59' THEN 'T'
                        ELSE 'N'
                    END
                 FROM tasy.escala_mews em 
                 WHERE em.nr_atendimento = atp.nr_atendimento 
                   AND em.dt_liberacao IS NOT NULL 
                   AND em.dt_inativacao IS NULL 
                   AND TRUNC(em.dt_avaliacao) = TRUNC(SYSDATE)
                 ORDER BY em.dt_avaliacao DESC
                 FETCH FIRST 1 ROWS ONLY) AS mews_shift
                   
            FROM tasy.unidade_atendimento ua
            JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
            LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento 
                AND atp.dt_alta IS NULL
            LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
            WHERE ua.cd_setor_atendimento = :sector_id
              AND ua.ie_situacao = 'A'
            ORDER BY 
                CASE WHEN ua.nr_seq_interno IS NOT NULL THEN ua.nr_seq_interno ELSE 999999 END ASC,
                ua.cd_unidade_basica ASC
        ", ['sector_id' => $sectorId]);
        
        return collect($results);
    }

     private function fetchPendingEventsBySector($sectorId)
    {
        $results = DB::connection('tasy')->select("
            SELECT
                ua.nr_atendimento,
                pf.dt_obito,
                COALESCE(
                    TRIM(
                           -- Situação do Paciente
                            CASE 
                                WHEN ap.dt_alta IS NOT NULL THEN
                                    '[ALTA] ' || TO_CHAR(ap.dt_alta, 'DD/MM HH24:MI') || NVL(' Motivo: ' || ma2.ds_motivo_alta, '') ||
                                    CASE WHEN ap.dt_previsto_alta IS NOT NULL THEN ' | Previsão: ' || TO_CHAR(ap.dt_previsto_alta, 'DD/MM HH24:MI') ELSE '' END
                                WHEN ap.dt_alta_medico IS NOT NULL THEN
                                    '[ALTA MÉDICA] ' || TO_CHAR(ap.dt_alta_medico, 'DD/MM HH24:MI') ||
                                    CASE WHEN ap.dt_previsto_alta IS NOT NULL THEN ' | Previsão: ' || TO_CHAR(ap.dt_previsto_alta, 'DD/MM HH24:MI') ELSE '' END
                                WHEN ap.dt_previsto_alta IS NOT NULL THEN
                                    '[PREVISÃO DE ALTA] ' || TO_CHAR(ap.dt_previsto_alta, 'DD/MM HH24:MI')
                                ELSE ''
                            END
                            ||

                            -- Procedimentos agendados (intervalo maior: 12h, excluindo 1341 e 5970)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.prescr_medica pm
                                JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                                WHERE pm.nr_atendimento = ua.nr_atendimento
                                AND pp.dt_prev_execucao BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND pp.dt_baixa IS NULL
                                AND pm.dt_liberacao IS NOT NULL
                                AND pp.nr_seq_proc_interno NOT IN (1341, 5970)
                                ) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Proc] ' || TASY.OBTER_DESC_PROC_INTERNO(pp.nr_seq_proc_interno) || ' ' || TO_CHAR(pp.dt_prev_execucao, 'DD/MM HH24:MI'),
                                    ' | '
                                ) WITHIN GROUP (ORDER BY pp.dt_prev_execucao)
                                FROM (
                                    SELECT DISTINCT
                                        pp.nr_seq_proc_interno,
                                        pp.dt_prev_execucao
                                    FROM tasy.prescr_medica pm
                                    JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                                    WHERE pm.nr_atendimento = ua.nr_atendimento
                                    AND pp.dt_prev_execucao BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                    AND pp.dt_baixa IS NULL
                                    AND pm.dt_liberacao IS NOT NULL
                                    AND pp.nr_seq_proc_interno NOT IN (1341, 5970)
                                ) pp
                                ), ''
                            )
                            ||

                            -- Cirurgias agendadas (intervalo maior: 12h)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.agenda_paciente ap2
                                WHERE ap2.cd_pessoa_fisica = ap.cd_pessoa_fisica
                                AND ap2.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND ap2.ie_carater_cirurgia IS NOT NULL) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Cir] ' || TASY.OBTER_DESC_PROC_INTERNO(ap2.nr_seq_proc_interno) || ' ' ||
                                    TO_CHAR(ap2.dt_agenda, 'DD/MM') ||
                                    CASE 
                                        WHEN ap2.hr_inicio IS NOT NULL THEN ' ' || TO_CHAR(ap2.hr_inicio, 'HH24:MI')
                                        ELSE ' 00:00'
                                    END,
                                    ' | '
                                ) WITHIN GROUP (ORDER BY ap2.dt_agenda)
                                FROM tasy.agenda_paciente ap2
                                WHERE ap2.cd_pessoa_fisica = ap.cd_pessoa_fisica
                                AND ap2.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND ap2.ie_carater_cirurgia IS NOT NULL
                                ), ''  
                            )
                            ||

                            -- Hemoterapia agendada (intervalo maior: 12h, mantém HH24:MI pois não tem hr_inicio)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.cpoe_hemoterapia hemo
                                WHERE hemo.nr_atendimento = ua.nr_atendimento
                                AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND hemo.dt_suspensao IS NULL) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Hemot] ' || TO_CHAR(hemo.dt_programada, 'DD/MM HH24:MI') ||
                                    ' (' || NVL(hemo.IE_TIPO_HEMOTERAP, 'Tipo não informado') || ')' ||
                                    ' - Qtde: ' || NVL(TO_CHAR(hemo.QT_PROCEDIMENTO), 'N/A') ||
                                    ' - Vol: ' || NVL(TO_CHAR(hemo.QT_VOL_HEMOCOMP), 'N/A') ||
                                    CASE WHEN NVL(hemo.IE_URGENCIA, 'N') = 'S' THEN ' [URGENTE]' ELSE '' END ||
                                    NVL(' - Obs: ' || hemo.DS_OBSERVACAO, ''),
                                    ' | '
                                ) WITHIN GROUP (ORDER BY hemo.dt_programada)
                                FROM tasy.cpoe_hemoterapia hemo
                                WHERE hemo.nr_atendimento = ua.nr_atendimento
                                AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND hemo.dt_suspensao IS NULL
                                ), ''
                            )
                            ||

                            -- Quimioterapia agendada (intervalo maior: 12h, mantém HH24:MI pois não tem hr_inicio)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.agenda_quimio aq
                                WHERE aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
                                AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Quimio] ' || TO_CHAR(aq.dt_agenda, 'DD/MM HH24:MI') ||
                                    ' - Tipo: ' || NVL(aq.ie_tipo_agendamento, 'Tipo não informado') ||
                                    ' - Status: ' || TASY.OBTER_VALOR_DOMINIO(83, aq.ie_status_agenda) ||
                                    NVL(' - Obs: ' || aq.ds_observacao, ''),
                                    ' | '
                                ) WITHIN GROUP (ORDER BY aq.dt_agenda)
                                FROM tasy.agenda_quimio aq
                                WHERE aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
                                AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                ), ''
                            )
                            ||

                            -- Medicações próximas (intervalo maior: 4h)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.prescr_medica pmed
                                JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
                                JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao
                                    AND pmt.nr_sequencia = pmh.nr_seq_material
                                WHERE pmed.nr_atendimento = ua.nr_atendimento
                                AND pmh.dt_horario BETWEEN SYSDATE AND SYSDATE + INTERVAL '4' HOUR
                                AND pmed.dt_liberacao IS NOT NULL
                                AND pmed.dt_suspensao IS NULL
                                AND pmt.ie_suspenso <> 'S'
                                AND pmt.ie_tipo_medic_hd = 'D'
                                AND NVL(pmt.ie_administrar,'S') = 'S'
                                AND NVL(pmt.ie_se_necessario,'N') = 'N'
                                AND NVL(pmt.ie_acm,'N') = 'N'
                                AND pmt.nr_seq_recomendacao IS NULL
                                AND pmt.nr_sequencia_solucao IS NULL
                                AND pmt.nr_sequencia_proc IS NULL
                                AND pmt.nr_sequencia_diluicao IS NULL
                                AND pmt.nr_sequencia_dieta IS NULL
                                ) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Med] ' || SUBSTR(TASY.OBTER_DESC_MATERIAL(pmt.cd_material), 1, INSTR(TASY.OBTER_DESC_MATERIAL(pmt.cd_material) || ' ', ' ') - 1)
                                    || ' (' || INITCAP(pmt.cd_unidade_medida_dose) || ') ' || TO_CHAR(pmh.dt_horario, 'DD/MM HH24:MI'),
                                    ' | '
                                ) WITHIN GROUP (ORDER BY pmh.dt_horario)
                                FROM tasy.prescr_medica pmed
                                JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
                                JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao
                                    AND pmt.nr_sequencia = pmh.nr_seq_material
                                WHERE pmed.nr_atendimento = ua.nr_atendimento
                                AND pmh.dt_horario BETWEEN SYSDATE AND SYSDATE + INTERVAL '4' HOUR
                                AND pmed.dt_liberacao IS NOT NULL
                                AND pmed.dt_suspensao IS NULL
                                AND pmt.ie_suspenso <> 'S'
                                AND pmt.ie_tipo_medic_hd = 'D'
                                AND NVL(pmt.ie_administrar,'S') = 'S'
                                AND NVL(pmt.ie_se_necessario,'N') = 'N'
                                AND NVL(pmt.ie_acm,'N') = 'N'
                                AND pmt.nr_seq_recomendacao IS NULL
                                AND pmt.nr_sequencia_solucao IS NULL
                                AND pmt.nr_sequencia_proc IS NULL
                                AND pmt.nr_sequencia_diluicao IS NULL
                                AND pmt.nr_sequencia_dieta IS NULL
                                ), ''
                            )
                            ||

                            -- Recomendações próximas (intervalo maior: 12h)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.cpoe_recomendacao rec
                                LEFT JOIN tasy.TIPO_RECOMENDACAO tr ON rec.cd_recomendacao = tr.cd_tipo_recomendacao
                                WHERE rec.nr_atendimento = ua.nr_atendimento
                                AND rec.dt_inicio BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND rec.dt_liberacao IS NOT NULL
                                AND rec.dt_suspensao IS NULL) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Rec] ' ||
                                    SUBSTR(NVL(tr.ds_tipo_recomendacao, ''), 1, 30) || ' ' || -- Limite de 30 caracteres para tipo
                                    TO_CHAR(rec.dt_inicio, 'DD/MM HH24:MI') || ' ' ||
                                    SUBSTR(rec.ds_recomendacao, 1, 80), -- Limite de 80 caracteres para recomendação
                                    ' | '
                                ) WITHIN GROUP (ORDER BY rec.dt_inicio)
                                FROM tasy.cpoe_recomendacao rec
                                LEFT JOIN tasy.TIPO_RECOMENDACAO tr ON rec.cd_recomendacao = tr.cd_tipo_recomendacao
                                WHERE rec.nr_atendimento = ua.nr_atendimento
                                AND rec.dt_inicio BETWEEN SYSDATE AND SYSDATE + INTERVAL '12' HOUR
                                AND rec.dt_liberacao IS NOT NULL
                                AND rec.dt_suspensao IS NULL
                                ), ''
                            )
                            ||

                            -- Exames futuros (intervalo maior: 48h, lógica igual cirurgias)
                            CASE WHEN
                                (SELECT COUNT(*) FROM tasy.agenda_paciente ap_exam
                                WHERE ap_exam.nr_atendimento = ua.nr_atendimento
                                AND ap_exam.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '48' HOUR
                                AND ap_exam.ie_origem_proced <> 5
                                AND tasy.Obter_status_Agenda_Paciente(ap_exam.nr_sequencia) NOT IN ('Realizado', 'Cancelado')
                                ) > 0
                            THEN ' | ' ELSE '' END
                            ||
                            NVL(
                                (SELECT LISTAGG(
                                    '[Exame] '
                                    || SUBSTR(
                                        tasy.obter_desc_agenda(ap_exam.cd_agenda),
                                        1, 140
                                    )
                                    || ' - ' || TASY.OBTER_EXAME_AGENDA(ap_exam.cd_procedimento, ap_exam.ie_origem_proced, ap_exam.nr_seq_proc_interno)
                                    || CASE WHEN ap_exam.hr_inicio IS NOT NULL THEN ' ' || TO_CHAR(ap_exam.hr_inicio, 'HH24:MI') ELSE ' 00:00' END
                                    || ' - Status: ' || TASY.Obter_status_Agenda_Paciente(ap_exam.nr_sequencia)
                                    || CASE WHEN ap_exam.cd_setor_atendimento IS NOT NULL THEN ' - Setor: ' || TASY.OBTER_DESC_SETOR_ATEND(ap_exam.cd_setor_atendimento) ELSE '' END
                                    || CASE WHEN ap_exam.ds_observacao IS NOT NULL THEN ' - Obs: ' || ap_exam.ds_observacao ELSE '' END
                                    || CASE WHEN ap_exam.ie_origem_proced IS NOT NULL THEN ' - Tipo: ' || TASY.OBTER_VALOR_DOMINIO(95, ap_exam.ie_origem_proced) ELSE '' END,
                                    ' | '
                                ) WITHIN GROUP (ORDER BY ap_exam.dt_agenda)
                                FROM tasy.agenda_paciente ap_exam
                                WHERE ap_exam.nr_atendimento = ua.nr_atendimento
                                AND ap_exam.dt_agenda BETWEEN SYSDATE AND SYSDATE + INTERVAL '48' HOUR
                                AND ap_exam.ie_origem_proced <> 5
                                AND TASY.Obter_status_Agenda_Paciente(ap_exam.nr_sequencia) NOT IN ('Realizado', 'Cancelado')
                                ), ''
                            )
                    ),
                    ''
                ) AS pending_events
            FROM tasy.unidade_atendimento ua
            JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
            JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
            LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
            WHERE ua.cd_setor_atendimento = :sector_id
              AND ua.ie_situacao = 'A'
              AND ap.dt_alta IS NULL
            ORDER BY ua.cd_unidade_basica, pf.nm_pessoa_fisica
        ", ['sector_id' => $sectorId]);

        $map = [];
        foreach ($results as $row) {
            if (!empty($row->dt_obito)) {
                $horaObito = date('d/m/Y H:i', strtotime($row->dt_obito));
                $map[$row->nr_atendimento] = 'ALTA POR ÓBITO - Horário: ' . $horaObito;
            } else {
                $map[$row->nr_atendimento] = $row->pending_events;
            }
        }
        return $map;
    }
    
    /**
     * Busca dados clínicos em batch para múltiplos pacientes
     */
    private function fetchBatchClinicalData($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [
                'allergies' => [],
                'isolation' => [],
                'surgery' => [],
                'priority_exams' => [],
            ];
        }
        
        return [
            'allergies' => $this->fetchBatchAllergies($attendanceNumbers),
            'isolation' => $this->fetchBatchIsolation($attendanceNumbers),
            'surgery' => $this->fetchBatchSurgeryFuture($attendanceNumbers),
            'priority_exams' => $this->fetchBatchPriorityExams($attendanceNumbers),
            'allergies_detailed' => $this->fetchBatchAllergiesDetailed($attendanceNumbers),
            'isolation_detailed' => $this->fetchBatchIsolationDetailed($attendanceNumbers),
            'surgery_detailed' => $this->fetchBatchSurgeryDetailed($attendanceNumbers),
        ];
    }
    
    /**
     * Busca alergias em batch
     */
    private function fetchBatchAllergies($attendanceNumbers)
    {
        $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
        
        $results = DB::connection('tasy')->select("
            SELECT DISTINCT
                atp.nr_atendimento,
                CASE WHEN EXISTS(
                    SELECT 1 FROM tasy.W_PAN_PACIENTE wpp 
                    WHERE wpp.cd_pessoa_paciente = atp.cd_pessoa_fisica 
                    AND wpp.ds_alergias IS NOT NULL 
                    AND LENGTH(TRIM(wpp.ds_alergias)) > 0
                ) THEN 1 ELSE 0 END AS has_allergy
            FROM tasy.atendimento_paciente atp
            WHERE atp.nr_atendimento IN ({$placeholders})
        ", $attendanceNumbers);
        
        $data = [];
        foreach ($results as $result) {
            $data[$result->nr_atendimento] = (bool)$result->has_allergy;
        }
        return $data;
    }
    
    /**
     * Busca isolamentos em batch
     */
    private function fetchBatchIsolation($attendanceNumbers)
    {
        $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
        
        $results = DB::connection('tasy')->select("
            SELECT DISTINCT
                atp.nr_atendimento,
                CASE WHEN EXISTS(
                    SELECT 1 FROM tasy.atendimento_precaucao ap
                    WHERE ap.nr_atendimento = atp.nr_atendimento
                    AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                    AND ap.dt_liberacao IS NOT NULL
                    AND ap.dt_inativacao IS NULL
                ) THEN 1 ELSE 0 END AS has_isolation
            FROM tasy.atendimento_paciente atp
            WHERE atp.nr_atendimento IN ({$placeholders})
        ", $attendanceNumbers);
        
        $data = [];
        foreach ($results as $result) {
            $data[$result->nr_atendimento] = (bool)$result->has_isolation;
        }
        return $data;
    }
    
    /**
     * Busca cirurgias em batch
     */
    private function fetchBatchSurgeryFuture($attendanceNumbers)
    {
        $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';

        $results = DB::connection('tasy')->select("
            SELECT DISTINCT 
                atp.nr_atendimento,
                CASE WHEN EXISTS(
                    SELECT 1 FROM tasy.agenda_paciente pac 
                    WHERE pac.cd_pessoa_fisica = atp.cd_pessoa_fisica
                    AND pac.IE_CARATER_CIRURGIA IS NOT NULL
                    AND pac.IE_CARATER_CIRURGIA <> 'X'
                    AND pac.dt_agenda > SYSDATE
                ) THEN 1 ELSE 0 END AS has_surgery
            FROM tasy.atendimento_paciente atp
            WHERE atp.nr_atendimento IN ({$placeholders})
        ", $attendanceNumbers);

        $data = [];
        foreach ($results as $result) {
            $data[$result->nr_atendimento] = (bool)$result->has_surgery;
        }
        return $data;
    }
    
    /**
     * Busca exames prioritários em batch - CORRIGIDO para usar mesma lógica do modal
     */
    private function fetchBatchPriorityExams($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $data = [];
        
        try {
            // Busca um por vez usando a mesma lógica que funciona no modal
            foreach ($attendanceNumbers as $attendanceNumber) {
                $result = DB::connection('tasy')->select("
                    SELECT
                        tasy.obter_select_concatenado_bv(
                            'select obter_valor_dominio(95,proc.cd_tipo_procedimento) tipo
                            from prescr_procedimento prescrp,
                                prescr_medica prescrm,
                                procedimento proc
                            where prescrp.nr_prescricao = prescrm.nr_prescricao
                            and prescrp.cd_procedimento = proc.cd_procedimento
                            and prescrm.nr_atendimento = :nr_atend
                            and prescrp.ie_status_execucao = ''10''
                            and prescrp.dt_coleta is null
                            and prescrm.dt_liberacao is not null
                            and prescrp.ie_origem_proced <> 4
                            group by obter_valor_dominio(95,proc.cd_tipo_procedimento)',
                            'nr_atend=' || :nr_atendimento,
                            CHR(13)
                        ) AS prioridade_exames
                    FROM dual
                ", ['nr_atendimento' => $attendanceNumber]);

                if ($result && !empty($result[0]->prioridade_exames)) {
                    $exams = $result[0]->prioridade_exames;
                    $exams = str_replace(chr(13), ', ', $exams); // Para o card usa vírgula em vez de quebra de linha
                    $exams = trim($exams);
                    if (!empty($exams)) {
                        $data[$attendanceNumber] = $exams;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error fetching priority exams batch: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Busca detalhes de alergias em lote
     */
    private function fetchBatchAllergiesDetailed($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $data = [];
        
        try {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT DISTINCT
                    atp.nr_atendimento,
                    wpp.ds_alergias as alergias_detalhadas
                FROM tasy.atendimento_paciente atp
                JOIN tasy.W_PAN_PACIENTE wpp ON wpp.cd_pessoa_paciente = atp.cd_pessoa_fisica
                WHERE atp.nr_atendimento IN ({$placeholders})
                  AND wpp.ds_alergias IS NOT NULL 
                  AND LENGTH(TRIM(wpp.ds_alergias)) > 0
            ", $attendanceNumbers);

            foreach ($results as $result) {
                $alergias = trim($result->alergias_detalhadas ?? '');
                if (!empty($alergias)) {
                    $data[$result->nr_atendimento] = $alergias;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error fetching allergies detailed batch: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Busca detalhes de isolamento em lote
     */
    private function fetchBatchIsolationDetailed($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $data = [];
        
        try {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT DISTINCT
                    ap.nr_atendimento,
                    LISTAGG(cp.ds_precaucao || ' - ' || mi.ds_motivo, '; ') 
                    WITHIN GROUP (ORDER BY cp.ds_precaucao || ' - ' || mi.ds_motivo) as motivos_isolamento
                FROM tasy.atendimento_precaucao ap
                JOIN tasy.motivo_isolamento mi ON ap.nr_seq_motivo_isol = mi.nr_sequencia
                JOIN tasy.cih_precaucao cp ON ap.nr_seq_precaucao = cp.nr_sequencia
                WHERE ap.nr_atendimento IN ({$placeholders})
                  AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                  AND ap.dt_liberacao IS NOT NULL
                  AND ap.dt_inativacao IS NULL
                GROUP BY ap.nr_atendimento
            ", $attendanceNumbers);

            foreach ($results as $result) {
                $motivos = trim($result->motivos_isolamento ?? '');
                if (!empty($motivos)) {
                    $data[$result->nr_atendimento] = $motivos;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error fetching isolation detailed batch: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Busca detalhes de cirurgias em lote
     */
    private function fetchBatchSurgeryDetailed($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $data = [];
        
        try {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    atp.nr_atendimento,
                    TO_CHAR(aaa.dt_agenda, 'DD/MM/YYYY') as data_agenda,
                    TO_CHAR(aaa.hr_inicio, 'HH24:MI') as hora_agenda,
                    SUBSTR(
                        TASY.obter_desc_agenda(aaa.cd_agenda) || 
                        ' / Data e hora: ' || TO_CHAR(aaa.dt_agenda, 'DD/MM/YYYY') || 
                        CASE WHEN aaa.hr_inicio IS NOT NULL THEN ' ' || TO_CHAR(aaa.hr_inicio, 'HH24:MI') ELSE ' 00:00' END ||
                        ' - Proced.: ' || TASY.obter_descricao_procedimento(aaa.cd_procedimento, aaa.ie_origem_proced),
                        1, 200
                    ) as procedimento,
                    aaa.ie_carater_cirurgia,
                    aaa.ds_observacao
                FROM tasy.atendimento_paciente atp
                JOIN tasy.agenda_paciente aaa ON atp.cd_pessoa_fisica = aaa.cd_pessoa_fisica
                WHERE atp.nr_atendimento IN ({$placeholders})
                  AND aaa.IE_CARATER_CIRURGIA IS NOT NULL
                  AND aaa.IE_CARATER_CIRURGIA <> 'X'
                  AND aaa.dt_agenda > SYSDATE
                ORDER BY aaa.dt_agenda ASC, aaa.hr_inicio ASC
            ", $attendanceNumbers);

            foreach ($results as $result) {
                if (!isset($data[$result->nr_atendimento])) {
                    $data[$result->nr_atendimento] = [];
                }
                $data[$result->nr_atendimento][] = [
                    'data_agenda' => $result->data_agenda,
                    'hora_agenda' => $result->hora_agenda,
                    'procedimento' => $result->procedimento ?: 'Procedimento cirúrgico',
                    'carater_cirurgia' => $this->getCaraterCirurgiaDescription($result->ie_carater_cirurgia ?? ''),
                    'observacoes' => $result->ds_observacao ?? ''
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Error fetching surgery detailed batch: " . $e->getMessage());
        }
        
        return $data;
    }

    /**
     * Método auxiliar para obter descrição do caráter da cirurgia
     */
    private function getCaraterCirurgiaDescription($carater)
    {
        return match($carater) {
            'E' => 'Eletiva',
            'U' => 'Urgência',
            'G' => 'Emergência',
            default => 'Não informado'
        };
    }

    /**
     * Formata informações de cirurgias para tooltip
     */
    private function formatSurgeryInfo($surgeries)
    {
        if (empty($surgeries)) {
            return '';
        }
        
        return collect($surgeries)
            ->take(2) // Máximo 2 cirurgias
            ->map(function($surgery) {
                return $surgery['data_agenda'] . ' às ' . $surgery['hora_agenda'] . ' - ' . $surgery['procedimento'];
            })
            ->join("\n");
    }

    /**
     * Formata dados de um paciente para o SBAR Report
     */
    private function formatSbarPatientData($bed, $sectorData, $batchData)
    {
        $attendanceNumber = $bed->nr_atendimento;
        $internmentDaysRaw = $bed->internment_days ?? null;
        $internmentDays = is_numeric($internmentDaysRaw) ? floatval($internmentDaysRaw) : null;
        $isNewPatient = ($internmentDays === null || $internmentDaysRaw === 'N/A' || $internmentDaysRaw === '' || $internmentDays < 1);
        
        // BUSCA ESCALAS USANDO O REPOSITÓRIO ÚNICO E PROCESSA COM SCALESERVICE
        $scalesData = null;
        $processedScales = [];
        if ($attendanceNumber) {
            $rawScalesData = $this->scalesRepo->getPatientScales($attendanceNumber);
            if ($rawScalesData) {
                $isPediatric = ($bed->age ?? 99) < 18;
                $processedScales = $this->scaleService->processScalesData($rawScalesData, $isPediatric);
            }
        }
        
        $baseData = [
            // Dados do leito
            'cd_unidade_basica' => $bed->cd_unidade_basica ?? 'N/A',
            'bed_sequence' => $bed->bed_sequence ?? 0,
            'bed_status' => $bed->bed_status ?? 'A',
            
            // Contexto de setor e hospital
            'cd_setor_atendimento' => $bed->cd_setor_atendimento ?? null,
            'ds_setor_atendimento' => $bed->ds_setor_atendimento ?? 'Setor não identificado',
            'hospital_id' => $sectorData['hospital_id'] ?? null,
            'hospital_name' => $sectorData['hospital_name'] ?? 'Hospital não identificado',
            
            // Status de ocupação
            'nr_atendimento' => $attendanceNumber,
            'is_occupied' => (bool)($bed->is_occupied ?? false),
            'has_patient' => (bool)($bed->has_patient ?? false),
            
            // Dados do paciente com valores padrão
            'cd_pessoa_fisica' => $bed->cd_pessoa_fisica ?? null,
            'nm_pessoa_fisica' => $bed->nm_pessoa_fisica ?? 'Nome não informado',
            'nr_prontuario' => $bed->nr_prontuario ?? 'N/A',
            'birth_date' => $bed->birth_date ? Carbon::parse($bed->birth_date)->format('d/m/Y') : 'Não informado',
            'age' => $bed->age ?? null,
            'age_detailed' => $this->formatDetailedAge($bed->age ?? null, $bed->age_months ?? null, $bed->age_days ?? null),
            'sexo' => $bed->sexo ?? 'N/A',
            'convenio' => $bed->convenio ?? 'Não informado',
            'medico_responsavel' => $bed->medico_responsavel ?? 'Não informado',
            'dt_entrada' => $bed->dt_entrada ?? null,
            'internment_days' => $internmentDays,
            'is_new_patient' => $isNewPatient,
            'is_pediatric' => ($bed->age ?? 99) < 18,
            'has_allergy' => $batchData['allergies'][$attendanceNumber] ?? false,
            'has_isolation' => $batchData['isolation'][$attendanceNumber] ?? false,
            'has_surgery' => $batchData['surgery'][$attendanceNumber] ?? false,
            'prioridade_exames' => $batchData['priority_exams'][$attendanceNumber] ?? null,
            'alergias_detalhadas' => $batchData['allergies_detailed'][$attendanceNumber] ?? 'Sem alergias registradas',
            'motivos_isolamento' => $batchData['isolation_detailed'][$attendanceNumber] ?? 'Nenhum motivo de isolamento',
            'procedimentos_cirurgicos' => $batchData['surgery_detailed'][$attendanceNumber] ?? [],
            'cirurgias_info' => $this->formatSurgeryInfo($batchData['surgery_detailed'][$attendanceNumber] ?? []),
            'pending_events' => '',
        ];
        
        // ADICIONA DADOS DAS ESCALAS PROCESSADAS PELO SCALESERVICE
        if (!empty($processedScales)) {
            // Usa escalas processadas - MEWS ou PEWS conforme idade
            $isPediatric = ($bed->age ?? 99) < 18;
            
            if (!$isPediatric && isset($processedScales['mews'])) {
                $mews = $processedScales['mews'];
                $baseData = array_merge($baseData, [
                    'mews_score' => $mews['score'],
                    'mews_needs_assessment' => $mews['needs_assessment'] ?? true,
                    'mews_increased' => $mews['increased'] ?? false,
                    'mews_classification' => $mews['classification'],
                    'mews_styling' => $mews['styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'mews_shift' => $mews['timestamp'],
                    'ds_mews' => $mews['timestamp'] ? $mews['timestamp'] . ' - MEWS: ' . $mews['score'] : 'Sem avaliação nas últimas 24h',
                ]);
            } else {
                // Valores padrão para MEWS
                $baseData = array_merge($baseData, [
                    'mews_score' => null,
                    'mews_needs_assessment' => true,
                    'mews_increased' => false,
                    'mews_classification' => 'Não classificado',
                    'mews_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'mews_shift' => null,
                    'ds_mews' => 'Sem avaliação nas últimas 24h',
                ]);
            }
            
            if ($isPediatric && isset($processedScales['pews'])) {
                $pews = $processedScales['pews'];
                $baseData = array_merge($baseData, [
                    'pews_score' => $pews['score'],
                    'pews_needs_assessment' => $pews['needs_assessment'] ?? true,
                    'pews_increased' => $pews['increased'] ?? false,
                    'pews_classification' => $pews['classification'],
                    'pews_styling' => $pews['styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'pews_shift' => $pews['timestamp'],
                    'ds_pews' => $pews['timestamp'] ? $pews['timestamp'] . ' - PEWS: ' . $pews['score'] : 'Sem avaliação nas últimas 24h',
                ]);
            } else {
                // Valores padrão para PEWS
                $baseData = array_merge($baseData, [
                    'pews_score' => null,
                    'pews_needs_assessment' => true,
                    'pews_increased' => false,
                    'pews_classification' => 'Não classificado',
                    'pews_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'pews_shift' => null,
                    'ds_pews' => 'Sem avaliação nas últimas 24h',
                ]);
            }
            
            // Escalas comuns
            if (isset($processedScales['braden'])) {
                $braden = $processedScales['braden'];
                $baseData = array_merge($baseData, [
                    'braden_score' => $braden['score'],
                    'braden_needs_assessment' => $braden['needs_assessment'] ?? true,
                    'braden_increased' => $braden['increased'] ?? false,
                    'braden_classification' => $braden['classification'],
                    'braden_styling' => $braden['styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'ds_braden' => $braden['timestamp'] ? $braden['timestamp'] . ' - Braden: ' . $braden['score'] : 'Sem avaliação nas últimas 24h',
                ]);
            } else {
                $baseData = array_merge($baseData, [
                    'braden_score' => null,
                    'braden_needs_assessment' => true,
                    'braden_increased' => false,
                    'braden_classification' => 'Não classificado',
                    'braden_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'ds_braden' => 'Sem avaliação nas últimas 24h',
                ]);
            }
            
            if (isset($processedScales['morse'])) {
                $morse = $processedScales['morse'];
                $baseData = array_merge($baseData, [
                    'morse_score' => $morse['score'],
                    'morse_needs_assessment' => $morse['needs_assessment'] ?? true,
                    'morse_increased' => $morse['increased'] ?? false,
                    'morse_classification' => $morse['classification'],
                    'morse_styling' => $morse['styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'ds_morse' => $morse['timestamp'] ? $morse['timestamp'] . ' - Morse: ' . $morse['score'] : 'Sem avaliação nas últimas 24h',
                ]);
            } else {
                $baseData = array_merge($baseData, [
                    'morse_score' => null,
                    'morse_needs_assessment' => true,
                    'morse_increased' => false,
                    'morse_classification' => 'Não classificado',
                    'morse_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'ds_morse' => 'Sem avaliação nas últimas 24h',
                ]);
            }
            
            if (isset($processedScales['dor'])) {
                $dor = $processedScales['dor'];
                $baseData = array_merge($baseData, [
                    'dor_score' => $dor['score'],
                    'dor_needs_assessment' => $dor['needs_assessment'] ?? true,
                    'dor_increased' => $dor['increased'] ?? false,
                    'dor_classification' => $dor['classification'],
                    'dor_styling' => $dor['styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'dor_shift' => $dor['timestamp'],
                    'ds_dor' => $dor['timestamp'] ? $dor['timestamp'] . ' - Dor: ' . $dor['score'] : 'Sem avaliação nas últimas 24h',
                ]);
            } else {
                $baseData = array_merge($baseData, [
                    'dor_score' => null,
                    'dor_needs_assessment' => true,
                    'dor_increased' => false,
                    'dor_classification' => 'Não classificado',
                    'dor_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'dor_shift' => null,
                    'ds_dor' => 'Sem avaliação nas últimas 24h',
                ]);
            }
            
            if (isset($processedScales['tev'])) {
                $tev = $processedScales['tev'];
                $baseData = array_merge($baseData, [
                    'tev_score' => $tev['score'],
                    'tev_needs_assessment' => $tev['needs_assessment'] ?? true,
                    'tev_increased' => $tev['increased'] ?? false,
                    'tev_classification' => $tev['classification'],
                    'tev_styling' => $tev['styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'ds_tev' => $tev['timestamp'] ? $tev['timestamp'] . ' - TEV: ' . $tev['score'] : 'Sem avaliação nas últimas 24h',
                ]);
            } else {
                $baseData = array_merge($baseData, [
                    'tev_score' => null,
                    'tev_needs_assessment' => true,
                    'tev_increased' => false,
                    'tev_classification' => 'Não classificado',
                    'tev_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                    'ds_tev' => 'Sem avaliação nas últimas 24h',
                ]);
            }
        } else {
            // Se não há escalas processadas, define valores padrão para todas
            $baseData = array_merge($baseData, [
                'mews_score' => null,
                'mews_needs_assessment' => true,
                'mews_increased' => false,
                'mews_classification' => 'Não classificado',
                'mews_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'mews_shift' => null,
                'ds_mews' => 'Sem avaliação nas últimas 24h',
                
                'braden_score' => null,
                'braden_needs_assessment' => true,
                'braden_increased' => false,
                'braden_classification' => 'Não classificado',
                'braden_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_braden' => 'Sem avaliação nas últimas 24h',
                
                'morse_score' => null,
                'morse_needs_assessment' => true,
                'morse_increased' => false,
                'morse_classification' => 'Não classificado',
                'morse_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_morse' => 'Sem avaliação nas últimas 24h',
                
                'dor_score' => null,
                'dor_needs_assessment' => true,
                'dor_increased' => false,
                'dor_classification' => 'Não classificado',
                'dor_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'dor_shift' => null,
                'ds_dor' => 'Sem avaliação nas últimas 24h',
                
                'tev_score' => null,
                'tev_needs_assessment' => true,
                'tev_increased' => false,
                'tev_classification' => 'Não classificado',
                'tev_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_tev' => 'Sem avaliação nas últimas 24h',
                
                'pews_score' => null,
                'pews_needs_assessment' => true,
                'pews_increased' => false,
                'pews_classification' => 'Não classificado',
                'pews_styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'pews_shift' => null,
                'ds_pews' => 'Sem avaliação nas últimas 24h',
            ]);
        }
        
        return $baseData;
    }
    
    /**
     * Busca contexto do setor (hospital, nome, etc)
     */
    private function getSectorContext($sectorId)
    {
        $sector = $this->sectorModel->find($sectorId);
        $hospital = null;
        
        if ($sector && $sector->cd_estabelecimento) {
            $hospital = $this->hospitalModel->find($sector->cd_estabelecimento);
        }
        
        return [
            'sector_id' => $sectorId,
            'sector_name' => $sector ? $sector->ds_setor_atendimento : '',
            'hospital_id' => $hospital ? $hospital->cd_estabelecimento : null,
            'hospital_name' => $hospital ? $hospital->ds_estabelecimento : ''
        ];
    }
    
    /**
     * Busca dados básicos de um paciente específico
     */
    private function fetchPatientBasicData($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT
                ua.cd_unidade_basica,
                ua.nr_seq_interno as bed_sequence,
                ua.ie_situacao as bed_status,
                ua.cd_setor_atendimento,
                sa.ds_setor_atendimento,
                sa.cd_estabelecimento as hospital_id,
                atp.cd_pessoa_fisica,
                atp.nr_atendimento,
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
            FROM tasy.atendimento_paciente atp
            JOIN tasy.unidade_atendimento ua ON ua.nr_atendimento = atp.nr_atendimento
            JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
            LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
            WHERE atp.nr_atendimento = :attendance
        ", ['attendance' => $attendanceNumber]);
        
        return !empty($result) ? (object)$result[0] : null;
    }
    
    /**
     * Enriquece dados básicos com contexto de hospital
     */
    private function enrichWithContext($basicData)
    {
        $hospital = $this->hospitalModel->find($basicData->hospital_id);
        
        $basicData->hospital_name = $hospital ? $hospital->ds_estabelecimento : '';
        $basicData->age_detailed = $this->formatDetailedAge(
            $basicData->age,
            $basicData->age_months,
            $basicData->age_days
        );
        $basicData->birth_date = $basicData->birth_date 
            ? Carbon::parse($basicData->birth_date)->format('d/m/Y') 
            : null;
            
        return $basicData;
    }
    
    /**
     * Busca dados clínicos detalhados de um paciente
     */
    private function fetchPatientClinicalDetails($attendanceNumber, $personId)
    {
        // Usa o repositório como auxiliar, mas processa aqui
        $clinicalData = $this->clinicalRepo->getPatientClinicalDetails($attendanceNumber);
        $alerts = $this->clinicalRepo->getPatientActiveAlerts($attendanceNumber, $personId);
        
        return (object)[
            'diagnosticos_comorbidades' => $clinicalData->diagnosticos_comorbidades ?? '',
            'medida_bloqueio' => $clinicalData->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $clinicalData->motivos_isolamento ?? '',
            'avaliacao_enf' => $clinicalData->avaliacao_enf ?? '',
            'plano_educ' => $clinicalData->plano_educ ?? '',
            'pe_data' => $clinicalData->pe_data ?? '',
            'ds_queda' => $clinicalData->ds_queda ?? 'Não',
            'diag' => $clinicalData->diag ?? '',
            'dispositivos' => $clinicalData->dispositivos ?? '',
            'alergias_detalhadas' => $clinicalData->alergias_detalhadas ?? '',
            'materiais' => $clinicalData->materiais ?? '',
            'prioridade_exames' => $clinicalData->prioridade_exames ?? '',
            'procedimentos_cirurgicos' => $clinicalData->procedimentos_cirurgicos ?? [],
            'alerts' => $alerts
        ];
    }
    
    /**
     * Busca escalas de um paciente PROCESSADAS pelo ScaleService
     */
    private function fetchPatientScales($attendanceNumber)
    {
        // Busca dados brutos
        $rawScalesData = $this->scalesRepo->getPatientScales($attendanceNumber);
        
        if (!$rawScalesData) {
            return null;
        }
        
        // Processa com ScaleService - NOTE: idade não disponível aqui, assume adulto
        $processedScales = $this->scaleService->processScalesData($rawScalesData, false);
        
        return $processedScales;
    }
    
    /**
     * Busca dados CPOE detalhados de um paciente
     */
    private function fetchPatientCPOEDetails($attendanceNumber)
    {
        // Usa o repositório como auxiliar para cada tipo
        return (object)[
            'cpoe_procedures' => $this->cpoeRepo->getPatientCpoeProcedures($attendanceNumber),
            'cpoe_medications' => $this->cpoeRepo->getPatientMedications($attendanceNumber),
            'cpoe_nutrition' => $this->cpoeRepo->getPatientNutrition($attendanceNumber),
            'cpoe_recommendations' => $this->cpoeRepo->getPatientRecommendations($attendanceNumber),
            'cpoe_interventions' => $this->cpoeRepo->getPatientInterventions($attendanceNumber)
        ];
    }
    
    /**
     * Agrega todos os dados do paciente em um objeto único
     */
    private function aggregateFullPatientData($basicData, $clinicalDetails, $scalesData, $cpoeData)
    {
        return (object)array_merge(
            (array)$basicData,
            [
                // Flags clínicas
                'has_patient' => true,
                'is_occupied' => true,
                
                // Dados clínicos detalhados com valores padrão
                'diagnosticos_comorbidades' => $clinicalDetails->diagnosticos_comorbidades ?? 'Sem diagnósticos',
                'medida_bloqueio' => $clinicalDetails->medida_bloqueio ?? 'Não',
                'motivos_isolamento' => $clinicalDetails->motivos_isolamento ?? 'Nenhum motivo de isolamento',
                'avaliacao_enf' => $clinicalDetails->avaliacao_enf ?? 'Não realizada',
                'plano_educ' => $clinicalDetails->plano_educ ?? 'Não realizado',
                'pe_data' => $clinicalDetails->pe_data ?? 'Não realizado',
                'ds_queda' => $clinicalDetails->ds_queda ?? 'Não avaliado',
                'diag' => $clinicalDetails->diag ?? 'Sem diagnósticos SAE',
                'dispositivos' => $clinicalDetails->dispositivos ?? 'Nenhum dispositivo',
                'alergias_detalhadas' => $clinicalDetails->alergias_detalhadas ?? 'Sem alergias registradas',
                'materiais' => $clinicalDetails->materiais ?? 'Nenhum antimicrobiano',
                'prioridade_exames' => $clinicalDetails->prioridade_exames ?? 'Nenhum exame prioritário',
                'procedimentos_cirurgicos' => $clinicalDetails->procedimentos_cirurgicos ?? [],
                'alerts' => $clinicalDetails->alerts ?? [],
                
                // Escalas processadas pelo ScaleService com valores padrão
                'mews_score' => isset($scalesData['mews']) ? $scalesData['mews']['score'] : null,
                'mews_classification' => isset($scalesData['mews']) ? $scalesData['mews']['classification'] : null,
                'mews_timestamp' => isset($scalesData['mews']) ? $scalesData['mews']['timestamp'] : null,
                'mews_increased' => isset($scalesData['mews']) ? $scalesData['mews']['increased'] : false,
                'mews_needs_assessment' => isset($scalesData['mews']) ? $scalesData['mews']['needs_assessment'] : true,
                'mews_styling' => isset($scalesData['mews']) ? $scalesData['mews']['styling'] : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_mews' => isset($scalesData['mews']) && $scalesData['mews']['timestamp'] 
                    ? $scalesData['mews']['timestamp'] . ' - MEWS: ' . $scalesData['mews']['score']
                    : 'Sem avaliação nas últimas 24h',
                    
                'braden_score' => isset($scalesData['braden']) ? $scalesData['braden']['score'] : null,
                'braden_classification' => isset($scalesData['braden']) ? $scalesData['braden']['classification'] : null,
                'braden_timestamp' => isset($scalesData['braden']) ? $scalesData['braden']['timestamp'] : null,
                'braden_increased' => isset($scalesData['braden']) ? $scalesData['braden']['increased'] : false,
                'braden_needs_assessment' => isset($scalesData['braden']) ? $scalesData['braden']['needs_assessment'] : true,
                'braden_styling' => isset($scalesData['braden']) ? $scalesData['braden']['styling'] : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_braden' => isset($scalesData['braden']) && $scalesData['braden']['timestamp']
                    ? $scalesData['braden']['timestamp'] . ' - Braden: ' . $scalesData['braden']['score']
                    : 'Sem avaliação nas últimas 24h',
                    
                'morse_score' => isset($scalesData['morse']) ? $scalesData['morse']['score'] : null,
                'morse_classification' => isset($scalesData['morse']) ? $scalesData['morse']['classification'] : null,
                'morse_timestamp' => isset($scalesData['morse']) ? $scalesData['morse']['timestamp'] : null,
                'morse_increased' => isset($scalesData['morse']) ? $scalesData['morse']['increased'] : false,
                'morse_needs_assessment' => isset($scalesData['morse']) ? $scalesData['morse']['needs_assessment'] : true,
                'morse_styling' => isset($scalesData['morse']) ? $scalesData['morse']['styling'] : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_morse' => isset($scalesData['morse']) && $scalesData['morse']['timestamp']
                    ? $scalesData['morse']['timestamp'] . ' - Morse: ' . $scalesData['morse']['score']
                    : 'Sem avaliação nas últimas 24h',
                    
                'dor_score' => isset($scalesData['dor']) ? $scalesData['dor']['score'] : null,
                'dor_classification' => isset($scalesData['dor']) ? $scalesData['dor']['classification'] : null,
                'dor_timestamp' => isset($scalesData['dor']) ? $scalesData['dor']['timestamp'] : null,
                'dor_increased' => isset($scalesData['dor']) ? $scalesData['dor']['increased'] : false,
                'dor_needs_assessment' => isset($scalesData['dor']) ? $scalesData['dor']['needs_assessment'] : true,
                'dor_styling' => isset($scalesData['dor']) ? $scalesData['dor']['styling'] : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_dor' => isset($scalesData['dor']) && $scalesData['dor']['timestamp']
                    ? $scalesData['dor']['timestamp'] . ' - Dor: ' . $scalesData['dor']['score']
                    : 'Sem avaliação nas últimas 24h',
                    
                'tev_score' => isset($scalesData['tev']) ? $scalesData['tev']['score'] : null,
                'tev_classification' => isset($scalesData['tev']) ? $scalesData['tev']['classification'] : null,
                'tev_timestamp' => isset($scalesData['tev']) ? $scalesData['tev']['timestamp'] : null,
                'tev_increased' => isset($scalesData['tev']) ? $scalesData['tev']['increased'] : false,
                'tev_needs_assessment' => isset($scalesData['tev']) ? $scalesData['tev']['needs_assessment'] : true,
                'tev_styling' => isset($scalesData['tev']) ? $scalesData['tev']['styling'] : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_tev' => isset($scalesData['tev']) && $scalesData['tev']['timestamp']
                    ? $scalesData['tev']['timestamp'] . ' - TEV: ' . $scalesData['tev']['score']
                    : 'Sem avaliação nas últimas 24h',
                    
                'pews_score' => isset($scalesData['pews']) ? $scalesData['pews']['score'] : null,
                'pews_classification' => isset($scalesData['pews']) ? $scalesData['pews']['classification'] : null,
                'pews_timestamp' => isset($scalesData['pews']) ? $scalesData['pews']['timestamp'] : null,
                'pews_increased' => isset($scalesData['pews']) ? $scalesData['pews']['increased'] : false,
                'pews_needs_assessment' => isset($scalesData['pews']) ? $scalesData['pews']['needs_assessment'] : true,
                'pews_styling' => isset($scalesData['pews']) ? $scalesData['pews']['styling'] : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
                'ds_pews' => isset($scalesData['pews']) && $scalesData['pews']['timestamp']
                    ? $scalesData['pews']['timestamp'] . ' - PEWS: ' . $scalesData['pews']['score']
                    : 'Sem avaliação nas últimas 24h',
                
                // CPOE detalhado com valores padrão
                'cpoe_procedures' => $cpoeData->cpoe_procedures ?? 'Nenhum procedimento',
                'cpoe_medications' => $cpoeData->cpoe_medications ?? 'Nenhuma medicação',
                'cpoe_nutrition' => $cpoeData->cpoe_nutrition ?? 'Nenhuma dieta',
                'cpoe_recommendations' => $cpoeData->cpoe_recommendations ?? 'Nenhuma recomendação',
                'cpoe_interventions' => $cpoeData->cpoe_interventions ?? 'Nenhuma intervenção'
            ]
        );
    }
    
    /**
     * Formata idade detalhada com valores padrão
     */
    private function formatDetailedAge($years, $months, $days)
    {
        if ($years === null && $months === null && $days === null) {
            return 'Idade não informada';
        }
        
        $ageDetailed = intval($years ?? 0) . 'a';
        if (intval($months ?? 0) > 0) $ageDetailed .= ' ' . intval($months) . 'm';
        if (intval($days ?? 0) > 0) $ageDetailed .= ' ' . intval($days) . 'd';
        return $ageDetailed;
    }
    
    /**
     * Limpa cache de um setor específico
     */
    public function clearSectorCache($sectorId)
    {
        Cache::forget("sector_patients_sbar_{$sectorId}");
    }
    
    /**
     * Limpa cache de um paciente específico
     */
    public function clearPatientCache($attendanceNumber)
    {
        Cache::forget("patient_full_data_{$attendanceNumber}");
    }
}