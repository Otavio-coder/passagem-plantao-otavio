<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientExamsRepository
{
    /**
     * Busca exames laboratoriais pendentes para um lote de atendimentos.
     *
     * Substitui TASY.OBTER_EXAM_PEND_BAR() que sofria de ORA-06502 (buffer VARCHAR2
     * muito pequeno para pacientes com muitos exames). A lógica é equivalente à de
     * obter_exames_pendentes(): mesma origem (prescr_medica + prescr_procedimento +
     * exame_laboratorio + lab_parametro), mesmos filtros de status alinhados com
     * OBTER_EXAM_PEND_BAR (ie_suspenso<>'S', dt_cancelamento IS NULL, ie_status_atend<35,
     * NOT EXISTS result_laboratorio coletado, status NOT IN ('40','R','C','BE')).
     * A concatenação é feita em PHP, eliminando o limite de buffer Oracle.
     *
     * @param  int[]  $attendanceNumbers
     * @return array<int, string|null>
     */
    public function getPriorityExamsForAttendances(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $result = array_fill_keys($attendanceNumbers, null);

        try {
            $placeholders = implode(',', array_fill(0, count($attendanceNumbers), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT
                    pm.nr_atendimento,
                    el.nm_exame
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp
                    ON pp.nr_prescricao = pm.nr_prescricao
                JOIN tasy.exame_laboratorio el
                    ON el.nr_seq_exame = pp.nr_seq_exame
                JOIN tasy.lab_parametro lp
                    ON lp.cd_estabelecimento = pm.cd_estabelecimento
                WHERE pm.nr_atendimento IN ({$placeholders})
                    AND pp.ie_status_execucao NOT IN ('40', 'R', 'C', 'BE')
                    AND pp.dt_baixa        IS NULL
                    AND pp.dt_cancelamento IS NULL
                    AND pp.ie_suspenso     <> 'S'
                    AND pp.ie_status_atend < 35
                    AND pm.dt_liberacao    IS NOT NULL
                    AND pm.dt_suspensao    IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM tasy.result_laboratorio rl
                        WHERE rl.nr_prescricao     = pm.nr_prescricao
                          AND rl.nr_seq_prescricao = pp.nr_sequencia
                          AND rl.dt_coleta         IS NOT NULL
                    )
                ORDER BY pm.nr_atendimento, el.nm_exame
            ", $attendanceNumbers);

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row->nr_atendimento][] = $row->nm_exame;
            }

            foreach ($grouped as $nr => $names) {
                $result[$nr] = 'Exames Pendentes: '.implode(' - ', $names);
            }
        } catch (\Throwable $e) {
            Log::warning('PatientExamsRepository batch query failed', [
                'exception' => $e,
                'attendance_count' => count($attendanceNumbers),
            ]);
        }

        return $result;
    }
}
