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
                        WHEN TO_NUMBER(SUBSTR(TO_CHAR(pp.dt_prev_execucao, 'HH24:MI'), 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                        WHEN TO_NUMBER(SUBSTR(TO_CHAR(pp.dt_prev_execucao, 'HH24:MI'), 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
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
                    WHEN TO_NUMBER(SUBSTR(TO_CHAR(pp.dt_prev_execucao, 'HH24:MI'), 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                    WHEN TO_NUMBER(SUBSTR(TO_CHAR(pp.dt_prev_execucao, 'HH24:MI'), 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
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
     * Get detailed medication prescriptions for a patient organized by shifts - OPTIMIZED LABELS
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
                    pmh.qt_dispensar,
                    pmh.qt_dispensar_hor,
                    pmed.dt_prescricao,
                    pmed.dt_validade_prescr,
                    -- Colunas de filtros removidos (agora como labels)
                    CASE WHEN pmt.ie_tipo_medic_hd = 'D' THEN 1 ELSE 0 END AS is_tipo_d,
                    CASE WHEN pmt.ie_se_necessario = 'S' THEN 1 ELSE 0 END AS is_se_necessario,
                    CASE WHEN pmt.ie_acm = 'S' THEN 1 ELSE 0 END AS is_acm,
                    CASE WHEN pmt.nr_seq_recomendacao IS NOT NULL THEN 1 ELSE 0 END AS is_recomendacao,
                    CASE WHEN pmt.nr_sequencia_solucao IS NOT NULL THEN 1 ELSE 0 END AS is_solucao,
                    CASE WHEN pmt.nr_sequencia_proc IS NOT NULL THEN 1 ELSE 0 END AS is_procedimento,
                    CASE WHEN pmt.nr_sequencia_diluicao IS NOT NULL THEN 1 ELSE 0 END AS is_diluicao,
                    CASE WHEN pmt.nr_sequencia_dieta IS NOT NULL THEN 1 ELSE 0 END AS is_dieta,
                    CASE WHEN pmt.cd_material IN (51666,51655,51656,52440,51669) THEN 1 ELSE 0 END AS is_material_excluido,
                    -- Turno calculation
                    CASE
                        WHEN TO_NUMBER(SUBSTR(TO_CHAR(pmh.dt_horario, 'HH24:MI'), 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                        WHEN TO_NUMBER(SUBSTR(TO_CHAR(pmh.dt_horario, 'HH24:MI'), 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                        ELSE 'NOITE'
                    END AS ie_turno
                  FROM
                    tasy.prescr_medica pmed
                    JOIN tasy.prescr_mat_hor pmh ON pmh.nr_prescricao = pmed.nr_prescricao
                    JOIN tasy.prescr_material pmt ON pmt.nr_prescricao = pmh.nr_prescricao
                        AND pmt.nr_sequencia = pmh.nr_seq_material
                  WHERE
                    pmed.nr_atendimento = :attendance
                    AND pmed.cd_estabelecimento = 1
                    AND NVL(pmt.ie_administrar,'S') = 'S'
                    AND pmt.ie_suspenso <> 'S'
                    AND pmed.dt_liberacao IS NOT NULL
                    AND pmed.dt_suspensao IS NULL
                    AND pmh.dt_horario >= TRUNC(SYSDATE)
                    AND pmh.dt_horario < TRUNC(SYSDATE) + 1
                ),
                prescricoes_ordenadas AS (
                  SELECT
                    bp.*,
                    ROW_NUMBER() OVER (
                      PARTITION BY bp.nr_prescricao, bp.nr_seq_material
                      ORDER BY bp.dt_horario, bp.nr_ocorrencia
                    ) AS rn,
                    ROW_NUMBER() OVER (
                      ORDER BY bp.dt_horario, bp.nr_prescricao, bp.nr_seq_material, bp.nr_ocorrencia
                    ) AS sort_order_,
                    -- Format prescription summary with all possible labels
                    '[ ' || TO_CHAR(bp.dt_horario,'dd/mm/yyyy HH24:MI') || ' ] '
                    || 'Med: ' || bp.ds_material
                    || ' | Dose: ' || bp.qt_dose || NVL(bp.cd_unidade_medida_dose, '')
                    || ' | Via: ' || NVL(bp.via_aplicacao, 'NI')
                    || ' | Disp: ' || NVL(TO_CHAR(bp.qt_dispensar),'0') || '/' || NVL(TO_CHAR(bp.qt_dispensar_hor),'0')
                    || CASE WHEN bp.is_se_necessario = 1 THEN ' | SE NECESSÁRIO' ELSE '' END
                    || CASE WHEN bp.is_acm = 1 THEN ' | ACM' ELSE '' END
                    || CASE WHEN bp.is_recomendacao = 1 THEN ' | RECOMENDAÇÃO' ELSE '' END
                    || CASE WHEN bp.is_solucao = 1 THEN ' | SOLUÇÃO' ELSE '' END
                    || CASE WHEN bp.is_procedimento = 1 THEN ' | PROCEDIMENTO' ELSE '' END
                    || CASE WHEN bp.is_diluicao = 1 THEN ' | DILUIÇÃO' ELSE '' END
                    || CASE WHEN bp.is_dieta = 1 THEN ' | DIETA' ELSE '' END
                    AS ds_resumo_formatado
                  FROM base_prescricoes bp
                )
                SELECT * FROM prescricoes_ordenadas 
                WHERE is_tipo_d = 1 
                  AND is_se_necessario = 0 
                  AND is_acm = 0 
                  AND is_recomendacao = 0 
                  AND is_solucao = 0 
                  AND is_procedimento = 0 
                  AND is_diluicao = 0 
                  AND is_dieta = 0 
                  AND is_material_excluido = 0
                ORDER BY sort_order_
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
                        // Generate dynamic labels based on filter columns
                        $labels = [];
                        if ($medication->is_se_necessario) $labels[] = 'Se Necessário';
                        if ($medication->is_acm) $labels[] = 'ACM';
                        if ($medication->is_recomendacao) $labels[] = 'Recomendação';
                        if ($medication->is_solucao) $labels[] = 'Solução';
                        if ($medication->is_procedimento) $labels[] = 'Procedimento';
                        if ($medication->is_diluicao) $labels[] = 'Diluição';
                        if ($medication->is_dieta) $labels[] = 'Dieta';
                        
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
                            'resumo_formatado' => $medication->ds_resumo_formatado,
                            'dt_prescricao' => $medication->dt_prescricao,
                            'dt_validade_prescr' => $medication->dt_validade_prescr,
                            'labels' => $labels, // New field with dynamic labels
                            'has_details' => true
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }
    
    /**
     * Get nutrition prescriptions for a patient organized by shifts - FIXED TIPO_NUTRICAO
     */
    public function getPatientNutrition($attendanceNumber)
    {
        $cacheKey = "cpoe_nutrition_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            $nutrition = DB::connection('tasy')->select("
                WITH base_dietas AS (
                  SELECT
                    cd.NR_SEQUENCIA,
                    cd.NR_ATENDIMENTO,
                    cd.CD_DIETA,
                    d.DS_DIETA,
                    cd.CD_MATERIAL,
                    TASY.OBTER_DESC_MATERIAL(cd.CD_MATERIAL) AS DS_MATERIAL,
                    cd.QT_DOSE,
                    cd.CD_UNIDADE_MEDIDA_DOSE,
                    cd.QT_VOLUME,
                    cd.QT_VOLUME_TOTAL,
                    cd.DS_HORARIOS,
                    cd.HR_PRIM_HORARIO,
                    cd.DT_INICIO,
                    cd.DT_FIM,
                    cd.IE_TIPO_DIETA,
                    cd.DS_ORIENTACAO,
                    cd.DS_OBSERVACAO,
                    cd.IE_DIETA_ENTERAL,
                    cd.IE_DIETA_ESPECIAL,
                    cd.QT_KCAL_NUTRI,
                    cd.QT_KCAL_TOTAL,
                    cd.DS_ALERGIAS_ALIMENTARES,
                    cd.DT_LIBERACAO,
                    cd.DT_SUSPENSAO,
                    cd.CD_PESSOA_FISICA,
                    cd.NR_SEQ_OBJETIVO,
                    cd.NR_SEQ_TIPO,
                    obj.DS_OBJETIVO AS DS_OBJETIVO_JEJUM,
                    tj.DS_TIPO AS DS_TIPO_JEJUM,
                    cd.DT_INICIO_JEJUM,
                    cd.DT_FIM_JEJUM,
                    cd.IE_JEJUM,
                    cd.IE_JEJUM_ANTES,
                    cd.IE_JEJUM_DEPOIS,
                    TASY.OBTER_NOME_PF(cd.CD_PESSOA_FISICA) AS NOME_NUTRICIONISTA,
                    CASE
                        WHEN cd.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                        WHEN cd.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                        ELSE 'NOITE'
                    END AS IE_TURNO
                  FROM
                    tasy.cpoe_dieta cd
                    LEFT JOIN tasy.dieta d ON cd.CD_DIETA = d.CD_DIETA
                    LEFT JOIN tasy.REP_OBJETIVO_JEJUM obj ON cd.NR_SEQ_OBJETIVO = obj.NR_SEQUENCIA
                    LEFT JOIN tasy.REP_TIPO_JEJUM tj ON cd.NR_SEQ_TIPO = tj.NR_SEQUENCIA
                  WHERE
                    cd.NR_ATENDIMENTO = :attendance
                    AND cd.DT_LIBERACAO IS NOT NULL
                    AND (
                      TRUNC(SYSDATE) BETWEEN TRUNC(cd.DT_INICIO) AND NVL(TRUNC(cd.DT_FIM), TRUNC(SYSDATE))
                    )
                    AND cd.DT_SUSPENSAO IS NULL
                ),
                dietas_ordenadas AS (
                  SELECT
                    bd.*,
                    ROW_NUMBER() OVER (
                      ORDER BY bd.DT_INICIO, bd.NR_SEQUENCIA
                    ) AS sort_order_,
                    CASE
                      WHEN bd.IE_TIPO_DIETA = 'J' THEN
                        '[ ' || TO_CHAR(bd.DT_INICIO, 'dd/mm/yyyy') || 
                        CASE WHEN bd.HR_PRIM_HORARIO IS NOT NULL THEN ' ' || bd.HR_PRIM_HORARIO ELSE '' END || 
                        ' ] JEJUM' || 
                        CASE WHEN bd.DS_TIPO_JEJUM IS NOT NULL THEN ' | Tipo: ' || bd.DS_TIPO_JEJUM ELSE '' END ||
                        CASE WHEN bd.DS_OBJETIVO_JEJUM IS NOT NULL THEN ' | Objetivo: ' || bd.DS_OBJETIVO_JEJUM ELSE '' END ||
                        CASE WHEN bd.DT_INICIO_JEJUM IS NOT NULL THEN ' | Início: ' || TO_CHAR(bd.DT_INICIO_JEJUM, 'dd/mm HH24:MI') ELSE '' END ||
                        CASE WHEN bd.DT_FIM_JEJUM IS NOT NULL THEN ' | Fim: ' || TO_CHAR(bd.DT_FIM_JEJUM, 'dd/mm HH24:MI') ELSE '' END
                      ELSE
                        '[ ' || TO_CHAR(bd.DT_INICIO, 'dd/mm/yyyy') || 
                        CASE WHEN bd.HR_PRIM_HORARIO IS NOT NULL THEN ' ' || bd.HR_PRIM_HORARIO ELSE '' END || 
                        ' ] Dieta: ' || COALESCE(bd.DS_DIETA, bd.DS_MATERIAL) || 
                        CASE WHEN bd.QT_DOSE IS NOT NULL THEN ' | Dose: ' || bd.QT_DOSE || ' ' || bd.CD_UNIDADE_MEDIDA_DOSE ELSE '' END ||
                        CASE WHEN bd.QT_VOLUME IS NOT NULL THEN ' | Volume: ' || bd.QT_VOLUME || 'ml' ELSE '' END ||
                        CASE WHEN bd.QT_KCAL_TOTAL IS NOT NULL THEN ' | Kcal: ' || bd.QT_KCAL_TOTAL ELSE '' END
                    END AS DS_RESUMO_FORMATADO
                  FROM base_dietas bd
                )
                SELECT * FROM dietas_ordenadas ORDER BY sort_order_
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
                        // FIXED: Generate tipo_nutricao based on available data
                        $tipoNutricao = 'Dieta';
                        if ($item->ie_tipo_dieta === 'J') {
                            $tipoNutricao = 'Jejum';
                        } elseif ($item->ie_dieta_enteral === 'S') {
                            $tipoNutricao = 'Enteral';
                        } elseif ($item->ie_dieta_especial === 'S') {
                            $tipoNutricao = 'Especial';
                        } elseif ($item->ds_dieta) {
                            $tipoNutricao = 'Dieta';
                        } elseif ($item->ds_material) {
                            $tipoNutricao = 'Material';
                        }
                        
                        return [
                            'prescricao' => $item->ie_tipo_dieta === 'J' 
                                ? 'JEJUM' . ($item->ds_tipo_jejum ? ' - ' . $item->ds_tipo_jejum : '') 
                                : ($item->ds_dieta ?? $item->ds_material ?? 'Prescrição nutricional'),
                            'tipo_dieta' => $item->ie_tipo_dieta,
                            'tipo_nutricao' => $tipoNutricao, // FIXED: Added missing field
                            'is_jejum' => $item->ie_tipo_dieta === 'J',
                            'tipo_jejum' => $item->ds_tipo_jejum,
                            'objetivo_jejum' => $item->ds_objetivo_jejum,
                            'observacoes' => $item->ds_observacao ?? $item->ds_orientacao,
                            'alergias_alimentares' => $item->ds_alergias_alimentares,
                            'data_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : null,
                            'horario_inicio' => $item->hr_prim_horario,
                            'data_fim' => $item->dt_fim ? Carbon::parse($item->dt_fim)->format('d/m/Y') : null,
                            'horarios' => $item->ds_horarios,
                            'volume' => $item->qt_volume,
                            'volume_total' => $item->qt_volume_total,
                            'kcal_total' => $item->qt_kcal_total,
                            'dose' => $item->qt_dose . ' ' . ($item->cd_unidade_medida_dose ?? ''),
                            'turno' => $item->ie_turno,
                            'nr_sequencia' => $item->nr_sequencia,
                            'cd_material' => $item->cd_material,
                            'nome_nutricionista' => $item->nome_nutricionista,
                            'dt_liberacao' => $item->dt_liberacao,
                            'is_active' => empty($item->dt_suspensao),
                            'is_enteral' => $item->ie_dieta_enteral === 'S',
                            'is_especial' => $item->ie_dieta_especial === 'S',
                            'periodo_completo' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y H:i') : '',
                            'resumo_formatado' => $item->ds_resumo_formatado,
                            'has_details' => !empty($item->ds_observacao) || !empty($item->ds_orientacao) || !empty($item->ds_alergias_alimentares)
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }

    /**
     * Get patient recommendations organized by shifts - NEW METHOD
     */
    public function getPatientRecommendations($attendanceNumber)
    {
        $cacheKey = "cpoe_recommendations_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            $recommendations = DB::connection('tasy')->select("
                WITH base_recomendacoes AS (
                  SELECT
                    cr.NR_SEQUENCIA,
                    cr.NR_ATENDIMENTO,
                    cr.CD_RECOMENDACAO,
                    tr.DS_TIPO_RECOMENDACAO AS DS_TIPO_RECOMENDACAO,
                    cr.DS_RECOMENDACAO,
                    cr.DS_HORARIOS,
                    cr.HR_PRIM_HORARIO,
                    cr.DT_INICIO,
                    cr.DT_FIM,
                    cr.DT_LIBERACAO,
                    cr.DT_SUSPENSAO,
                    cr.DS_OBSERVACAO,
                    cr.CD_PESSOA_FISICA,
                    TASY.OBTER_NOME_PF(cr.CD_PESSOA_FISICA) AS NOME_PROFISSIONAL,
                    CASE
                        WHEN cr.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cr.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                        WHEN cr.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cr.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                        ELSE 'NOITE'
                    END AS IE_TURNO
                  FROM
                    tasy.cpoe_recomendacao cr
                    LEFT JOIN tasy.TIPO_RECOMENDACAO tr ON cr.CD_RECOMENDACAO = tr.CD_TIPO_RECOMENDACAO
                  WHERE
                    cr.NR_ATENDIMENTO = :attendance
                    AND cr.DT_LIBERACAO IS NOT NULL
                    AND cr.DT_SUSPENSAO IS NULL
                    AND (
                      TRUNC(SYSDATE) BETWEEN TRUNC(cr.DT_INICIO) AND NVL(TRUNC(cr.DT_FIM), TRUNC(SYSDATE))
                    )
                ),
                recomendacoes_ordenadas AS (
                  SELECT
                    br.*,
                    ROW_NUMBER() OVER (
                      ORDER BY br.DT_INICIO, br.NR_SEQUENCIA
                    ) AS sort_order_,
                    '[ ' || TO_CHAR(br.DT_INICIO, 'dd/mm/yyyy') || 
                    CASE WHEN br.HR_PRIM_HORARIO IS NOT NULL THEN ' ' || br.HR_PRIM_HORARIO ELSE '' END || 
                    ' ] ' || CASE WHEN br.DS_TIPO_RECOMENDACAO IS NOT NULL THEN br.DS_TIPO_RECOMENDACAO || ': ' ELSE '' END || 
                    SUBSTR(br.DS_RECOMENDACAO, 1, 200) AS ds_resumo_formatado
                  FROM base_recomendacoes br
                )
                SELECT * FROM recomendacoes_ordenadas ORDER BY sort_order_
            ", ['attendance' => $attendanceNumber]);
            
            $recommendationsByShift = collect($recommendations)->groupBy('ie_turno');
            
            $result = [
                'total_count' => count($recommendations),
                'shifts' => []
            ];
            
            foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift) {
                $shiftRecommendations = $recommendationsByShift->get($shift, collect());
                
                $result['shifts'][$shift] = [
                    'count' => $shiftRecommendations->count(),
                    'recommendations' => $shiftRecommendations->map(function ($item) {
                        return [
                            'recomendacao' => $item->ds_recomendacao ?? 'Recomendação não especificada',
                            'tipo_recomendacao' => $item->ds_tipo_recomendacao,
                            'observacoes' => $item->ds_observacao,
                            'data_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : null,
                            'horario_inicio' => $item->hr_prim_horario,
                            'data_fim' => $item->dt_fim ? Carbon::parse($item->dt_fim)->format('d/m/Y') : null,
                            'horarios' => $item->ds_horarios,
                            'turno' => $item->ie_turno,
                            'nr_sequencia' => $item->nr_sequencia,
                            'cd_recomendacao' => $item->cd_recomendacao,
                            'nome_profissional' => $item->nome_profissional,
                            'dt_liberacao' => $item->dt_liberacao,
                            'is_active' => empty($item->dt_suspensao),
                            'periodo_completo' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y H:i') : '',
                            'resumo_formatado' => $item->ds_resumo_formatado,
                            'has_details' => !empty($item->ds_observacao) || !empty($item->nome_profissional)
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }

    /**
     * Get patient interventions organized by shifts - NEW METHOD
     */
    public function getPatientInterventions($attendanceNumber)
    {
        $cacheKey = "cpoe_interventions_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumber) {
            $interventions = DB::connection('tasy')->select("
                WITH base_intervencoes AS (
                  SELECT
                    ci.NR_SEQUENCIA,
                    ci.NR_ATENDIMENTO,
                    ci.NR_SEQ_PROC,
                    TASY.OBTER_DESC_PROC_INTERNO(ci.NR_SEQ_PROC) AS DS_PROCEDIMENTO,
                    ci.DS_HORARIOS,
                    ci.HR_PRIM_HORARIO,
                    ci.DT_INICIO,
                    ci.DT_FIM,
                    ci.DT_LIBERACAO,
                    ci.DT_SUSPENSAO,
                    ci.DS_OBSERVACAO,
                    ci.CD_PESSOA_FISICA,
                    TASY.OBTER_NOME_PF(ci.CD_PESSOA_FISICA) AS NOME_PROFISSIONAL,
                    ci.IE_SE_NECESSARIO,
                    ci.IE_ACM,
                    ci.IE_URGENCIA,
                    ci.IE_LADO,
                    ci.CD_PRESCRITOR,
                    TASY.OBTER_NOME_PF(ci.CD_PRESCRITOR) AS NOME_PRESCRITOR,
                    ci.DT_PRESCRICAO,
                    CASE
                        WHEN ci.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(ci.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                        WHEN ci.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(ci.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                        ELSE 'NOITE'
                    END AS IE_TURNO
                  FROM
                    tasy.CPOE_INTERVENCAO ci
                  WHERE
                    ci.NR_ATENDIMENTO = :attendance
                    AND ci.DT_LIBERACAO IS NOT NULL
                    AND ci.DT_SUSPENSAO IS NULL
                    AND (
                      TRUNC(SYSDATE) BETWEEN TRUNC(ci.DT_INICIO) AND NVL(TRUNC(ci.DT_FIM), TRUNC(SYSDATE))
                    )
                ),
                intervencoes_ordenadas AS (
                  SELECT
                    bi.*,
                    ROW_NUMBER() OVER (
                      ORDER BY bi.DT_INICIO, bi.NR_SEQUENCIA
                    ) AS sort_order_,
                    '[ ' || TO_CHAR(bi.DT_INICIO, 'dd/mm/yyyy') || 
                    CASE WHEN bi.HR_PRIM_HORARIO IS NOT NULL THEN ' ' || bi.HR_PRIM_HORARIO ELSE '' END || 
                    ' ] ' || bi.DS_PROCEDIMENTO || 
                    CASE WHEN bi.IE_LADO IS NOT NULL THEN ' | Lado: ' || 
                        CASE 
                            WHEN bi.IE_LADO = 'D' THEN 'Direito'
                            WHEN bi.IE_LADO = 'E' THEN 'Esquerdo'
                            WHEN bi.IE_LADO = 'B' THEN 'Bilateral'
                            ELSE bi.IE_LADO
                        END
                    ELSE '' END ||
                    CASE WHEN bi.IE_URGENCIA = '1' THEN ' | URGENTE' ELSE '' END ||
                    CASE WHEN bi.IE_SE_NECESSARIO = 'S' THEN ' | Se necessário' ELSE '' END ||
                    CASE WHEN bi.IE_ACM = 'S' THEN ' | ACM' ELSE '' END
                    AS ds_resumo_formatado
                  FROM base_intervencoes bi
                )
                SELECT * FROM intervencoes_ordenadas ORDER BY sort_order_
            ", ['attendance' => $attendanceNumber]);
            
            $interventionsByShift = collect($interventions)->groupBy('ie_turno');
            
            $result = [
                'total_count' => count($interventions),
                'shifts' => []
            ];
            
            foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift) {
                $shiftInterventions = $interventionsByShift->get($shift, collect());
                
                $result['shifts'][$shift] = [
                    'count' => $shiftInterventions->count(),
                    'interventions' => $shiftInterventions->map(function ($item) {
                        // Generate labels for intervention flags
                        $labels = [];
                        if ($item->ie_urgencia === '1') $labels[] = 'Urgente';
                        if ($item->ie_se_necessario === 'S') $labels[] = 'Se Necessário';
                        if ($item->ie_acm === 'S') $labels[] = 'ACM';
                        if ($item->ie_lado) {
                            $lado_map = ['D' => 'Direito', 'E' => 'Esquerdo', 'B' => 'Bilateral'];
                            $labels[] = 'Lado: ' . ($lado_map[$item->ie_lado] ?? $item->ie_lado);
                        }

                        return [
                            'procedimento' => $item->ds_procedimento ?? 'Procedimento não especificado',
                            'observacoes' => $item->ds_observacao,
                            'data_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : null,
                            'horario_inicio' => $item->hr_prim_horario,
                            'data_fim' => $item->dt_fim ? Carbon::parse($item->dt_fim)->format('d/m/Y') : null,
                            'horarios' => $item->ds_horarios,
                            'turno' => $item->ie_turno,
                            'nr_sequencia' => $item->nr_sequencia,
                            'nr_seq_proc' => $item->nr_seq_proc,
                            'nome_profissional' => $item->nome_profissional,
                            'nome_prescritor' => $item->nome_prescritor,
                            'dt_liberacao' => $item->dt_liberacao,
                            'dt_prescricao' => $item->dt_prescricao,
                            'is_active' => empty($item->dt_suspensao),
                            'is_urgente' => $item->ie_urgencia === '1',
                            'is_se_necessario' => $item->ie_se_necessario === 'S',
                            'is_acm' => $item->ie_acm === 'S',
                            'lado' => $item->ie_lado,
                            'labels' => $labels, // Dynamic labels based on flags
                            'periodo_completo' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y H:i') : '',
                            'resumo_formatado' => $item->ds_resumo_formatado,
                            'has_details' => !empty($item->ds_observacao) || !empty($item->nome_profissional) || !empty($item->nome_prescritor)
                        ];
                    })->toArray()
                ];
            }
            
            return $result;
        });
    }

    /**
     * Update unified summary methods to include new data types - OPTIMIZED
     */
    public function getUnifiedCpoePendingForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "unified_cpoe_pending_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 120, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            // Combined query to reduce database calls
            $results = DB::connection('tasy')->select("
                WITH unified_counts AS (
                    -- Pending procedures
                    SELECT 
                        pm.nr_atendimento,
                        COUNT(*) AS pending_procedures,
                        0 AS pending_medications,
                        0 AS pending_nutrition,
                        0 AS total_recommendations,
                        0 AS total_interventions
                    FROM tasy.prescr_medica pm
                    JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                    WHERE pm.nr_atendimento IN ({$placeholders})
                    AND TRUNC(pp.dt_prev_execucao) = TRUNC(SYSDATE)
                    AND pp.dt_baixa IS NULL
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_suspensao IS NULL
                    GROUP BY pm.nr_atendimento
                    
                    UNION ALL
                    
                    -- Pending medications (with filter flags applied)
                    SELECT 
                        pmed.nr_atendimento,
                        0 AS pending_procedures,
                        COUNT(pmh.nr_sequencia) AS pending_medications,
                        0 AS pending_nutrition,
                        0 AS total_recommendations,
                        0 AS total_interventions
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
                    
                    UNION ALL
                    
                    -- Active nutrition prescriptions
                    SELECT 
                        cd.nr_atendimento,
                        0 AS pending_procedures,
                        0 AS pending_medications,
                        COUNT(*) AS pending_nutrition,
                        0 AS total_recommendations,
                        0 AS total_interventions
                    FROM tasy.cpoe_dieta cd
                    WHERE cd.nr_atendimento IN ({$placeholders})
                    AND cd.dt_liberacao IS NOT NULL
                    AND cd.dt_suspensao IS NULL
                    AND TRUNC(SYSDATE) BETWEEN TRUNC(cd.dt_inicio) AND NVL(TRUNC(cd.dt_fim), TRUNC(SYSDATE))
                    GROUP BY cd.nr_atendimento
                    
                    UNION ALL
                    
                    -- Active recommendations
                    SELECT 
                        cr.nr_atendimento,
                        0 AS pending_procedures,
                        0 AS pending_medications,
                        0 AS pending_nutrition,
                        COUNT(*) AS total_recommendations,
                        0 AS total_interventions
                    FROM tasy.cpoe_recomendacao cr
                    WHERE cr.nr_atendimento IN ({$placeholders})
                    AND cr.dt_liberacao IS NOT NULL
                    AND cr.dt_suspensao IS NULL
                    AND TRUNC(SYSDATE) BETWEEN TRUNC(cr.dt_inicio) AND NVL(TRUNC(cr.dt_fim), TRUNC(SYSDATE))
                    GROUP BY cr.nr_atendimento
                    
                    UNION ALL
                    
                    -- Active interventions
                    SELECT 
                        ci.nr_atendimento,
                        0 AS pending_procedures,
                        0 AS pending_medications,
                        0 AS pending_nutrition,
                        0 AS total_recommendations,
                        COUNT(*) AS total_interventions
                    FROM tasy.cpoe_intervencao ci
                    WHERE ci.nr_atendimento IN ({$placeholders})
                    AND ci.dt_liberacao IS NOT NULL
                    AND ci.dt_suspensao IS NULL
                    AND TRUNC(SYSDATE) BETWEEN TRUNC(ci.dt_inicio) AND NVL(TRUNC(ci.dt_fim), TRUNC(SYSDATE))
                    GROUP BY ci.nr_atendimento
                )
                SELECT 
                    nr_atendimento,
                    SUM(pending_procedures) AS pending_procedures,
                    SUM(pending_medications) AS pending_medications,
                    SUM(pending_nutrition) AS pending_nutrition,
                    SUM(total_recommendations) AS total_recommendations,
                    SUM(total_interventions) AS total_interventions
                FROM unified_counts
                GROUP BY nr_atendimento
            ", array_merge($attendanceNumbers, $attendanceNumbers, $attendanceNumbers, $attendanceNumbers, $attendanceNumbers));
            
            // Initialize with zeros
            $cpoeData = [];
            foreach ($attendanceNumbers as $attendance) {
                $cpoeData[$attendance] = [
                    'has_cpoe_pending' => false,
                    'cpoe_pending_count' => 0,
                    'pending_procedures' => 0,
                    'pending_medications' => 0,
                    'pending_nutrition' => 0,
                    'total_recommendations' => 0,
                    'total_interventions' => 0
                ];
            }
            
            // Populate with actual data
            foreach ($results as $result) {
                $totalPending = intval($result->pending_procedures) + intval($result->pending_medications) + intval($result->pending_nutrition);
                
                $cpoeData[$result->nr_atendimento] = [
                    'has_cpoe_pending' => $totalPending > 0,
                    'cpoe_pending_count' => $totalPending,
                    'pending_procedures' => intval($result->pending_procedures),
                    'pending_medications' => intval($result->pending_medications),
                    'pending_nutrition' => intval($result->pending_nutrition),
                    'total_recommendations' => intval($result->total_recommendations),
                    'total_interventions' => intval($result->total_interventions)
                ];
            }
            
            return $cpoeData;
        });
    }

    /**
     * Get medication summary for multiple patients - MISSING METHOD
     */
    public function getMedicationSummaryForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "medication_summary_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    pmed.nr_atendimento,
                    COUNT(pmh.nr_sequencia) AS total_medications,
                    COUNT(CASE WHEN pmt.dt_baixa IS NULL THEN 1 END) AS pending_administrations
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
            
            $data = [];
            foreach ($results as $result) {
                $data[$result->nr_atendimento] = [
                    'has_medications' => intval($result->total_medications) > 0,
                    'total_medications' => intval($result->total_medications),
                    'pending_administrations' => intval($result->pending_administrations)
                ];
            }
            
            return $data;
        });
    }

    /**
     * Get nutrition summary for multiple patients - MISSING METHOD
     */
    public function getNutritionSummaryForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "nutrition_summary_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 180, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    cd.nr_atendimento,
                    COUNT(*) AS total_nutrition_prescriptions,
                    COUNT(CASE WHEN cd.dt_suspensao IS NULL THEN 1 END) AS active_prescriptions
                FROM tasy.cpoe_dieta cd
                WHERE cd.nr_atendimento IN ({$placeholders})
                AND cd.dt_liberacao IS NOT NULL
                AND TRUNC(SYSDATE) BETWEEN TRUNC(cd.dt_inicio) AND NVL(TRUNC(cd.dt_fim), TRUNC(SYSDATE))
                GROUP BY cd.nr_atendimento
            ", $attendanceNumbers);
            
            $data = [];
            foreach ($results as $result) {
                $data[$result->nr_atendimento] = [
                    'has_nutrition' => intval($result->total_nutrition_prescriptions) > 0,
                    'total_nutrition_prescriptions' => intval($result->total_nutrition_prescriptions),
                    'active_prescriptions' => intval($result->active_prescriptions)
                ];
            }
            
            return $data;
        });
    }
}
