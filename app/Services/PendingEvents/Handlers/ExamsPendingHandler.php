<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExamsPendingHandler implements PendingEventHandler
{
    public function handle(array &$results, array $attendances): void
    {
        if (empty($attendances)) return;

        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT
                        ap.nr_atendimento,
                        ap.dt_agenda         AS dt_evento,
                        ap.hr_inicio,
                        ap.ds_observacao,
                        ap.nr_seq_proc_interno,
                        ap.cd_procedimento,
                        ap.ie_origem_proced,
                        ap.ie_status_agenda,
                        CASE
                            WHEN ap.nr_seq_proc_interno IS NOT NULL
                                THEN tasy.obter_desc_proc_interno(ap.nr_seq_proc_interno)
                            WHEN ap.cd_procedimento IS NOT NULL
                                THEN tasy.obter_descricao_procedimento(ap.cd_procedimento, ap.ie_origem_proced)
                            ELSE NULL
                        END AS descricao
                    FROM tasy.agenda_paciente ap
                    WHERE ap.nr_atendimento IN ($placeholders)
                        AND ap.dt_agenda >= TRUNC(SYSDATE)
                        AND ap.dt_agenda <= SYSDATE + 30
                        AND ap.ie_carater_cirurgia IS NULL
                        AND ap.ie_status_agenda NOT IN ('C', 'S')
                        AND ap.dt_executada IS NULL
                        AND (ap.nr_seq_proc_interno IS NOT NULL OR ap.cd_procedimento IS NOT NULL)
                    ORDER BY ap.nr_atendimento, ap.dt_agenda, ap.hr_inicio NULLS LAST
                ", $chunk);

                foreach ($rows as $row) {
                    if (!isset($results[$row->nr_atendimento])) continue;

                    $descricao   = $row->descricao ?? $row->ds_observacao ?? 'Procedimento Agendado';
                    $dtFormatted = null;

                    if ($row->dt_evento) {
                        $dtFormatted = date('d/m/Y', strtotime($row->dt_evento));
                        if (!empty($row->hr_inicio)) {
                            $dtFormatted .= ' ' . date('H:i', strtotime($row->hr_inicio));
                        }
                    }

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo'               => 'exame',
                        'icone'              => 'tac.svg',
                        'descricao'          => substr($descricao, 0, 80),
                        'ds_subtipo'         => 'Agendamento',
                        'dt_evento'          => $row->dt_evento,
                        'dt_evento_formatted'=> $dtFormatted,
                        'urgente'            => false,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[PendingEvents] AgendaExames query failed: ' . $e->getMessage());
                continue;
            }
        }

        Log::info("[PendingEvents] AgendaExames: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
