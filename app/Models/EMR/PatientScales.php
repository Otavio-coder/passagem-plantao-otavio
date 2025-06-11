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
     * Get MEWS data for multiple patients
     */
    public function getMewsDataForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "mews_data_" . md5(implode(',', $attendanceNumbers));
        
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
                  AND xm.dt_avaliacao >= SYSDATE - 7
            ", $attendanceNumbers);
            
            $mewsData = [];
            foreach ($results as $result) {
                if ($result->rn == 1) { // Only latest
                    $mewsData[$result->nr_atendimento] = [
                        'mews_score' => $result->qt_pontuacao,
                        'mews_date' => $result->dt_avaliacao
                    ];
                }
            }
            
            return $mewsData;
        });
    }
    
    /**
     * Get all scales for a specific patient - SIMPLIFIED
     */
    public function getPatientScales($attendanceNumber)
    {
        $cacheKey = "patient_scales_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            $result = DB::connection('tasy')->select("
                SELECT
                    -- MEWS score and description
                    (SELECT qt_pontuacao FROM tasy.escala_mews 
                     WHERE nr_atendimento = :attendance 
                       AND dt_liberacao IS NOT NULL 
                     ORDER BY dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY) AS mews_score,
                    
                    -- MEWS description with treatment for new patients
                    CASE 
                        WHEN (SELECT TRUNC(SYSDATE - TRUNC(dt_entrada)) FROM tasy.atendimento_paciente WHERE nr_atendimento = :attendance2) = 0
                        THEN 'Paciente recém-chegado - MEWS pendente'
                        WHEN (SELECT qt_pontuacao FROM tasy.escala_mews 
                              WHERE nr_atendimento = :attendance3
                                AND dt_liberacao IS NOT NULL 
                              ORDER BY dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY) IS NULL
                        THEN 'MEWS não realizado'
                        ELSE (SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - ' || 
                                     CASE 
                                         WHEN qt_pontuacao >= 5 THEN 'CRÍTICO (MEWS: ' || qt_pontuacao || ')'
                                         WHEN qt_pontuacao >= 3 THEN 'ALERTA (MEWS: ' || qt_pontuacao || ')'
                                         ELSE 'NORMAL (MEWS: ' || qt_pontuacao || ')'
                                     END
                              FROM tasy.escala_mews 
                              WHERE nr_atendimento = :attendance4
                                AND dt_liberacao IS NOT NULL 
                              ORDER BY dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY)
                    END AS ds_mews,
                    
                    -- Braden Scale
                    COALESCE(
                        (SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - ' || SUBSTR(tasy.obter_resultado_braden(qt_ponto), 1, 50)
                         FROM tasy.ATEND_ESCALA_BRADEN 
                         WHERE nr_atendimento = :attendance5
                           AND dt_liberacao IS NOT NULL 
                         ORDER BY dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY),
                        'Não avaliado'
                    ) AS ds_braden,
                    
                    -- Morse Scale
                    COALESCE(
                        (SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - ' || SUBSTR(tasy.Obter_desc_escala_morse(qt_pontuacao), 1, 20)
                         FROM tasy.escala_morse 
                         WHERE nr_atendimento = :attendance6
                           AND dt_liberacao IS NOT NULL 
                         ORDER BY dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY),
                        'Não avaliado'
                    ) AS ds_morse,
                    
                    -- PEWS for pediatric patients
                    CASE 
                        WHEN (SELECT FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) 
                              FROM tasy.atendimento_paciente atp 
                              JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                              WHERE atp.nr_atendimento = :attendance7) < 16
                        THEN COALESCE(
                            (SELECT TO_CHAR(dt_avaliacao, 'DD/MM') || ' - PEWS: ' || qt_pontuacao
                             FROM tasy.escala_pews 
                             WHERE nr_atendimento = :attendance8
                               AND dt_liberacao IS NOT NULL 
                             ORDER BY dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY),
                            'Não avaliado'
                        )
                        ELSE NULL
                    END AS ds_pews
                       
                FROM dual
            ", [
                'attendance' => $attendanceNumber,
                'attendance2' => $attendanceNumber,
                'attendance3' => $attendanceNumber,
                'attendance4' => $attendanceNumber,
                'attendance5' => $attendanceNumber,
                'attendance6' => $attendanceNumber,
                'attendance7' => $attendanceNumber,
                'attendance8' => $attendanceNumber
            ]);
            
            return !empty($result) ? $result[0] : null;
        });
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
