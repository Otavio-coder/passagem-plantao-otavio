<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PatientScales 
{
    protected $connection = 'tasy';
    
    /**
     * MÉTODO ÚNICO PARA BUSCAR ESCALAS - SIMPLIFICADO
     * Retorna apenas dados brutos essenciais para processamento pelo ScaleService
     * 
     * @param int $attendanceNumber
     * @return object|null
     */
    public function getPatientScales($attendanceNumber)
    {
        try {
            $currentShift = $this->getCurrentShift();
            $shiftStart = $this->getCurrentShiftStart();
            
            $result = DB::connection('tasy')->select("
                SELECT 
                    -- MEWS (turno atual e anterior)
                    NVL(mews_atual.valor, NULL) as mews_atual,
                    TO_CHAR(mews_atual.dt_avaliacao, 'DD/MM/YYYY HH24:MI') as mews_data,
                    NVL(mews_anterior.valor, NULL) as mews_anterior,
                    
                    -- PEWS (turno atual e anterior)  
                    NVL(pews_atual.valor, NULL) as pews_atual,
                    TO_CHAR(pews_atual.dt_avaliacao, 'DD/MM/YYYY HH24:MI') as pews_data,
                    NVL(pews_anterior.valor, NULL) as pews_anterior,
                    
                    -- BRADEN (24h atual e anterior)
                    NVL(braden_atual.valor, NULL) as braden_atual,
                    TO_CHAR(braden_atual.dt_avaliacao, 'DD/MM/YYYY HH24:MI') as braden_data,
                    NVL(braden_anterior.valor, NULL) as braden_anterior,
                    
                    -- MORSE (24h atual e anterior)
                    NVL(morse_atual.valor, NULL) as morse_atual,
                    TO_CHAR(morse_atual.dt_avaliacao, 'DD/MM/YYYY HH24:MI') as morse_data,
                    NVL(morse_anterior.valor, NULL) as morse_anterior,
                    
                    -- DOR (turno atual e anterior)
                    NVL(dor_atual.valor, NULL) as dor_atual,
                    TO_CHAR(dor_atual.dt_avaliacao, 'DD/MM/YYYY HH24:MI') as dor_data,
                    NVL(dor_anterior.valor, NULL) as dor_anterior,
                    
                    -- TEV (24h atual e anterior)
                    NVL(tev_atual.valor, NULL) as tev_atual,
                    TO_CHAR(tev_atual.dt_avaliacao, 'DD/MM/YYYY HH24:MI') as tev_data,
                    NVL(tev_anterior.valor, NULL) as tev_anterior
                    
                FROM (
                    -- Subquery principal para obter referências
                    SELECT :nr_atend AS nr_atendimento,
                           TRUNC(SYSDATE) as data_hoje,
                           TO_DATE(:shift_start, 'YYYY-MM-DD HH24:MI:SS') AS inicio_turno
                    FROM dual
                ) base
                
                -- MEWS atual (turno)
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor, dt_avaliacao,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_mews 
                    WHERE nr_atendimento = :nr_atend2
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao >= TO_DATE(:shift_start2, 'YYYY-MM-DD HH24:MI:SS')
                ) mews_atual ON mews_atual.nr_atendimento = base.nr_atendimento AND mews_atual.rn = 1
                
                -- MEWS anterior
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_mews 
                    WHERE nr_atendimento = :nr_atend3
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao < TO_DATE(:shift_start3, 'YYYY-MM-DD HH24:MI:SS')
                ) mews_anterior ON mews_anterior.nr_atendimento = base.nr_atendimento AND mews_anterior.rn = 1
                
                -- PEWS atual (turno)
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor, dt_avaliacao,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_pews 
                    WHERE nr_atendimento = :nr_atend4
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao >= TO_DATE(:shift_start4, 'YYYY-MM-DD HH24:MI:SS')
                ) pews_atual ON pews_atual.nr_atendimento = base.nr_atendimento AND pews_atual.rn = 1
                
                -- PEWS anterior
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_pews 
                    WHERE nr_atendimento = :nr_atend5
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao < TO_DATE(:shift_start5, 'YYYY-MM-DD HH24:MI:SS')
                ) pews_anterior ON pews_anterior.nr_atendimento = base.nr_atendimento AND pews_anterior.rn = 1
                
                -- BRADEN atual (24h)
                LEFT JOIN (
                    SELECT nr_atendimento, qt_ponto as valor, dt_avaliacao,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.atend_escala_braden 
                    WHERE nr_atendimento = :nr_atend6
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao >= TRUNC(SYSDATE)
                ) braden_atual ON braden_atual.nr_atendimento = base.nr_atendimento AND braden_atual.rn = 1
                
                -- BRADEN anterior
                LEFT JOIN (
                    SELECT nr_atendimento, qt_ponto as valor,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.atend_escala_braden 
                    WHERE nr_atendimento = :nr_atend7
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao < TRUNC(SYSDATE)
                ) braden_anterior ON braden_anterior.nr_atendimento = base.nr_atendimento AND braden_anterior.rn = 1
                
                -- MORSE atual (24h)
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor, dt_avaliacao,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_morse 
                    WHERE nr_atendimento = :nr_atend8
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao >= TRUNC(SYSDATE)
                ) morse_atual ON morse_atual.nr_atendimento = base.nr_atendimento AND morse_atual.rn = 1
                
                -- MORSE anterior
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_morse 
                    WHERE nr_atendimento = :nr_atend9
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao < TRUNC(SYSDATE)
                ) morse_anterior ON morse_anterior.nr_atendimento = base.nr_atendimento AND morse_anterior.rn = 1
                
                -- DOR atual (turno)
                LEFT JOIN (
                    SELECT nr_atendimento, qt_escala_dor as valor, dt_sinal_vital as dt_avaliacao,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_sinal_vital DESC) as rn
                    FROM tasy.atendimento_sinal_vital 
                    WHERE nr_atendimento = :nr_atend10
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND qt_escala_dor IS NOT NULL
                      AND dt_sinal_vital >= TO_DATE(:shift_start6, 'YYYY-MM-DD HH24:MI:SS')
                ) dor_atual ON dor_atual.nr_atendimento = base.nr_atendimento AND dor_atual.rn = 1
                
                -- DOR anterior
                LEFT JOIN (
                    SELECT nr_atendimento, qt_escala_dor as valor,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_sinal_vital DESC) as rn
                    FROM tasy.atendimento_sinal_vital 
                    WHERE nr_atendimento = :nr_atend11
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND qt_escala_dor IS NOT NULL
                      AND dt_sinal_vital < TO_DATE(:shift_start7, 'YYYY-MM-DD HH24:MI:SS')
                ) dor_anterior ON dor_anterior.nr_atendimento = base.nr_atendimento AND dor_anterior.rn = 1
                
                -- TEV atual (24h)
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor, dt_avaliacao,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_tev 
                    WHERE nr_atendimento = :nr_atend12
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao >= TRUNC(SYSDATE)
                ) tev_atual ON tev_atual.nr_atendimento = base.nr_atendimento AND tev_atual.rn = 1
                
                -- TEV anterior
                LEFT JOIN (
                    SELECT nr_atendimento, qt_pontuacao as valor,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_tev 
                    WHERE nr_atendimento = :nr_atend13
                      AND dt_liberacao IS NOT NULL 
                      AND dt_inativacao IS NULL
                      AND dt_avaliacao < TRUNC(SYSDATE)
                ) tev_anterior ON tev_anterior.nr_atendimento = base.nr_atendimento AND tev_anterior.rn = 1
            ", [
                'nr_atend' => $attendanceNumber,
                'shift_start' => $shiftStart,
                'nr_atend2' => $attendanceNumber,
                'shift_start2' => $shiftStart,
                'nr_atend3' => $attendanceNumber,
                'shift_start3' => $shiftStart,
                'nr_atend4' => $attendanceNumber,
                'shift_start4' => $shiftStart,
                'nr_atend5' => $attendanceNumber,
                'shift_start5' => $shiftStart,
                'nr_atend6' => $attendanceNumber,
                'nr_atend7' => $attendanceNumber,
                'nr_atend8' => $attendanceNumber,
                'nr_atend9' => $attendanceNumber,
                'nr_atend10' => $attendanceNumber,
                'shift_start6' => $shiftStart,
                'nr_atend11' => $attendanceNumber,
                'shift_start7' => $shiftStart,
                'nr_atend12' => $attendanceNumber,
                'nr_atend13' => $attendanceNumber
            ]);
            
            if (empty($result)) {
                return null;
            }
            
            return $result[0];
            
        } catch (\Exception $e) {
            Log::error("PatientScales: Erro ao buscar escalas", [
                'attendance_number' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    private function getCurrentShift()
    {
        $now = now();
        $hour = $now->hour;
        
        if ($hour >= 7 && $hour < 13) {
            return 'manha';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'tarde';
        } else {
            return 'noite';
        }
    }
    
    private function getCurrentShiftStart()
    {
        $shift = $this->getCurrentShift();
        $now = now();
        
        switch ($shift) {
            case 'manha':
                return $now->copy()->setTime(7, 0, 0)->format('Y-m-d H:i:s');
            case 'tarde':
                return $now->copy()->setTime(13, 0, 0)->format('Y-m-d H:i:s');
            case 'noite':
                // Se é noite e ainda é do dia atual (19h-23h59), início é 19h
                // Se é noite do dia seguinte (0h-6h59), início é 19h do dia anterior
                if ($now->hour >= 19) {
                    return $now->copy()->setTime(19, 0, 0)->format('Y-m-d H:i:s');
                } else {
                    return $now->copy()->subDay()->setTime(19, 0, 0)->format('Y-m-d H:i:s');
                }
        }
    }
}