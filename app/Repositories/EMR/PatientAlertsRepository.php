<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientAlertsRepository
{
    /**
     * Busca alertas ativos de um paciente (alerta clínico + isolamento)
     */
    public function getPatientActiveAlerts(int $attendanceNumber, int $personId): array
    {
        $results = DB::connection('tasy')->select("
            SELECT DISTINCT
                alp.ds_alerta,
                alp.nr_seq_tipo_alerta,
                alp.dt_fim_alerta,
                mi.ds_motivo AS motivo_isolamento,
                apc.dt_inicio AS dt_inicio_precaucao,
                apc.dt_termino AS dt_termino_precaucao,
                CASE
                    WHEN alp.ds_alerta IS NOT NULL THEN 1
                    WHEN mi.ds_motivo IS NOT NULL THEN 2
                    ELSE 3
                END AS alert_priority
            FROM tasy.atendimento_paciente atp
            LEFT JOIN tasy.alerta_paciente alp
                ON atp.cd_pessoa_fisica = alp.cd_pessoa_fisica
                AND alp.ds_alerta IS NOT NULL
                AND LENGTH(TRIM(alp.ds_alerta)) > 0
                AND (alp.dt_fim_alerta IS NULL OR TRUNC(alp.dt_fim_alerta) > TRUNC(SYSDATE))
            LEFT JOIN tasy.atendimento_precaucao apc
                ON atp.nr_atendimento = apc.nr_atendimento
                AND apc.dt_liberacao IS NOT NULL
                AND apc.dt_inativacao IS NULL
                AND (apc.dt_termino IS NULL OR TRUNC(apc.dt_termino) > TRUNC(SYSDATE))
            LEFT JOIN tasy.motivo_isolamento mi
                ON apc.nr_seq_motivo_isol = mi.nr_sequencia
            WHERE atp.nr_atendimento = :attendance
              AND atp.cd_pessoa_fisica = :person_id
              AND atp.dt_alta IS NULL
              AND (alp.ds_alerta IS NOT NULL OR mi.ds_motivo IS NOT NULL)
            ORDER BY alert_priority
        ", [
            'attendance' => $attendanceNumber,
            'person_id'  => $personId,
        ]);

        $alerts = [];
        $processedAlerts = [];

        foreach ($results as $result) {
            if (!empty($result->ds_alerta)) {
                $alertKey = 'alert_' . md5(trim($result->ds_alerta));
                if (!isset($processedAlerts[$alertKey])) {
                    $alerts[] = [
                        'type'     => 'ALERTA',
                        'message'  => trim($result->ds_alerta),
                        'end_date' => $result->dt_fim_alerta,
                        'severity' => 'warning',
                    ];
                    $processedAlerts[$alertKey] = true;
                }
            }

            if (!empty($result->motivo_isolamento)) {
                $isolationKey = 'isolation_' . md5(trim($result->motivo_isolamento));
                if (!isset($processedAlerts[$isolationKey])) {
                    $alerts[] = [
                        'type'       => 'ISOLAMENTO',
                        'message'    => trim($result->motivo_isolamento),
                        'start_date' => $result->dt_inicio_precaucao,
                        'end_date'   => $result->dt_termino_precaucao,
                        'severity'   => 'danger',
                    ];
                    $processedAlerts[$isolationKey] = true;
                }
            }
        }

        return array_values($alerts);
    }
}
