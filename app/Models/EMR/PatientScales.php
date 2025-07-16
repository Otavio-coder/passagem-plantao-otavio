<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PatientScales extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    

    /**
     * Get today's most recent MEWS data for multiple patients
     * Returns "Não Medido" if not available for today
     */
    public function getMewsDataForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "mews_data_today_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 300, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    xm.nr_atendimento,
                    xm.qt_pontuacao,
                    xm.dt_avaliacao,
                    ROW_NUMBER() OVER (
                        PARTITION BY xm.nr_atendimento
                        ORDER BY xm.dt_avaliacao DESC
                    ) AS rn
                FROM tasy.escala_mews xm
                WHERE xm.nr_atendimento IN ({$placeholders})
                AND xm.dt_inativacao IS NULL 
                AND xm.dt_liberacao IS NOT NULL
                AND xm.dt_avaliacao >= TRUNC(SYSDATE)
                AND xm.dt_avaliacao < TRUNC(SYSDATE) + 1
            ", $attendanceNumbers);
            
            $mewsData = [];
            foreach ($attendanceNumbers as $nr_atendimento) {
                $mewsData[$nr_atendimento] = [
                    'mews_score' => null,
                    'mews_date' => null,
                    'mews_alert' => 'Não aferido', // alerta curto
                    'mews_display' => 'MEWS: não aferido'
                ];
            }
            foreach ($results as $result) {
                if ($result->rn == 1) { // Only latest for today
                    $hora = date('H:i', strtotime($result->dt_avaliacao));
                    $pontuacao = $result->qt_pontuacao;
                    $mewsData[$result->nr_atendimento] = [
                        'mews_score' => $pontuacao,
                        'mews_date' => $result->dt_avaliacao,
                        'mews_alert' => null,
                        'mews_display' => "MEWS: {$pontuacao} ({$hora})"
                    ];
                }
            }
            
            return $mewsData;
        });
    }
    
    /**
     * Get all scales for a specific patient 
     */
    public function getPatientScales($attendanceNumber)
    {
        $cacheKey = "patient_scales_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            return (object) [
                'mews_score' => $this->getMewsScore($attendanceNumber),
                'ds_mews' => $this->getMewsDescription($attendanceNumber),
                'ds_braden' => $this->getBradenDescription($attendanceNumber),
                'ds_morse' => $this->getMorseDescription($attendanceNumber),
                'ds_pews' => $this->getPewsDescription($attendanceNumber),
                'ds_dor' => $this->getPainScaleDescription($attendanceNumber),
                'ds_tev' => $this->getTevDescription($attendanceNumber)
            ];
        });
    }
    
    /**
     * Get MEWS score for a patient (only today's most recent)
     * Returns "Não Medido" if not available for today
     */
    private function getMewsScore($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT qt_pontuacao 
            FROM tasy.escala_mews 
            WHERE nr_atendimento = ? 
            AND dt_liberacao IS NOT NULL 
            AND dt_inativacao IS NULL
            AND TRUNC(dt_avaliacao) = TRUNC(SYSDATE)
            ORDER BY dt_avaliacao DESC 
            FETCH FIRST 1 ROWS ONLY
        ", [$attendanceNumber]);
        
        return $result && isset($result[0]->qt_pontuacao)
            ? $result[0]->qt_pontuacao
            : null;
    }

    /**
     * Get MEWS description for a patient - OPTIMIZED
     */
    private function getMewsDescription($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT
                CASE WHEN TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) = 0 THEN 1 ELSE 0 END AS is_new_patient,
                em.qt_pontuacao AS mews_score,
                em.dt_avaliacao AS mews_date
            FROM tasy.atendimento_paciente atp
            LEFT JOIN (
                SELECT nr_atendimento, qt_pontuacao, dt_avaliacao,
                    ROW_NUMBER() OVER (ORDER BY dt_avaliacao DESC) AS rn
                FROM tasy.escala_mews 
                WHERE nr_atendimento = ?
                AND dt_liberacao IS NOT NULL 
                AND dt_inativacao IS NULL
                AND TRUNC(dt_avaliacao) = TRUNC(SYSDATE)
            ) em ON em.nr_atendimento = atp.nr_atendimento AND em.rn = 1
            WHERE atp.nr_atendimento = ?
        ", [$attendanceNumber, $attendanceNumber]);

        if (!$result) {
            return 'MEWS não realizado hoje';
        }

        $data = $result[0];

        // Novo paciente
        if ($data->is_new_patient) {
            return 'Paciente recém-chegado - MEWS pendente';
        }

        // Sem MEWS para hoje
        if ($data->mews_score === null) {
            return 'ALERTA: MEWS não preenchido para o dia de hoje!';
        }

        // Formata descrição
        $date = date('d/m H:i', strtotime($data->mews_date));
        $classification = $this->getMewsClassification($data->mews_score);

        return "{$date} - {$classification} (MEWS: {$data->mews_score})";
    }
    
    /**
     * Get MEWS classification
     */
    private function getMewsClassification($score)
    {
        if ($score >= 5) return 'CRÍTICO';
        if ($score >= 3) return 'ALERTA';
        return 'NORMAL';
    }
    
    /**
     * Get Braden scale description for a patient
     */
    private function getBradenDescription($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - ' || SUBSTR(tasy.obter_resultado_braden(qt_ponto), 1, 50) AS ds_braden
            FROM tasy.ATEND_ESCALA_BRADEN 
            WHERE nr_atendimento = ? 
              AND dt_liberacao IS NOT NULL 
              AND dt_inativacao IS NULL
            ORDER BY dt_avaliacao DESC 
            FETCH FIRST 1 ROWS ONLY
        ", [$attendanceNumber]);
        
        return $result ? $result[0]->ds_braden : 'Não avaliado';
    }
    
    /**
     * Get Morse scale description for a patient
     */
    private function getMorseDescription($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - ' || SUBSTR(tasy.Obter_desc_escala_morse(qt_pontuacao), 1, 20) AS ds_morse
            FROM tasy.escala_morse 
            WHERE nr_atendimento = ? 
              AND dt_liberacao IS NOT NULL 
              AND dt_inativacao IS NULL
            ORDER BY dt_avaliacao DESC 
            FETCH FIRST 1 ROWS ONLY
        ", [$attendanceNumber]);
        
        return $result ? $result[0]->ds_morse : 'Não avaliado';
    }
    
    /**
     * Get PEWS description for pediatric patients
     */
    private function getPewsDescription($attendanceNumber)
    {
        // Check if patient is pediatric first
        $ageResult = DB::connection('tasy')->select("
            SELECT FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS age
            FROM tasy.atendimento_paciente atp 
            JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
            WHERE atp.nr_atendimento = ?
        ", [$attendanceNumber]);
        
        if (!$ageResult || $ageResult[0]->age >= 16) {
            return null; // Not pediatric patient
        }
        
        // Get PEWS data for pediatric patient
        $result = DB::connection('tasy')->select("
            SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - PEWS: ' || qt_pontuacao AS ds_pews
            FROM tasy.escala_pews 
            WHERE nr_atendimento = ? 
              AND dt_liberacao IS NOT NULL 
              AND dt_inativacao IS NULL
            ORDER BY dt_avaliacao DESC 
            FETCH FIRST 1 ROWS ONLY
        ", [$attendanceNumber]);
        
        return $result ? $result[0]->ds_pews : 'Não avaliado';
    }
    
    /**
     * Get Pain scale description for a patient - NEW
     */
    private function getPainScaleDescription($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT DISTINCT
                TO_CHAR(TRUNC(asv.dt_sinal_vital), 'DD/MM') || ' - Dor: ' || asv.qt_escala_dor AS ds_dor
            FROM tasy.atendimento_sinal_vital asv
            WHERE asv.nr_atendimento = ?
              AND asv.nr_sequencia = (
                  SELECT MAX(sub_asv.nr_sequencia)
                  FROM tasy.atendimento_sinal_vital sub_asv
                  WHERE sub_asv.nr_atendimento = ?
                    AND sub_asv.dt_inativacao IS NULL
                    AND sub_asv.qt_escala_dor IS NOT NULL
                    AND sub_asv.dt_liberacao IS NOT NULL
              )
        ", [$attendanceNumber, $attendanceNumber]);
        
        return $result ? $result[0]->ds_dor : 'Não avaliado';
    }
    
    /**
     * Get TEV scale description for a patient - NEW
     */
    private function getTevDescription($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT
                TO_CHAR(dt_avaliacao, 'DD/MM') || ' - TEV: ' || 
                SUBSTR(tasy.obter_valor_dominio(2900, et.ie_risco), 1, 255) AS ds_tev
            FROM tasy.escala_tev et
            WHERE et.nr_atendimento = ?
              AND et.dt_inativacao IS NULL
              AND et.nr_sequencia = (
                  SELECT MAX(e.nr_sequencia)
                  FROM tasy.escala_tev e
                  WHERE e.nr_atendimento = ?
                    AND e.dt_liberacao IS NOT NULL
                    AND e.dt_inativacao IS NULL
              )
        ", [$attendanceNumber, $attendanceNumber]);
        
        return $result ? $result[0]->ds_tev : 'Não avaliado';
    }
    
    /**
     * Apply MEWS filter to patient list
     */
    public function applyMewsFilter($patients, $mewsFilter)
    {
        if ($mewsFilter === 'all') {
            return $patients;
        }
        
        return $patients->filter(function($patient) use ($mewsFilter) {
            if (!$patient->has_patient) {
                return false; // Exclude empty beds from MEWS filters
            }
            
            $mewsScore = $patient->mews_score;
            
            switch ($mewsFilter) {
                case 'critical':
                    return $mewsScore !== null && $mewsScore >= 5;
                case 'warning':
                    return $mewsScore !== null && $mewsScore >= 3 && $mewsScore < 5;
                case 'normal':
                    return $mewsScore === null || $mewsScore < 3;
                default:
                    return true;
            }
        });
    }
}
