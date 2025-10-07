<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PatientCPOE 
{
    protected $connection = 'tasy';
    
    /**
     * Busca procedimentos CPOE organizados por turno
     * Usado pela Patient::fetchPatientCPOEDetails()
     */
    public function getPatientCpoeProcedures($attendanceNumber)
    {
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
        
        return $this->organizeByShifts($procedures, 'procedures');
    }
    
    /**
     * Busca medicações organizadas por turno
     * Usado pela Patient::fetchPatientCPOEDetails()
     */
    public function getPatientMedications($attendanceNumber)
    {
        $medications = DB::connection('tasy')->select("
            SELECT
                pmh.nr_prescricao,
                pmh.nr_sequencia,
                pmh.nr_seq_material,
                pmt.cd_material,
                TASY.OBTER_DESC_MATERIAL(pmt.cd_material) AS ds_material,
                pmt.qt_dose,
                pmh.cd_unidade_medida_dose,
                TASY.OBTER_VIA_APLICACAO(pmt.ie_via_aplicacao,'D') AS via_aplicacao,
                pmh.dt_horario,
                pmh.ds_horario,
                pmt.dt_baixa,
                pmh.qt_dispensar,
                pmh.qt_dispensar_hor,
                pmed.dt_prescricao,
                pmed.dt_validade_prescr,
                CASE
                    WHEN TO_NUMBER(SUBSTR(TO_CHAR(pmh.dt_horario, 'HH24:MI'), 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                    WHEN TO_NUMBER(SUBSTR(TO_CHAR(pmh.dt_horario, 'HH24:MI'), 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                    ELSE 'NOITE'
                END AS turno
            FROM tasy.prescr_medica pmed
            JOIN tasy.prescr_material pmt ON pmed.nr_prescricao = pmt.nr_prescricao
            JOIN tasy.prescr_mat_hor pmh ON pmt.nr_prescricao = pmh.nr_prescricao
                AND pmt.nr_sequencia = pmh.nr_seq_material
            WHERE pmed.nr_atendimento = :attendance
              AND pmed.dt_liberacao IS NOT NULL
              AND pmed.dt_suspensao IS NULL
              AND pmt.ie_suspenso <> 'S'
              AND pmt.ie_tipo_medic_hd = 'D'
              AND NVL(pmt.ie_administrar,'S') = 'S'
              AND pmh.dt_horario >= TRUNC(SYSDATE)
              AND pmh.dt_horario < TRUNC(SYSDATE) + 1
              -- Exclui materiais específicos
              AND pmt.cd_material NOT IN (51666,51655,51656,52440,51669)
              -- Exclui se necessário, ACM, recomendações, etc
              AND NVL(pmt.ie_se_necessario,'N') = 'N'
              AND NVL(pmt.ie_acm,'N') = 'N'
              AND pmt.nr_seq_recomendacao IS NULL
              AND pmt.nr_sequencia_solucao IS NULL
              AND pmt.nr_sequencia_proc IS NULL
              AND pmt.nr_sequencia_diluicao IS NULL
              AND pmt.nr_sequencia_dieta IS NULL
            ORDER BY pmh.dt_horario
        ", ['attendance' => $attendanceNumber]);
        
        return $this->organizeByShifts($medications, 'medications');
    }
    
    /**
     * Busca prescrições nutricionais organizadas por turno
     * Usado pela Patient::fetchPatientCPOEDetails()
     */
    public function getPatientNutrition($attendanceNumber)
    {
        $nutrition = DB::connection('tasy')->select("
            SELECT
                cd.NR_SEQUENCIA,
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
                cd.DS_OBSERVACAO,
                cd.IE_DIETA_ENTERAL,
                cd.IE_DIETA_ESPECIAL,
                cd.QT_KCAL_TOTAL,
                cd.DS_ALERGIAS_ALIMENTARES,
                cd.IE_JEJUM,
                obj.DS_OBJETIVO AS DS_OBJETIVO_JEJUM,
                tj.DS_TIPO AS DS_TIPO_JEJUM,
                cd.DT_INICIO_JEJUM,
                cd.DT_FIM_JEJUM,
                TASY.OBTER_NOME_PF(cd.CD_PESSOA_FISICA) AS NOME_NUTRICIONISTA,
                CASE
                    WHEN cd.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                    WHEN cd.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                    ELSE 'NOITE'
                END AS turno
            FROM tasy.cpoe_dieta cd
            LEFT JOIN tasy.dieta d ON cd.CD_DIETA = d.CD_DIETA
            LEFT JOIN tasy.REP_OBJETIVO_JEJUM obj ON cd.NR_SEQ_OBJETIVO = obj.NR_SEQUENCIA
            LEFT JOIN tasy.REP_TIPO_JEJUM tj ON cd.NR_SEQ_TIPO = tj.NR_SEQUENCIA
            WHERE cd.NR_ATENDIMENTO = :attendance
              AND cd.DT_LIBERACAO IS NOT NULL
              AND cd.DT_SUSPENSAO IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(cd.DT_INICIO) AND NVL(TRUNC(cd.DT_FIM), TRUNC(SYSDATE))
            ORDER BY cd.DT_INICIO, cd.NR_SEQUENCIA
        ", ['attendance' => $attendanceNumber]);
        
        return $this->organizeByShifts($nutrition, 'nutrition');
    }
    
    /**
     * Busca recomendações organizadas por turno
     * Usado pela Patient::fetchPatientCPOEDetails()
     */
    public function getPatientRecommendations($attendanceNumber)
    {
        $recommendations = DB::connection('tasy')->select("
            SELECT
                cr.NR_SEQUENCIA,
                cr.CD_RECOMENDACAO,
                tr.DS_TIPO_RECOMENDACAO,
                cr.DS_RECOMENDACAO,
                cr.DS_HORARIOS,
                cr.HR_PRIM_HORARIO,
                cr.DT_INICIO,
                cr.DT_FIM,
                cr.DS_OBSERVACAO,
                TASY.OBTER_NOME_PF(cr.CD_PESSOA_FISICA) AS NOME_PROFISSIONAL,
                CASE
                    WHEN cr.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cr.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                    WHEN cr.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cr.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                    ELSE 'NOITE'
                END AS turno
            FROM tasy.cpoe_recomendacao cr
            LEFT JOIN tasy.TIPO_RECOMENDACAO tr ON cr.CD_RECOMENDACAO = tr.CD_TIPO_RECOMENDACAO
            WHERE cr.NR_ATENDIMENTO = :attendance
              AND cr.DT_LIBERACAO IS NOT NULL
              AND cr.DT_SUSPENSAO IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(cr.DT_INICIO) AND NVL(TRUNC(cr.DT_FIM), TRUNC(SYSDATE))
            ORDER BY cr.DT_INICIO, cr.NR_SEQUENCIA
        ", ['attendance' => $attendanceNumber]);
        
        return $this->organizeByShifts($recommendations, 'recommendations');
    }
    
    /**
     * Busca intervenções organizadas por turno
     * Usado pela Patient::fetchPatientCPOEDetails()
     */
    public function getPatientInterventions($attendanceNumber)
    {
        $interventions = DB::connection('tasy')->select("
            SELECT
                ci.NR_SEQUENCIA,
                ci.NR_SEQ_PROC,
                TASY.OBTER_DESC_PROC_INTERNO(ci.NR_SEQ_PROC) AS DS_PROCEDIMENTO,
                ci.DS_HORARIOS,
                ci.HR_PRIM_HORARIO,
                ci.DT_INICIO,
                ci.DT_FIM,
                ci.DS_OBSERVACAO,
                TASY.OBTER_NOME_PF(ci.CD_PESSOA_FISICA) AS NOME_PROFISSIONAL,
                TASY.OBTER_NOME_PF(ci.CD_PRESCRITOR) AS NOME_PRESCRITOR,
                ci.IE_SE_NECESSARIO,
                ci.IE_ACM,
                ci.IE_URGENCIA,
                ci.IE_LADO,
                CASE
                    WHEN ci.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(ci.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                    WHEN ci.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(ci.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                    ELSE 'NOITE'
                END AS turno
            FROM tasy.CPOE_INTERVENCAO ci
            WHERE ci.NR_ATENDIMENTO = :attendance
              AND ci.DT_LIBERACAO IS NOT NULL
              AND ci.DT_SUSPENSAO IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(ci.DT_INICIO) AND NVL(TRUNC(ci.DT_FIM), TRUNC(SYSDATE))
            ORDER BY ci.DT_INICIO, ci.NR_SEQUENCIA
        ", ['attendance' => $attendanceNumber]);
        
        return $this->organizeByShifts($interventions, 'interventions');
    }
    
    /**
     * Organiza dados por turnos (Manhã, Tarde, Noite)
     */
    private function organizeByShifts($data, $type)
    {
        $dataByShift = collect($data)->groupBy('turno');

        // Mapeamento das chaves esperadas pela view
        $typeMap = [
            'procedures' => 'procedures',
            'medications' => 'medications',
            'nutrition' => 'prescriptions',
            'recommendations' => 'recommendations',
            'interventions' => 'interventions',
        ];
        $key = $typeMap[$type] ?? $type;

        $result = [
            'total_count' => count($data),
            'shifts' => []
        ];

        foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift) {
            $shiftData = $dataByShift->get($shift, collect());

            $result['shifts'][$shift] = [
                'count' => $shiftData->count(),
                $key => $shiftData->map(function($item) use ($type) {
                    return $this->formatItem($item, $type);
                })->toArray()
            ];
        }

        return $result;
    }
    
    /**
     * Formata item baseado no tipo
     */
        
    private function formatItem($item, $type)
    {
        switch ($type) {
            case 'procedures':
                return [
                    'procedimento' => $item->ds_procedimento ?? 'Procedimento não identificado',
                    'data_prevista' => $item->dt_prev_execucao_fmt ?? '',
                    'horario' => $item->hr_prev_execucao ?? '',
                    'turno' => $item->turno,
                    'is_completed' => !empty($item->dt_baixa),
                    'has_details' => true,
                ];

            case 'medications':
                return [
                    'medicamento' => $item->ds_material ?? 'Medicamento não identificado',
                    'dose' => $item->qt_dose . ' ' . ($item->cd_unidade_medida_dose ?? ''),
                    'via_aplicacao' => $item->via_aplicacao ?? 'Via não informada',
                    'horario' => $item->ds_horario ?? '',
                    'turno' => $item->turno,
                    'is_administered' => !empty($item->dt_baixa),
                    'is_suspended' => !empty($item->dt_suspensao),
                    'dispensar' => $item->qt_dispensar ?? '',
                    'nr_prescricao' => $item->nr_prescricao ?? '',
                    'dt_prescricao' => $item->dt_prescricao ?? null,
                    'resumo_formatado' => $item->resumo_formatado ?? '',
                    'has_details' => true,
                ];

            case 'nutrition':
                $isPejeJejum = $item->ie_tipo_dieta === 'J';
                $tipoNutricao = $isPejeJejum ? 'Jejum' : 
                    ($item->ie_dieta_enteral === 'S' ? 'Enteral' : 
                    ($item->ie_dieta_especial === 'S' ? 'Especial' : 'Dieta'));

                return [
                    'prescricao' => $isPejeJejum 
                        ? 'JEJUM' . ($item->ds_tipo_jejum ? ' - ' . $item->ds_tipo_jejum : '') 
                        : ($item->ds_dieta ?? $item->ds_material ?? 'Prescrição nutricional'),
                    'tipo_nutricao' => $tipoNutricao,
                    'is_jejum' => $isPejeJejum,
                    'observacoes' => $item->ds_observacao ?? '',
                    'horario_inicio' => $item->hr_prim_horario ?? '',
                    'volume' => $item->qt_volume ?? null,
                    'kcal_total' => $item->qt_kcal_total ?? null,
                    'turno' => $item->turno,
                    'alergias_alimentares' => $item->ds_alergias_alimentares ?? '',
                    'is_enteral' => $item->ie_dieta_enteral === 'S',
                    'is_especial' => $item->ie_dieta_especial === 'S',
                    'periodo_completo' => ($item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : '') . 
                        (($item->hr_prim_horario ?? '') ? ' ' . $item->hr_prim_horario : ''),
                    'is_active' => true,
                    'has_details' => true,
                    'resumo_formatado' => $item->resumo_formatado ?? '',
                ];

            case 'recommendations':
                return [
                    'recomendacao' => $item->ds_recomendacao ?? 'Recomendação não especificada',
                    'tipo_recomendacao' => $item->ds_tipo_recomendacao ?? '',
                    'observacoes' => $item->ds_observacao ?? '',
                    'horario_inicio' => $item->hr_prim_horario ?? '',
                    'turno' => $item->turno,
                    'nome_profissional' => $item->nome_profissional ?? '',
                    'is_active' => true,
                    'has_details' => true,
                    'data_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : '',
                    'resumo_formatado' => $item->resumo_formatado ?? '',
                ];

            case 'interventions':
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
                    'observacoes' => $item->ds_observacao ?? '',
                    'horario_inicio' => $item->hr_prim_horario ?? '',
                    'turno' => $item->turno,
                    'nome_profissional' => $item->nome_profissional ?? '',
                    'nome_prescritor' => $item->nome_prescritor ?? '',
                    'labels' => $labels,
                    'is_active' => true,
                    'has_details' => true,
                    'data_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : '',
                    'resumo_formatado' => $item->resumo_formatado ?? '',
                ];

            default:
                return [];
        }
    }
}