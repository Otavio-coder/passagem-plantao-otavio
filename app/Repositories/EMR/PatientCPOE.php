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
}
