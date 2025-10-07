<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PatientScales 
{
    protected $connection = 'tasy';
    
    /**
     * MÉTODO ÚNICO PARA BUSCAR ESCALAS
     * Usado tanto para cards quanto para modal
     * 
     * @param int $attendanceNumber
     * @return object
     */
    public function getPatientScales($attendanceNumber)
    {
        try {
            $currentShift = $this->getCurrentShift();
            $shiftStart = $this->getCurrentShiftStart();
            
            $result = DB::connection('tasy')->select("
                SELECT
                    -- MEWS (última 24h + turno atual + penúltima + shift)
                    (SELECT qt_pontuacao 
                     FROM tasy.escala_mews 
                     WHERE nr_atendimento = :nr_atend1
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS mews_score,
                    
                    (SELECT TO_CHAR(dt_avaliacao, 'DD/MM HH24:MI')
                     FROM tasy.escala_mews 
                     WHERE nr_atendimento = :nr_atend2
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS mews_datetime,
                    
                    (SELECT CASE 
                        WHEN TO_CHAR(dt_avaliacao, 'HH24:MI') BETWEEN '07:00' AND '12:59' THEN 'manha'
                        WHEN TO_CHAR(dt_avaliacao, 'HH24:MI') BETWEEN '13:00' AND '18:59' THEN 'tarde'
                        ELSE 'noite'
                    END
                     FROM tasy.escala_mews 
                     WHERE nr_atendimento = :nr_atend2b
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS mews_shift,
                    
                    (SELECT qt_pontuacao 
                     FROM (
                         SELECT qt_pontuacao, ROW_NUMBER() OVER (ORDER BY dt_avaliacao DESC) as rn
                         FROM tasy.escala_mews
                         WHERE nr_atendimento = :nr_atend3
                           AND dt_liberacao IS NOT NULL
                           AND dt_inativacao IS NULL
                           AND dt_avaliacao >= SYSDATE - 2
                     ) WHERE rn = 2) AS mews_previous,
                    
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                     FROM tasy.escala_mews
                     WHERE nr_atendimento = :nr_atend4
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL
                       AND dt_avaliacao >= :shift_start) AS mews_current_shift,
                    
                    -- BRADEN (última 24h + penúltima)
                    (SELECT qt_ponto 
                     FROM tasy.ATEND_ESCALA_BRADEN 
                     WHERE nr_atendimento = :nr_atend5
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS braden_score,
                    
                    (SELECT TO_CHAR(dt_avaliacao, 'DD/MM HH24:MI')
                     FROM tasy.ATEND_ESCALA_BRADEN 
                     WHERE nr_atendimento = :nr_atend6
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS braden_datetime,
                    
                    (SELECT qt_ponto 
                     FROM (
                         SELECT qt_ponto, ROW_NUMBER() OVER (ORDER BY dt_avaliacao DESC) as rn
                         FROM tasy.ATEND_ESCALA_BRADEN
                         WHERE nr_atendimento = :nr_atend7
                           AND dt_liberacao IS NOT NULL
                           AND dt_inativacao IS NULL
                           AND dt_avaliacao >= SYSDATE - 2
                     ) WHERE rn = 2) AS braden_previous,
                    
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                     FROM tasy.ATEND_ESCALA_BRADEN
                     WHERE nr_atendimento = :nr_atend8
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL
                       AND dt_avaliacao >= SYSDATE - 1) AS braden_done_24h,
                    
                    -- MORSE (última 24h + penúltima)
                    (SELECT qt_pontuacao 
                     FROM tasy.escala_morse 
                     WHERE nr_atendimento = :nr_atend9
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS morse_score,
                    
                    (SELECT TO_CHAR(dt_avaliacao, 'DD/MM HH24:MI')
                     FROM tasy.escala_morse 
                     WHERE nr_atendimento = :nr_atend10
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS morse_datetime,
                    
                    (SELECT qt_pontuacao 
                     FROM (
                         SELECT qt_pontuacao, ROW_NUMBER() OVER (ORDER BY dt_avaliacao DESC) as rn
                         FROM tasy.escala_morse
                         WHERE nr_atendimento = :nr_atend11
                           AND dt_liberacao IS NOT NULL
                           AND dt_inativacao IS NULL
                           AND dt_avaliacao >= SYSDATE - 2
                     ) WHERE rn = 2) AS morse_previous,
                    
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                     FROM tasy.escala_morse
                     WHERE nr_atendimento = :nr_atend12
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL
                       AND dt_avaliacao >= SYSDATE - 1) AS morse_done_24h,
                    
                    -- DOR (última 24h + turno atual + penúltima + shift)
                    (SELECT qt_escala_dor 
                     FROM tasy.atendimento_sinal_vital 
                     WHERE nr_atendimento = :nr_atend13
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND qt_escala_dor IS NOT NULL 
                       AND dt_sinal_vital >= SYSDATE - 1
                     ORDER BY dt_sinal_vital DESC
                     FETCH FIRST 1 ROWS ONLY) AS dor_score,
                    
                    (SELECT TO_CHAR(dt_sinal_vital, 'DD/MM HH24:MI')
                     FROM tasy.atendimento_sinal_vital 
                     WHERE nr_atendimento = :nr_atend14
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND qt_escala_dor IS NOT NULL 
                       AND dt_sinal_vital >= SYSDATE - 1
                     ORDER BY dt_sinal_vital DESC
                     FETCH FIRST 1 ROWS ONLY) AS dor_datetime,
                    
                    (SELECT CASE 
                        WHEN TO_CHAR(dt_sinal_vital, 'HH24:MI') BETWEEN '07:00' AND '12:59' THEN 'manha'
                        WHEN TO_CHAR(dt_sinal_vital, 'HH24:MI') BETWEEN '13:00' AND '18:59' THEN 'tarde'
                        ELSE 'noite'
                    END
                     FROM tasy.atendimento_sinal_vital 
                     WHERE nr_atendimento = :nr_atend14b
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND qt_escala_dor IS NOT NULL 
                       AND dt_sinal_vital >= SYSDATE - 1
                     ORDER BY dt_sinal_vital DESC
                     FETCH FIRST 1 ROWS ONLY) AS dor_shift,
                    
                    (SELECT qt_escala_dor 
                     FROM (
                         SELECT qt_escala_dor, ROW_NUMBER() OVER (ORDER BY dt_sinal_vital DESC) as rn
                         FROM tasy.atendimento_sinal_vital
                         WHERE nr_atendimento = :nr_atend15
                           AND dt_liberacao IS NOT NULL
                           AND dt_inativacao IS NULL
                           AND qt_escala_dor IS NOT NULL
                           AND dt_sinal_vital >= SYSDATE - 2
                     ) WHERE rn = 2) AS dor_previous,
                    
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                     FROM tasy.atendimento_sinal_vital
                     WHERE nr_atendimento = :nr_atend16
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL
                       AND qt_escala_dor IS NOT NULL
                       AND dt_sinal_vital >= :shift_start2) AS dor_current_shift,
                    
                    -- TEV (última 24h + penúltima)
                    (SELECT qt_pontuacao 
                     FROM tasy.escala_tev 
                     WHERE nr_atendimento = :nr_atend17
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS tev_score,
                    
                    (SELECT TO_CHAR(dt_avaliacao, 'DD/MM HH24:MI')
                     FROM tasy.escala_tev 
                     WHERE nr_atendimento = :nr_atend18
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS tev_datetime,
                    
                    (SELECT qt_pontuacao 
                     FROM (
                         SELECT qt_pontuacao, ROW_NUMBER() OVER (ORDER BY dt_avaliacao DESC) as rn
                         FROM tasy.escala_tev
                         WHERE nr_atendimento = :nr_atend19
                           AND dt_liberacao IS NOT NULL
                           AND dt_inativacao IS NULL
                           AND dt_avaliacao >= SYSDATE - 2
                     ) WHERE rn = 2) AS tev_previous,
                    
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                     FROM tasy.escala_tev
                     WHERE nr_atendimento = :nr_atend20
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL
                       AND dt_avaliacao >= SYSDATE - 1) AS tev_done_24h,
                    
                    -- PEWS (última 24h + turno atual + penúltima + shift) - só pediátrico
                    (SELECT qt_pontuacao 
                     FROM tasy.escala_pews 
                     WHERE nr_atendimento = :nr_atend21
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS pews_score,
                    
                    (SELECT TO_CHAR(dt_avaliacao, 'DD/MM HH24:MI')
                     FROM tasy.escala_pews 
                     WHERE nr_atendimento = :nr_atend22
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS pews_datetime,
                    
                    (SELECT CASE 
                        WHEN TO_CHAR(dt_avaliacao, 'HH24:MI') BETWEEN '07:00' AND '12:59' THEN 'manha'
                        WHEN TO_CHAR(dt_avaliacao, 'HH24:MI') BETWEEN '13:00' AND '18:59' THEN 'tarde'
                        ELSE 'noite'
                    END
                     FROM tasy.escala_pews 
                     WHERE nr_atendimento = :nr_atend22b
                       AND dt_liberacao IS NOT NULL 
                       AND dt_inativacao IS NULL 
                       AND dt_avaliacao >= SYSDATE - 1
                     ORDER BY dt_avaliacao DESC
                     FETCH FIRST 1 ROWS ONLY) AS pews_shift,
                    
                    (SELECT qt_pontuacao 
                     FROM (
                         SELECT qt_pontuacao, ROW_NUMBER() OVER (ORDER BY dt_avaliacao DESC) as rn
                         FROM tasy.escala_pews
                         WHERE nr_atendimento = :nr_atend23
                           AND dt_liberacao IS NOT NULL
                           AND dt_inativacao IS NULL
                           AND dt_avaliacao >= SYSDATE - 2
                     ) WHERE rn = 2) AS pews_previous,
                    
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                     FROM tasy.escala_pews
                     WHERE nr_atendimento = :nr_atend24
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL
                       AND dt_avaliacao >= :shift_start3) AS pews_current_shift
                    
                FROM dual
            ", [
                'nr_atend1' => $attendanceNumber, 'nr_atend2' => $attendanceNumber, 'nr_atend2b' => $attendanceNumber, 'nr_atend3' => $attendanceNumber,
                'nr_atend4' => $attendanceNumber, 'shift_start' => $shiftStart,
                'nr_atend5' => $attendanceNumber, 'nr_atend6' => $attendanceNumber, 'nr_atend7' => $attendanceNumber, 'nr_atend8' => $attendanceNumber,
                'nr_atend9' => $attendanceNumber, 'nr_atend10' => $attendanceNumber, 'nr_atend11' => $attendanceNumber, 'nr_atend12' => $attendanceNumber,
                'nr_atend13' => $attendanceNumber, 'nr_atend14' => $attendanceNumber, 'nr_atend14b' => $attendanceNumber, 'nr_atend15' => $attendanceNumber,
                'nr_atend16' => $attendanceNumber, 'shift_start2' => $shiftStart,
                'nr_atend17' => $attendanceNumber, 'nr_atend18' => $attendanceNumber, 'nr_atend19' => $attendanceNumber, 'nr_atend20' => $attendanceNumber,
                'nr_atend21' => $attendanceNumber, 'nr_atend22' => $attendanceNumber, 'nr_atend22b' => $attendanceNumber, 'nr_atend23' => $attendanceNumber,
                'nr_atend24' => $attendanceNumber, 'shift_start3' => $shiftStart
            ]);
            
            if (empty($result)) {
                return $this->getEmptyScalesObject();
            }
            
            $data = $result[0];
            
            return (object)[
                // MEWS
                'mews_score' => $data->mews_score,
                'mews_datetime' => $data->mews_datetime,
                'mews_shift' => $data->mews_shift, // ADICIONADO
                'mews_needs_assessment' => !$data->mews_current_shift,
                'mews_increased' => $this->hasIncreased($data->mews_score, $data->mews_previous),
                'mews_classification' => $this->getMewsClassification($data->mews_score),
                'ds_mews' => $this->formatDescription('MEWS', $data->mews_score, $data->mews_datetime),
                
                // BRADEN
                'braden_score' => $data->braden_score,
                'braden_datetime' => $data->braden_datetime,
                'braden_needs_assessment' => !$data->braden_done_24h,
                'braden_increased' => $this->hasIncreased($data->braden_score, $data->braden_previous),
                'braden_classification' => $this->getBradenClassification($data->braden_score),
                'ds_braden' => $this->formatDescription('Braden', $data->braden_score, $data->braden_datetime),
                
                // MORSE
                'morse_score' => $data->morse_score,
                'morse_datetime' => $data->morse_datetime,
                'morse_needs_assessment' => !$data->morse_done_24h,
                'morse_increased' => $this->hasIncreased($data->morse_score, $data->morse_previous),
                'morse_classification' => $this->getMorseClassification($data->morse_score),
                'ds_morse' => $this->formatDescription('Morse', $data->morse_score, $data->morse_datetime),
                
                // DOR
                'dor_score' => $data->dor_score,
                'dor_datetime' => $data->dor_datetime,
                'dor_shift' => $data->dor_shift, // ADICIONADO
                'dor_needs_assessment' => !$data->dor_current_shift,
                'dor_increased' => $this->hasIncreased($data->dor_score, $data->dor_previous),
                'dor_classification' => $this->getDorClassification($data->dor_score),
                'ds_dor' => $this->formatDescription('Dor', $data->dor_score, $data->dor_datetime),
                
                // TEV
                'tev_score' => $data->tev_score,
                'tev_datetime' => $data->tev_datetime,
                'tev_needs_assessment' => !$data->tev_done_24h,
                'tev_increased' => $this->hasIncreased($data->tev_score, $data->tev_previous),
                'tev_classification' => $this->getTevClassification($data->tev_score),
                'ds_tev' => $this->formatDescription('TEV', $data->tev_score, $data->tev_datetime),
                
                // PEWS
                'pews_score' => $data->pews_score,
                'pews_datetime' => $data->pews_datetime,
                'pews_shift' => $data->pews_shift, // ADICIONADO
                'pews_needs_assessment' => !$data->pews_current_shift,
                'pews_increased' => $this->hasIncreased($data->pews_score, $data->pews_previous),
                'pews_classification' => $this->getPewsClassification($data->pews_score),
                'ds_pews' => $this->formatDescription('PEWS', $data->pews_score, $data->pews_datetime),
                
                'current_shift' => $currentShift,
                'fetched_at' => now()->toDateTimeString(),
            ];
            
        } catch (\Exception $e) {
            Log::error("PatientScales: Erro ao buscar escalas", [
                'attendance_number' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
            return $this->getEmptyScalesObject();
        }
    }
    
    /**
     * Retorna dados formatados para cards
     */
    public function getScalesForCard($scalesData)
    {
        if (!$scalesData) {
            return $this->getEmptyScalesForCard();
        }
        
        return [
            'mews' => [
                'score' => $scalesData->mews_score,
                'classification' => $scalesData->mews_classification,
                'needs_assessment' => $scalesData->mews_needs_assessment,
                'increased' => $scalesData->mews_increased,
                'type' => 'turno'
            ],
            'braden' => [
                'score' => $scalesData->braden_score,
                'classification' => $scalesData->braden_classification,
                'needs_assessment' => $scalesData->braden_needs_assessment,
                'increased' => $scalesData->braden_increased,
                'type' => '24h'
            ],
            'morse' => [
                'score' => $scalesData->morse_score,
                'classification' => $scalesData->morse_classification,
                'needs_assessment' => $scalesData->morse_needs_assessment,
                'increased' => $scalesData->morse_increased,
                'type' => '24h'
            ],
            'dor' => [
                'score' => $scalesData->dor_score,
                'classification' => $scalesData->dor_classification,
                'needs_assessment' => $scalesData->dor_needs_assessment,
                'increased' => $scalesData->dor_increased,
                'type' => 'turno'
            ],
            'tev' => [
                'score' => $scalesData->tev_score,
                'classification' => $scalesData->tev_classification,
                'needs_assessment' => $scalesData->tev_needs_assessment,
                'increased' => $scalesData->tev_increased,
                'type' => '24h'
            ],
            'pews' => [
                'score' => $scalesData->pews_score,
                'classification' => $scalesData->pews_classification,
                'needs_assessment' => $scalesData->pews_needs_assessment,
                'increased' => $scalesData->pews_increased,
                'type' => 'turno'
            ],
        ];
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
                if ($now->hour >= 19) {
                    return $now->copy()->setTime(19, 0, 0)->format('Y-m-d H:i:s');
                } else {
                    return $now->copy()->subDay()->setTime(19, 0, 0)->format('Y-m-d H:i:s');
                }
        }
    }
    
    private function hasIncreased($current, $previous)
    {
        if ($current === null || $previous === null) {
            return false;
        }
        return $current > $previous;
    }
    
    private function formatDescription($scaleName, $score, $datetime)
    {
        if ($score === null) {
            return "Sem avaliação nas últimas 24h";
        }
        return ($datetime ?? 'N/A') . ' - ' . $scaleName . ': ' . $score;
    }
    
    private function getMewsClassification($score)
    {
        if ($score === null) return null;
        if ($score >= 5) return 'Crítico';
        if ($score >= 3) return 'Alerta';
        return 'Normal';
    }
    
    private function getBradenClassification($score)
    {
        if ($score === null) return null;
        if ($score <= 12) return 'Alto Risco';
        if ($score <= 14) return 'Risco Moderado';
        return 'Baixo Risco';
    }
    
    private function getMorseClassification($score)
    {
        if ($score === null) return null;
        if ($score >= 45) return 'Alto Risco';
        if ($score >= 25) return 'Risco Moderado';
        return 'Baixo Risco';
    }
    
    private function getDorClassification($score)
    {
        if ($score === null) return null;
        if ($score == 0) return 'Sem Dor';
        if ($score >= 7) return 'Dor Intensa';
        if ($score >= 4) return 'Dor Moderada';
        return 'Dor Leve';
    }
    
    private function getTevClassification($score)
    {
        if ($score === null) return null;
        if ($score >= 5) return 'Alto Risco';
        if ($score >= 2) return 'Risco Moderado';
        return 'Baixo Risco';
    }
    
    private function getPewsClassification($score)
    {
        if ($score === null) return null;
        if ($score >= 7) return 'Crítico';
        if ($score >= 4) return 'Alerta';
        return 'Normal';
    }
    
    private function getEmptyScalesObject()
    {
        return (object)[
            'mews_score' => null, 'mews_datetime' => null, 'mews_shift' => null, 'mews_needs_assessment' => true, 'mews_increased' => false, 'mews_classification' => null, 'ds_mews' => 'Sem avaliação nas últimas 24h',
            'braden_score' => null, 'braden_datetime' => null, 'braden_needs_assessment' => true, 'braden_increased' => false, 'braden_classification' => null, 'ds_braden' => 'Sem avaliação nas últimas 24h',
            'morse_score' => null, 'morse_datetime' => null, 'morse_needs_assessment' => true, 'morse_increased' => false, 'morse_classification' => null, 'ds_morse' => 'Sem avaliação nas últimas 24h',
            'dor_score' => null, 'dor_datetime' => null, 'dor_shift' => null, 'dor_needs_assessment' => true, 'dor_increased' => false, 'dor_classification' => null, 'ds_dor' => 'Sem avaliação nas últimas 24h',
            'tev_score' => null, 'tev_datetime' => null, 'tev_needs_assessment' => true, 'tev_increased' => false, 'tev_classification' => null, 'ds_tev' => 'Sem avaliação nas últimas 24h',
            'pews_score' => null, 'pews_datetime' => null, 'pews_shift' => null, 'pews_needs_assessment' => true, 'pews_increased' => false, 'pews_classification' => null, 'ds_pews' => 'Sem avaliação nas últimas 24h',
            'current_shift' => $this->getCurrentShift(),
            'fetched_at' => now()->toDateTimeString(),
        ];
    }
    
    private function getEmptyScalesForCard()
    {
        return [
            'mews' => ['score' => null, 'classification' => null, 'needs_assessment' => true, 'increased' => false, 'type' => 'turno'],
            'braden' => ['score' => null, 'classification' => null, 'needs_assessment' => true, 'increased' => false, 'type' => '24h'],
            'morse' => ['score' => null, 'classification' => null, 'needs_assessment' => true, 'increased' => false, 'type' => '24h'],
            'dor' => ['score' => null, 'classification' => null, 'needs_assessment' => true, 'increased' => false, 'type' => 'turno'],
            'tev' => ['score' => null, 'classification' => null, 'needs_assessment' => true, 'increased' => false, 'type' => '24h'],
            'pews' => ['score' => null, 'classification' => null, 'needs_assessment' => true, 'increased' => false, 'type' => 'turno'],
        ];
    }
}