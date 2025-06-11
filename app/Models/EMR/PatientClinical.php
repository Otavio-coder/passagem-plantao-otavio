<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PatientClinical extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    /**
     * Get clinical alerts for multiple patients (allergies, isolation, etc.)
     */
    public function getClinicalAlertsForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "clinical_alerts_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 300, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    atp.nr_atendimento,
                    CASE WHEN EXISTS(
                        SELECT 1 FROM tasy.W_PAN_PACIENTE wpp 
                        WHERE wpp.cd_pessoa_paciente = atp.cd_pessoa_fisica 
                        AND wpp.ds_alergias IS NOT NULL 
                        AND LENGTH(TRIM(wpp.ds_alergias)) > 0
                    ) THEN 1 ELSE 0 END AS has_allergy,
                    
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
            
            $alerts = [];
            foreach ($results as $result) {
                $alerts[$result->nr_atendimento] = [
                    'has_allergy' => (bool)$result->has_allergy,
                    'has_isolation' => (bool)$result->has_isolation
                ];
            }
            
            return $alerts;
        });
    }
    
    /**
     * Get detailed clinical information for a patient
     */
    public function getPatientClinicalDetails($attendanceNumber)
    {
        $cacheKey = "patient_clinical_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            $result = DB::connection('tasy')->select("
                SELECT
                    -- Diagnoses
                    COALESCE(
                        (SELECT SUBSTR(LISTAGG(SUBSTR(tasy.PE_obter_desc_diag(d.nr_seq_diag, 'DI'), 1, 100), ' | ') 
                                WITHIN GROUP (ORDER BY 1), 1, 400)
                         FROM tasy.pe_prescr_diag d
                         JOIN tasy.pe_prescricao p ON d.nr_seq_prescr = p.nr_sequencia
                         WHERE p.nr_atendimento = atp.nr_atendimento
                           AND p.dt_prescricao = (SELECT MAX(c.dt_prescricao) FROM tasy.pe_prescricao c WHERE c.nr_atendimento = atp.nr_atendimento)
                         AND ROWNUM <= 3),
                        'Sem diagnósticos'
                    ) AS diag,
                    
                    -- Allergies
                    COALESCE(
                        (SELECT SUBSTR(ds_alergias, 1, 300) FROM tasy.W_PAN_PACIENTE 
                         WHERE cd_pessoa_paciente = atp.cd_pessoa_fisica AND ROWNUM = 1),
                        'Sem alergias registradas'
                    ) AS alergias_detalhadas,
                    
                    -- Devices
                    COALESCE(
                        (SELECT LISTAGG(SUBSTR(tasy.obter_descricao_padrao('DISPOSITIVO', 'DS_DISPOSITIVO', NR_SEQ_DISPOSITIVO), 1, 60), ' | ') 
                                WITHIN GROUP (ORDER BY 1)
                         FROM tasy.ATEND_PAC_DISPOSITIVO 
                         WHERE nr_atendimento = atp.nr_atendimento 
                           AND dt_retirada IS NULL),
                        'Nenhum dispositivo'
                    ) AS dispositivos,
                    
                    -- Isolation
                    (SELECT CASE WHEN COUNT(*) > 0 THEN 'Sim - Precauções ativas' ELSE 'Não' END
                     FROM tasy.atendimento_precaucao ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL) AS ds_isolamento,
                       
                    -- Priority exams
                    COALESCE(
                        (SELECT RTRIM(LISTAGG(DISTINCT tasy.obter_valor_dominio(95, proc.cd_tipo_procedimento), ', ') 
                                WITHIN GROUP (ORDER BY tasy.obter_valor_dominio(95, proc.cd_tipo_procedimento)), ', ')
                         FROM tasy.prescr_procedimento pp
                         JOIN tasy.prescr_medica pm ON pp.nr_prescricao = pm.nr_prescricao
                         JOIN tasy.procedimento proc ON pp.cd_procedimento = proc.cd_procedimento
                         WHERE pm.nr_atendimento = atp.nr_atendimento
                           AND pp.ie_status_execucao = '10'
                           AND pp.dt_coleta IS NULL
                           AND pm.dt_liberacao IS NOT NULL),
                        'Nenhum exame prioritário'
                    ) AS prioridade_exames,
                       
                    -- Default values for missing fields
                    'Sem registro' AS ds_queda,
                    'Sem consultas agendadas' AS futureinquiries,
                    'Nenhuma precaução' AS precaucoes,
                    'Nenhum antimicrobiano' AS materiais,
                    'Nenhum exame agendado' AS exams
                       
                FROM tasy.atendimento_paciente atp
                WHERE atp.nr_atendimento = :attendance
            ", ['attendance' => $attendanceNumber]);
            
            return !empty($result) ? $result[0] : null;
        });
    }
}
