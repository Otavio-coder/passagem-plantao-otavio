<?php

namespace App\Repositories\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PatientCPOE 
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    /**
     * Get CPOE pending status for multiple patients
     */
    public function getCpoePendingForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "cpoe_pending_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 120, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    pm.nr_atendimento,
                    COUNT(*) AS pending_count,
                    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_cpoe_pending
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                WHERE pm.nr_atendimento IN ({$placeholders})
                  AND TRUNC(pp.dt_prev_execucao) = TRUNC(SYSDATE)
                  AND pp.dt_baixa IS NULL
                  AND pm.dt_liberacao IS NOT NULL
                GROUP BY pm.nr_atendimento
            ", $attendanceNumbers);
            
            $cpoeData = [];
            foreach ($results as $result) {
                $cpoeData[$result->nr_atendimento] = [
                    'has_cpoe_pending' => (bool)$result->has_cpoe_pending,
                    'cpoe_pending_count' => intval($result->pending_count)
                ];
            }
            
            return $cpoeData;
        });
    }
    
    /**
     * Get detailed CPOE procedures for a patient organized by shifts
     */
    public function getPatientCpoeProcedures($attendanceNumber)
    {
        $cacheKey = "cpoe_procedures_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            $procedures = DB::connection('tasy')->select("
                SELECT
                    pm.nr_prescricao,
                    pp.nr_seq_proc_interno,
                    TASY.OBTER_DESC_PROC_INTERNO(pp.nr_seq_proc_interno) AS ds_procedimento,
                    pp.dt_prev_execucao,
                    pp.dt_baixa,
                    TO_CHAR(pp.dt_prev_execucao, 'DD/MM/YY') AS dt_prev_execucao_fmt,
                    TO_CHAR(pp.dt_prev_execucao, 'HH24:MI') AS hr_prev_execucao,
                    TO_CHAR(pp.dt_prev_execucao, 'DD/MM/YY HH24:MI:SS') AS dt_prev_execucao_full,
                    CASE 
                        WHEN TO_NUMBER(TO_CHAR(pp.dt_prev_execucao, 'HH24')) BETWEEN 6 AND 13 THEN 'MANHÃ'
                        WHEN TO_NUMBER(TO_CHAR(pp.dt_prev_execucao, 'HH24')) BETWEEN 14 AND 21 THEN 'TARDE'
                        ELSE 'NOITE'
                    END AS turno,
                    pm.dt_liberacao
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                WHERE pm.nr_atendimento = :attendance
                  AND TRUNC(pp.dt_prev_execucao) = TRUNC(SYSDATE)
                  AND pm.dt_liberacao IS NOT NULL
                ORDER BY pp.dt_prev_execucao
            ", ['attendance' => $attendanceNumber]);
            
            $proceduresByShift = collect($procedures)->groupBy('turno');
            
            $result = [
                'total_count' => count($procedures),
                'shifts' => []
            ];
            
            foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift) {
                $shiftProcedures = $proceduresByShift->get($shift, collect());
                
                $result['shifts'][$shift] = [
                    'count' => $shiftProcedures->count(),
                    'procedures' => $shiftProcedures->map(function ($procedure) {
                        return [
                            'procedimento' => $procedure->ds_procedimento ?? 'Procedimento não identificado',
                            'data_prevista' => $procedure->dt_prev_execucao_fmt,
                            'horario' => $procedure->hr_prev_execucao,
                            'horarios' => $procedure->dt_prev_execucao_full,
                            'periodo' => $procedure->dt_prev_execucao_full,
                            'turno' => $procedure->turno,
                            'nr_prescricao' => $procedure->nr_prescricao,
                            'nr_seq_proc_interno' => $procedure->nr_seq_proc_interno,
                            'is_completed' => !empty($procedure->dt_baixa),
                            'dt_baixa' => $procedure->dt_baixa ? 
                                Carbon::parse($procedure->dt_baixa)->format('d/m/Y H:i') : null,
                            'dt_liberacao' => $procedure->dt_liberacao
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }
    
    /**
     * Get CPOE procedures data for reporting
     */
    public function getCpoeProceduresData($sectorId = null, $attendanceNumber = null)
    {
        $query = "
            SELECT
                pm.nr_prescricao,
                pm.nr_atendimento,
                pp.nr_seq_proc_interno,
                TASY.OBTER_DESC_PROC_INTERNO(pp.nr_seq_proc_interno) AS DS_PROCEDIMENTO,
                pp.dt_prev_execucao,
                pp.dt_baixa,
                TO_CHAR(pp.dt_prev_execucao, 'DD/MM/YY') AS dt_prev_execucao_fmt,
                TO_CHAR(pp.dt_prev_execucao, 'HH24:MI') AS hr_prev_execucao,
                TO_CHAR(pp.dt_prev_execucao, 'DD/MM/YY HH24:MI:SS') AS dt_prev_execucao_full,
                CASE 
                    WHEN TO_NUMBER(TO_CHAR(pp.dt_prev_execucao, 'HH24')) BETWEEN 6 AND 13 THEN 'MANHÃ'
                    WHEN TO_NUMBER(TO_CHAR(pp.dt_prev_execucao, 'HH24')) BETWEEN 14 AND 21 THEN 'TARDE'
                    ELSE 'NOITE'
                END AS turno
            FROM
                tasy.prescr_medica pm,
                tasy.prescr_procedimento pp
            WHERE
                pm.nr_prescricao = pp.nr_prescricao
                AND TRUNC(pp.dt_prev_execucao) = TRUNC(SYSDATE)
        ";
        
        $params = [];
        
        if ($attendanceNumber) {
            $query .= " AND pm.nr_atendimento = :attendance";
            $params['attendance'] = $attendanceNumber;
        } else if ($sectorId) {
            $query .= " AND pm.nr_atendimento IN (
                SELECT ua.nr_atendimento 
                FROM tasy.unidade_atendimento ua 
                WHERE ua.cd_setor_atendimento = :sector_id 
                AND ua.ie_situacao = 'A'
            )";
            $params['sector_id'] = $sectorId;
        }
        
        $query .= " ORDER BY pm.nr_atendimento, pp.dt_prev_execucao";
        
        return collect(DB::connection('tasy')->select($query, $params));
    }
    
    /**
     * Get detailed medication prescriptions for a patient organized by shifts
     */
    public function getPatientMedications($attendanceNumber)
    {
        $cacheKey = "cpoe_medications_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            $medications = DB::connection('tasy')->select("
                WITH base_prescricoes AS (
                  SELECT
                    pmh.nr_prescricao,
                    pmed.nr_atendimento,
                    pmh.nr_sequencia,
                    pmh.nr_seq_material,
                    pmh.nr_ocorrencia,
                    pmt.cd_material,
                    TASY.OBTER_DESC_MATERIAL(pmt.cd_material) AS ds_material,
                    pmt.qt_dose,
                    pmh.cd_unidade_medida_dose,
                    pmt.cd_unidade_medida,
                    TASY.OBTER_VIA_APLICACAO(pmt.ie_via_aplicacao,'D') AS via_aplicacao,
                    pmh.dt_horario,
                    pmh.ds_horario,
                    pmt.dt_baixa,
                    pmt.dt_suspensao AS dt_suspensao_material,
                    pmh.dt_suspensao AS dt_suspensao_horario,
                    pmh.dt_fim_horario,
                    pmt.ie_tipo_medic_hd,
                    pmt.ie_suspenso,
                    pmh.qt_dispensar,
                    pmh.qt_dispensar_hor,
                    pma.ie_alteracao,
                    pma.dt_alteracao,
                    CASE
                      WHEN pma.dt_alteracao IS NULL OR pma.ie_alteracao = 38 THEN (
                        SELECT 'Dev.: ' || SUM(idm.qt_material)
                        FROM tasy.devolucao_material_pac dmp
                        JOIN tasy.item_devolucao_material_pac idm
                          ON dmp.nr_devolucao = idm.nr_devolucao
                         AND idm.nr_prescricao = pmed.nr_prescricao
                         AND idm.nr_sequencia_prescricao = pmt.nr_sequencia
                      ) ELSE NULL
                    END AS devolucao,
                    pmed.dt_prescricao,
                    pmed.dt_validade_prescr,
                    CASE
                      WHEN TO_CHAR(NVL(pma.dt_horario, pmh.dt_horario), 'HH24:MI')
                           BETWEEN '07:05' AND '13:04' THEN 'MANHÃ'
                      WHEN TO_CHAR(NVL(pma.dt_horario, pmh.dt_horario), 'HH24:MI')
                           BETWEEN '13:05' AND '19:04' THEN 'TARDE'
                      ELSE 'NOITE'
                    END AS ie_turno,
                    TASY.OBTER_FUNCAO_USUARIO_PRESCR(pmed.nr_prescricao) AS funcao_prescritor,
                    substr(tasy.obter_nome_pf(pma.cd_pessoa_fisica),1,60) AS profissional,
                    substr(
                      nvl(
                        tasy.obter_valor_dominio(1620, pma.ie_alteracao),
                        'Sem Evento'
                      ),1,100
                    ) AS evento
                  FROM
                    tasy.prescr_medica pmed
                    JOIN tasy.prescr_mat_hor pmh ON pmh.nr_prescricao = pmed.nr_prescricao
                    LEFT JOIN tasy.prescr_mat_alteracao pma ON pma.nr_seq_horario = pmh.nr_sequencia
                         AND pma.nr_prescricao = pmh.nr_prescricao
                    JOIN tasy.prescr_material pmt ON pmt.nr_prescricao = pmh.nr_prescricao
                         AND pmt.nr_sequencia = pmh.nr_seq_material
                  WHERE
                    pmed.nr_atendimento = :attendance
                    AND pmed.cd_estabelecimento = 1
                    AND NVL(pmt.ie_administrar,'S') = 'S'
                    AND pmt.ie_suspenso <> 'S'
                    AND pmt.ie_se_necessario <> 'S'
                    AND pmt.ie_acm <> 'S'
                    AND pmt.ie_tipo_medic_hd = 'D'
                    AND pmt.nr_seq_recomendacao IS NULL
                    AND pmt.nr_sequencia_solucao IS NULL
                    AND pmt.nr_sequencia_proc IS NULL
                    AND pmt.nr_sequencia_diluicao IS NULL
                    AND pmt.nr_sequencia_dieta IS NULL
                    AND pmt.cd_material NOT IN (51666,51655,51656,52440,51669)
                    AND pmed.dt_liberacao IS NOT NULL
                    AND pmed.dt_suspensao IS NULL
                    AND pmh.dt_horario >= TRUNC(SYSDATE)
                    AND pmh.dt_horario < TRUNC(SYSDATE) + 1
                ),
                prescricoes_ordenadas AS (
                  SELECT
                    bp.*,
                    ROW_NUMBER() OVER (
                      PARTITION BY bp.nr_prescricao
                      ORDER BY bp.dt_horario, bp.nr_seq_material, bp.nr_ocorrencia
                    ) AS rn,
                    ROW_NUMBER() OVER (
                      ORDER BY bp.dt_horario, bp.nr_prescricao,
                               bp.nr_seq_material, bp.nr_ocorrencia
                    ) AS sort_order_,
                    CASE
                      WHEN ROW_NUMBER() OVER (
                             PARTITION BY bp.nr_prescricao
                             ORDER BY bp.dt_horario, bp.nr_seq_material, bp.nr_ocorrencia
                           ) = 1
                      THEN '[ ' || TO_CHAR(bp.dt_horario,'dd/mm/yyyy HH24:MI') || ' ] '
                           || 'Med: ' || bp.ds_material
                           || ' | Dose: ' || bp.qt_dose || bp.cd_unidade_medida_dose
                           || ' | Via: ' || bp.via_aplicacao
                           || ' | Disp: ' || NVL(TO_CHAR(bp.qt_dispensar),'0')
                                       || '/' || NVL(TO_CHAR(bp.qt_dispensar_hor),'0')
                      ELSE '    -- continuidade -> [ ' || TO_CHAR(bp.dt_horario,'dd/mm/yyyy HH24:MI') || ' ] '
                           || 'Med: ' || bp.ds_material
                           || ' | Dose: ' || bp.qt_dose || bp.cd_unidade_medida_dose
                           || ' | Via: ' || bp.via_aplicacao
                           || ' | Disp: ' || NVL(TO_CHAR(bp.qt_dispensar),'0')
                                       || '/' || NVL(TO_CHAR(bp.qt_dispensar_hor),'0')
                    END AS ds_resumo_formatado
                  FROM base_prescricoes bp
                )
                SELECT * FROM prescricoes_ordenadas ORDER BY sort_order_
            ", ['attendance' => $attendanceNumber]);
            
            $medicationsByShift = collect($medications)->groupBy('ie_turno');
            
            $result = [
                'total_count' => count($medications),
                'shifts' => []
            ];
            
            foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift) {
                $shiftMedications = $medicationsByShift->get($shift, collect());
                
                $result['shifts'][$shift] = [
                    'count' => $shiftMedications->count(),
                    'medications' => $shiftMedications->map(function ($medication) {
                        return [
                            'medicamento' => $medication->ds_material ?? 'Medicamento não identificado',
                            'dose' => $medication->qt_dose . ' ' . ($medication->cd_unidade_medida_dose ?? ''),
                            'via_aplicacao' => $medication->via_aplicacao ?? 'Via não informada',
                            'horario' => $medication->ds_horario,
                            'dt_horario' => $medication->dt_horario,
                            'dispensar' => ($medication->qt_dispensar ?? 0) . '/' . ($medication->qt_dispensar_hor ?? 0),
                            'turno' => $medication->ie_turno,
                            'nr_prescricao' => $medication->nr_prescricao,
                            'nr_sequencia' => $medication->nr_sequencia,
                            'cd_material' => $medication->cd_material,
                            'is_administered' => !empty($medication->dt_baixa),
                            'is_suspended' => !empty($medication->dt_suspensao_material) || !empty($medication->dt_suspensao_horario),
                            'devolucao' => $medication->devolucao,
                            'funcao_prescritor' => $medication->funcao_prescritor,
                            'profissional' => $medication->profissional,
                            'evento' => $medication->evento,
                            'resumo_formatado' => $medication->ds_resumo_formatado,
                            'dt_prescricao' => $medication->dt_prescricao,
                            'dt_validade_prescr' => $medication->dt_validade_prescr,
                            'ie_tipo_medic_hd' => $medication->ie_tipo_medic_hd,
                            'has_details' => true // Flag para mostrar botão de detalhes
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }
    
    /**
     * Get nutrition prescriptions for a patient organized by shifts
     */
    public function getPatientNutrition($attendanceNumber)
    {
        $cacheKey = "cpoe_nutrition_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            $nutrition = DB::connection('tasy')->select("
                WITH base_prescricoes AS (
                  SELECT
                    pmh.nr_prescricao,
                    pmed.nr_atendimento,
                    -- quem prescreveu
                    TASY.OBTER_FUNCAO_USUARIO_PRESCR(pmed.nr_prescricao) AS tipo_prescritor,
                    pmh.nr_sequencia,
                    pmh.nr_seq_material,
                    pmh.nr_ocorrencia,
                    pmt.cd_material,
                    -- descrição direta, sem lateral
                    TASY.OBTER_DESC_MATERIAL(pmt.cd_material) AS ds_material,
                    pmt.qt_dose,
                    pmh.cd_unidade_medida_dose,
                    pmt.cd_unidade_medida,
                    -- via direta
                    TASY.OBTER_VIA_APLICACAO(pmt.ie_via_aplicacao,'D') AS via_aplicacao,
                    pmh.dt_horario,
                    pmh.ds_horario,
                    pmt.dt_baixa,
                    pmt.dt_suspensao AS dt_suspensao_material,
                    pmh.dt_suspensao AS dt_suspensao_horario,
                    pmt.ie_suspenso,
                    pmh.qt_dispensar,
                    pmh.qt_dispensar_hor,
                    -- devolução (se houver)
                    CASE
                      WHEN pma.dt_alteracao IS NULL OR pma.ie_alteracao = 38 THEN (
                        SELECT 'Dev.: '||SUM(idm.qt_material)
                        FROM tasy.devolucao_material_pac dmp
                        JOIN tasy.item_devolucao_material_pac idm
                          ON dmp.nr_devolucao = idm.nr_devolucao
                         AND idm.nr_prescricao           = pmed.nr_prescricao
                         AND idm.nr_sequencia_prescricao = pmt.nr_sequencia
                      ) ELSE NULL
                    END AS devolucao,
                    pmed.dt_prescricao,
                    pmed.dt_liberacao,
                    -- turno
                    CASE
                      WHEN TO_CHAR(pmh.dt_horario,'HH24:MI') BETWEEN '07:05' AND '13:04' THEN 'MANHÃ'
                      WHEN TO_CHAR(pmh.dt_horario,'HH24:MI') BETWEEN '13:05' AND '19:04' THEN 'TARDE'
                      ELSE 'NOITE'
                    END AS ie_turno
                  FROM
                    tasy.prescr_medica        pmed
                    JOIN tasy.prescr_mat_hor   pmh
                      ON pmh.nr_prescricao = pmed.nr_prescricao
                    LEFT JOIN tasy.prescr_mat_alteracao pma
                      ON pma.nr_prescricao  = pmh.nr_prescricao
                     AND pma.nr_seq_horario = pmh.nr_sequencia
                    JOIN tasy.prescr_material pmt
                      ON pmt.nr_prescricao = pmh.nr_prescricao
                     AND pmt.nr_sequencia  = pmh.nr_seq_material
                  WHERE
                    pmed.nr_atendimento        = :attendance
                    AND pmed.cd_estabelecimento = 1
                    AND NVL(pmt.ie_administrar,'S') = 'S'
                    AND pmt.ie_suspenso            <> 'S'
                    AND pmed.dt_liberacao IS NOT NULL
                    AND pmed.dt_suspensao IS NULL
                    AND TRUNC(pmed.dt_prescricao) = TRUNC(SYSDATE)
                    AND UPPER(TASY.OBTER_FUNCAO_USUARIO_PRESCR(pmed.nr_prescricao)) LIKE '%NUTRICIONISTA%'
                    -- apenas hoje
                    AND pmh.dt_horario >= TRUNC(SYSDATE)
                    AND pmh.dt_horario <  TRUNC(SYSDATE) + 1
                ),
                prescricoes_ordenadas AS (
                  SELECT
                    bp.*,
                    ROW_NUMBER() OVER (
                      PARTITION BY bp.nr_prescricao
                      ORDER BY bp.dt_horario, bp.nr_seq_material, bp.nr_ocorrencia
                    ) AS rn,
                    ROW_NUMBER() OVER (
                      ORDER BY bp.dt_horario, bp.nr_prescricao,
                               bp.nr_seq_material, bp.nr_ocorrencia
                    ) AS sort_order_,
                    CASE
                      WHEN ROW_NUMBER() OVER (
                             PARTITION BY bp.nr_prescricao
                             ORDER BY bp.dt_horario, bp.nr_seq_material, bp.nr_ocorrencia
                           ) = 1
                      THEN '[ '||TO_CHAR(bp.dt_horario,'dd/mm/yyyy HH24:MI')||' ] '
                           || 'Alimento: '||bp.ds_material
                           || ' | Dose: '||bp.qt_dose||bp.cd_unidade_medida_dose
                           || ' | Via: '||bp.via_aplicacao
                           || ' | Disp: '||NVL(TO_CHAR(bp.qt_dispensar),'0')
                                       || '/'||NVL(TO_CHAR(bp.qt_dispensar_hor),'0')
                      ELSE '    -- continuidade -> [ '||TO_CHAR(bp.dt_horario,'dd/mm/yyyy HH24:MI')||' ] '
                           || 'Alimento: '||bp.ds_material
                           || ' | Dose: '||bp.qt_dose||bp.cd_unidade_medida_dose
                           || ' | Via: '||bp.via_aplicacao
                           || ' | Disp: '||NVL(TO_CHAR(bp.qt_dispensar),'0')
                                       || '/'||NVL(TO_CHAR(bp.qt_dispensar_hor),'0')
                    END AS ds_resumo_formatado
                  FROM base_prescricoes bp
                )
                SELECT * FROM prescricoes_ordenadas ORDER BY sort_order_
            ", ['attendance' => $attendanceNumber]);
            
            $nutritionByShift = collect($nutrition)->groupBy('ie_turno');
            
            $result = [
                'total_count' => count($nutrition),
                'shifts' => []
            ];
            
            foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift) {
                $shiftNutrition = $nutritionByShift->get($shift, collect());
                
                $result['shifts'][$shift] = [
                    'count' => $shiftNutrition->count(),
                    'prescriptions' => $shiftNutrition->map(function ($item) {
                        return [
                            'prescricao' => $item->ds_material ?? 'Prescrição nutricional',
                            'observacoes' => $item->devolucao, // Usando devolução como observação
                            'tipo_nutricao' => $item->via_aplicacao, // Usando via como tipo
                            'data_inicio' => $item->dt_horario ? Carbon::parse($item->dt_horario)->format('d/m/Y') : null,
                            'horario_inicio' => $item->ds_horario,
                            'data_fim' => null, // Não disponível na estrutura atual
                            'horario_fim' => null,
                            'turno' => $item->ie_turno,
                            'nr_prescricao' => $item->nr_prescricao,
                            'nr_sequencia' => $item->nr_sequencia,
                            'cd_material' => $item->cd_material,
                            'dose' => $item->qt_dose . ' ' . ($item->cd_unidade_medida_dose ?? ''),
                            'dispensar' => ($item->qt_dispensar ?? 0) . '/' . ($item->qt_dispensar_hor ?? 0),
                            'funcao_prescritor' => $item->tipo_prescritor,
                            'nome_prescritor' => null, // Não disponível na consulta atual
                            'dt_prescricao' => $item->dt_prescricao,
                            'dt_liberacao' => $item->dt_liberacao,
                            'is_active' => empty($item->dt_suspensao_material) && empty($item->dt_suspensao_horario),
                            'is_administered' => !empty($item->dt_baixa),
                            'periodo_completo' => $item->dt_horario ? Carbon::parse($item->dt_horario)->format('d/m/Y H:i') : '',
                            'resumo_formatado' => $item->ds_resumo_formatado,
                            'has_details' => !empty($item->devolucao) || !empty($item->tipo_prescritor)
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }
    
    /**
     * Get lightweight nutrition summary for multiple patients
     */
    public function getNutritionSummaryForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "nutrition_summary_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 120, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    pmed.nr_atendimento,
                    COUNT(DISTINCT pmt.cd_material) AS total_nutrition_prescriptions,
                    COUNT(pmh.nr_sequencia) AS total_administrations,
                    COUNT(CASE WHEN pmt.dt_baixa IS NULL AND pmt.dt_suspensao IS NULL THEN 1 END) AS active_prescriptions,
                    COUNT(CASE WHEN pmt.dt_baixa IS NOT NULL OR pmt.dt_suspensao IS NOT NULL THEN 1 END) AS inactive_prescriptions
                FROM tasy.prescr_medica pmed
                JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
                JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao 
                    AND pmt.nr_sequencia = pmh.nr_seq_material
                WHERE pmed.nr_atendimento IN ({$placeholders})
                  AND pmed.dt_liberacao IS NOT NULL
                  AND pmed.dt_suspensao IS NULL
                  AND pmt.ie_suspenso <> 'S'
                  AND NVL(pmt.ie_administrar,'S') = 'S'
                  AND TRUNC(pmed.dt_prescricao) = TRUNC(SYSDATE)
                  AND UPPER(TASY.OBTER_FUNCAO_USUARIO_PRESCR(pmed.nr_prescricao)) LIKE '%NUTRICIONISTA%'
                  AND pmh.dt_horario >= TRUNC(SYSDATE)
                  AND pmh.dt_horario < TRUNC(SYSDATE) + 1
                GROUP BY pmed.nr_atendimento
            ", $attendanceNumbers);
            
            $nutritionData = [];
            foreach ($results as $result) {
                $nutritionData[$result->nr_atendimento] = [
                    'total_nutrition_prescriptions' => intval($result->total_nutrition_prescriptions),
                    'active_prescriptions' => intval($result->active_prescriptions),
                    'inactive_prescriptions' => intval($result->inactive_prescriptions),
                    'has_nutrition' => intval($result->total_nutrition_prescriptions) > 0
                ];
            }
            
            return $nutritionData;
        });
    }

    /**
     * Get lightweight medication summary for multiple patients
     */
    public function getMedicationSummaryForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "medication_summary_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 120, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    pmed.nr_atendimento,
                    COUNT(DISTINCT pmt.cd_material) AS total_medications,
                    COUNT(pmh.nr_sequencia) AS total_administrations,
                    COUNT(CASE WHEN pmt.dt_baixa IS NULL THEN 1 END) AS pending_administrations,
                    COUNT(CASE WHEN pmt.dt_baixa IS NOT NULL THEN 1 END) AS completed_administrations
                FROM tasy.prescr_medica pmed
                JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
                JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao 
                    AND pmt.nr_sequencia = pmh.nr_seq_material
                WHERE pmed.nr_atendimento IN ({$placeholders})
                  AND pmed.dt_liberacao IS NOT NULL
                  AND pmed.dt_suspensao IS NULL
                  AND pmt.ie_suspenso <> 'S'
                  AND pmt.ie_tipo_medic_hd = 'D'
                  AND NVL(pmt.ie_administrar,'S') = 'S'
                  AND pmh.dt_horario >= TRUNC(SYSDATE)
                  AND pmh.dt_horario < TRUNC(SYSDATE) + 1
                GROUP BY pmed.nr_atendimento
            ", $attendanceNumbers);
            
            $medicationData = [];
            foreach ($results as $result) {
                $medicationData[$result->nr_atendimento] = [
                    'total_medications' => intval($result->total_medications),
                    'total_administrations' => intval($result->total_administrations),
                    'pending_administrations' => intval($result->pending_administrations),
                    'completed_administrations' => intval($result->completed_administrations),
                    'has_medications' => intval($result->total_medications) > 0
                ];
            }
            
            return $medicationData;
        });
    }

    /**
     * Get unified CPOE pending counts for multiple patients (procedures + medications + nutrition)
     * FIXED: Garantir filtro apenas para HOJE
     */
    public function getUnifiedCpoePendingForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "unified_cpoe_pending_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 120, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            // Get pending procedures - HOJE APENAS
            $pendingProcedures = DB::connection('tasy')->select("
                SELECT 
                    pm.nr_atendimento,
                    COUNT(*) AS pending_procedures
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                WHERE pm.nr_atendimento IN ({$placeholders})
                AND TRUNC(pp.dt_prev_execucao) = TRUNC(SYSDATE)
                AND pp.dt_baixa IS NULL
                AND pm.dt_liberacao IS NOT NULL
                AND pm.dt_suspensao IS NULL
                GROUP BY pm.nr_atendimento
            ", $attendanceNumbers);
            
            // Get pending medications - HOJE APENAS
            $pendingMedications = DB::connection('tasy')->select("
                SELECT 
                    pmed.nr_atendimento,
                    COUNT(pmh.nr_sequencia) AS pending_medications
                FROM tasy.prescr_medica pmed
                JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
                JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao 
                    AND pmt.nr_sequencia = pmh.nr_seq_material
                WHERE pmed.nr_atendimento IN ({$placeholders})
                AND pmed.dt_liberacao IS NOT NULL
                AND pmed.dt_suspensao IS NULL
                AND pmt.ie_suspenso <> 'S'
                AND pmt.ie_tipo_medic_hd = 'D'
                AND NVL(pmt.ie_administrar,'S') = 'S'
                AND pmt.dt_baixa IS NULL
                AND pmh.dt_horario >= TRUNC(SYSDATE)
                AND pmh.dt_horario < TRUNC(SYSDATE) + 1
                GROUP BY pmed.nr_atendimento
            ", $attendanceNumbers);
            
            // Get pending nutrition prescriptions - HOJE APENAS
            $pendingNutrition = DB::connection('tasy')->select("
                SELECT 
                    pmed.nr_atendimento,
                    COUNT(pmh.nr_sequencia) AS pending_nutrition
                FROM tasy.prescr_medica pmed
                JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
                JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao 
                    AND pmt.nr_sequencia = pmh.nr_seq_material
                WHERE pmed.nr_atendimento IN ({$placeholders})
                AND pmed.dt_liberacao IS NOT NULL
                AND pmed.dt_suspensao IS NULL
                AND pmt.ie_suspenso <> 'S'
                AND NVL(pmt.ie_administrar,'S') = 'S'
                AND pmt.dt_baixa IS NULL
                AND pmt.dt_suspensao IS NULL
                AND TRUNC(pmed.dt_prescricao) = TRUNC(SYSDATE)
                AND UPPER(TASY.OBTER_FUNCAO_USUARIO_PRESCR(pmed.nr_prescricao)) LIKE '%NUTRICIONISTA%'
                AND pmh.dt_horario >= TRUNC(SYSDATE)
                AND pmh.dt_horario < TRUNC(SYSDATE) + 1
                GROUP BY pmed.nr_atendimento
            ", $attendanceNumbers);
            
            // Consolidate results
            $cpoeData = [];
            
            // Initialize with zeros
            foreach ($attendanceNumbers as $attendance) {
                $cpoeData[$attendance] = [
                    'has_cpoe_pending' => false,
                    'cpoe_pending_count' => 0,
                    'pending_procedures' => 0,
                    'pending_medications' => 0,
                    'pending_nutrition' => 0
                ];
            }
            
            // Add procedure counts
            foreach ($pendingProcedures as $result) {
                $cpoeData[$result->nr_atendimento]['pending_procedures'] = intval($result->pending_procedures);
            }
            
            // Add medication counts
            foreach ($pendingMedications as $result) {
                $cpoeData[$result->nr_atendimento]['pending_medications'] = intval($result->pending_medications);
            }
            
            // Add nutrition counts
            foreach ($pendingNutrition as $result) {
                $cpoeData[$result->nr_atendimento]['pending_nutrition'] = intval($result->pending_nutrition);
            }
            
            // Calculate totals
            foreach ($cpoeData as $attendance => $data) {
                $totalPending = $data['pending_procedures'] + $data['pending_medications'] + $data['pending_nutrition'];
                $cpoeData[$attendance]['cpoe_pending_count'] = $totalPending;
                $cpoeData[$attendance]['has_cpoe_pending'] = $totalPending > 0;
            }
            
            return $cpoeData;
        });
    }
}
