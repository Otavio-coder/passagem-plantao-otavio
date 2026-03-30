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
            $result = DB::connection('tasy')->select(
                'SELECT tasy.OBTER_EXAM_PEND_BAR(:nr_atendimento) AS exames FROM dual',
                ['nr_atendimento' => $attendanceNumber]
            );

            $value = trim((string) ($result[0]->exames ?? ''));

            return $value !== '' ? $value : null;
        } catch (\Exception $e) {
            Log::warning("PatientExamsRepository: Error fetching priority exams for attendance {$attendanceNumber}: " . $e->getMessage());
            return null;
        }
    }
}
