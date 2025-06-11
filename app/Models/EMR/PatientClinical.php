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
    
    /**
     * Get active alerts for a patient (allergies and isolation warnings)
     * Prevents duplicates and filters out expired alerts properly
     */
    public function getPatientActiveAlerts($attendanceNumber, $personId)
    {
        $cacheKey = "patient_alerts_{$attendanceNumber}_{$personId}";
        
        return Cache::remember($cacheKey, 300, function() use ($attendanceNumber, $personId) {
            $results = DB::connection('tasy')->select("
                SELECT DISTINCT
                    ua.cd_unidade_basica AS leito,
                    ua.nr_atendimento,
                    atp.cd_pessoa_fisica,
                    
                    alp.ds_alerta,
                    alp.nr_seq_tipo_alerta,
                    alp.dt_fim_alerta,
                    
                    apc.nr_seq_motivo_isol,
                    apc.dt_inicio AS dt_inicio_precaucao,
                    apc.dt_termino AS dt_termino_precaucao,
                    
                    mi.ds_motivo AS motivo_isolamento,
                    
                    -- Priority for ordering (alerts first, then isolation)
                    CASE 
                        WHEN alp.ds_alerta IS NOT NULL THEN 1
                        WHEN mi.ds_motivo IS NOT NULL THEN 2
                        ELSE 3
                    END AS alert_priority
                FROM tasy.atendimento_paciente atp
                JOIN tasy.unidade_atendimento ua 
                    ON ua.nr_atendimento = atp.nr_atendimento
                    AND ua.ie_situacao = 'A'
                LEFT JOIN tasy.alerta_paciente alp 
                    ON atp.cd_pessoa_fisica = alp.cd_pessoa_fisica
                    AND alp.ds_alerta IS NOT NULL
                    AND LENGTH(TRIM(alp.ds_alerta)) > 0
                    -- Only active alerts: either no end date OR end date is in the future
                    AND (alp.dt_fim_alerta IS NULL OR TRUNC(alp.dt_fim_alerta) > TRUNC(SYSDATE))
                LEFT JOIN tasy.atendimento_precaucao apc 
                    ON ua.nr_atendimento = apc.nr_atendimento
                    AND apc.dt_liberacao IS NOT NULL
                    AND apc.dt_inativacao IS NULL
                    -- Only active precautions: either no end date OR end date is in the future
                    AND (apc.dt_termino IS NULL OR TRUNC(apc.dt_termino) > TRUNC(SYSDATE))
                LEFT JOIN tasy.motivo_isolamento mi 
                    ON apc.nr_seq_motivo_isol = mi.nr_sequencia
                    AND mi.ds_motivo IS NOT NULL
                    AND LENGTH(TRIM(mi.ds_motivo)) > 0
                WHERE atp.nr_atendimento = :attendance
                  AND atp.cd_pessoa_fisica = :person_id
                  AND atp.dt_alta IS NULL
                  AND (
                    (alp.ds_alerta IS NOT NULL AND LENGTH(TRIM(alp.ds_alerta)) > 0)
                    OR 
                    (mi.ds_motivo IS NOT NULL AND LENGTH(TRIM(mi.ds_motivo)) > 0)
                  )
                ORDER BY alert_priority, 
                         CASE WHEN alp.dt_fim_alerta IS NULL THEN 0 ELSE 1 END, -- Active alerts first
                         alp.dt_fim_alerta DESC,
                         CASE WHEN apc.dt_termino IS NULL THEN 0 ELSE 1 END, -- Active isolation first
                         apc.dt_termino DESC
            ", [
                'attendance' => $attendanceNumber,
                'person_id' => $personId
            ]);
            
            $alerts = [];
            $processedAlerts = []; // Track processed alerts to prevent duplicates
            
            foreach ($results as $result) {
                // Process ALERTA type
                if (!empty($result->ds_alerta) && !empty(trim($result->ds_alerta))) {
                    // Create unique key based on alert content and type
                    $alertKey = 'alert_' . ($result->nr_seq_tipo_alerta ?? 'null') . '_' . md5(trim($result->ds_alerta));
                    
                    if (!isset($processedAlerts[$alertKey])) {
                        $alerts[] = [
                            'type' => 'ALERTA',
                            'message' => trim($result->ds_alerta),
                            'end_date' => $result->dt_fim_alerta,
                            'severity' => 'warning',
                            'type_id' => $result->nr_seq_tipo_alerta,
                            'unique_key' => $alertKey,
                            'is_active' => $result->dt_fim_alerta === null || strtotime($result->dt_fim_alerta) > time()
                        ];
                        $processedAlerts[$alertKey] = true;
                    }
                }
                
                // Process ISOLAMENTO type
                if (!empty($result->motivo_isolamento) && !empty(trim($result->motivo_isolamento))) {
                    // Create unique key based on isolation content and dates
                    $isolationKey = 'isolation_' . ($result->nr_seq_motivo_isol ?? 'null') . '_' . 
                                   md5(trim($result->motivo_isolamento) . '_' . ($result->dt_inicio_precaucao ?? ''));
                    
                    if (!isset($processedAlerts[$isolationKey])) {
                        $alerts[] = [
                            'type' => 'ISOLAMENTO',
                            'message' => trim($result->motivo_isolamento),
                            'start_date' => $result->dt_inicio_precaucao,
                            'end_date' => $result->dt_termino_precaucao,
                            'severity' => 'danger',
                            'motivo_id' => $result->nr_seq_motivo_isol,
                            'unique_key' => $isolationKey,
                            'is_active' => $result->dt_termino_precaucao === null || strtotime($result->dt_termino_precaucao) > time()
                        ];
                        $processedAlerts[$isolationKey] = true;
                    }
                }
            }
            
            // Filter out any alerts that somehow got through but are expired
            $alerts = array_filter($alerts, function($alert) {
                if (isset($alert['end_date']) && $alert['end_date'] !== null) {
                    return strtotime($alert['end_date']) > time();
                }
                return true; // Keep alerts without end dates (they're active)
            });
            
            // Re-index array after filtering
            return array_values($alerts);
        });
    }
}
