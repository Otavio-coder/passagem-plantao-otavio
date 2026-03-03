<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientExamsRepository
{
    /**
     * Busca exames prioritários para um lote de atendimentos
     */
    public function getPriorityExamsForAttendances(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $result = [];
        foreach ($attendanceNumbers as $nr) {
            $result[$nr] = null;
        }

        foreach ($attendanceNumbers as $nr) {
            try {
                $result[$nr] = $this->getPriorityExams($nr);
            } catch (\Throwable $e) {
                Log::warning('PatientExamsRepository::getPriorityExamsForAttendances failed for ' . $nr . ': ' . $e->getMessage());
                $result[$nr] = null;
            }
        }

        return $result;
    }

    private function getPriorityExams(int $attendanceNumber): ?string
    {
        try {
            $result = DB::connection('tasy')->select("
                SELECT
                    tasy.obter_select_concatenado_bv(
                        'select obter_valor_dominio(95,proc.cd_tipo_procedimento) tipo
                        from prescr_procedimento prescrp,
                            prescr_medica prescrm,
                            procedimento proc
                        where prescrp.nr_prescricao = prescrm.nr_prescricao
                        and prescrp.cd_procedimento = proc.cd_procedimento
                        and prescrm.nr_atendimento = :nr_atend
                        and prescrp.ie_status_execucao = ''10''
                        and prescrp.dt_coleta is null
                        and prescrm.dt_liberacao is not null
                        and prescrp.ie_origem_proced <> 4
                        group by obter_valor_dominio(95,proc.cd_tipo_procedimento)',
                        'nr_atend=' || :nr_atendimento,
                        CHR(13)
                    ) AS prioridade_exames
                FROM dual
            ", ['nr_atendimento' => $attendanceNumber]);

            if ($result && !empty($result[0]->prioridade_exames)) {
                $exams = $result[0]->prioridade_exames;
                $exams = str_replace(chr(13), chr(13) . '• ', $exams);
                if (!empty(trim($exams))) {
                    $exams = '• ' . $exams;
                }
                return $exams;
            }

            return null;
        } catch (\Exception $e) {
            Log::warning("PatientExamsRepository: Error fetching priority exams for attendance {$attendanceNumber}: " . $e->getMessage());
            return null;
        }
    }
}
