<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SurgeryPendingHandler implements PendingEventHandler
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
                        ap.dt_agenda AS dt_evento,
                        ap.hr_inicio,
                        ap.ds_cirurgia,
                        ap.ie_carater_cirurgia,
                        ap.ds_observacao,
                        ap.nr_seq_proc_interno,
                        ap.cd_procedimento,
                        ap.ie_origem_proced,
                        ap.nr_seq_sala
                    FROM tasy.agenda_paciente ap
                    WHERE ap.nr_atendimento IN ($placeholders)
                        AND ap.dt_agenda >= TRUNC(SYSDATE)
                        AND ap.dt_agenda <= SYSDATE + 30
                        AND ap.ie_carater_cirurgia IS NOT NULL
                        AND ap.ie_carater_cirurgia <> 'X'
                        AND ap.ie_status_agenda NOT IN ('C', 'S')
                        AND ap.dt_executada IS NULL
                    ORDER BY ap.nr_atendimento, ap.dt_agenda, ap.hr_inicio
                ", $chunk);

                foreach ($rows as $row) {
                    if (!isset($results[$row->nr_atendimento])) continue;

                    $descricao = $row->ds_cirurgia ?? 'Agendas de Cirurgia Recente';
                    if (!empty($row->nr_seq_proc_interno)) {
                        try {
                            $procResult = DB::connection('tasy')->selectOne("
                                SELECT TASY.OBTER_DESC_PROC_INTERNO(:seq) AS descricao FROM dual
                            ", ['seq' => $row->nr_seq_proc_interno]);
                            if ($procResult && !empty($procResult->descricao)) {
                                $descricao = $procResult->descricao;
                            }
                        } catch (\Throwable $e) {
                            // Mantém descrição original
                        }
                    }

                    $carater = match($row->ie_carater_cirurgia) {
                        'E' => 'Eletiva',
                        'U' => 'Urgência',
                        'G' => 'Emergência',
                        default => 'Não informado',
                    };

                    $parts = [];
                    if (!empty($row->ds_cirurgia))   $parts[] = $row->ds_cirurgia;
                    if (!empty($row->nr_seq_sala))   $parts[] = 'Sala: ' . $row->nr_seq_sala;

                    $dtFormatted = null;
                    if ($row->dt_evento) {
                        $dtFormatted = date('d/m/Y', strtotime($row->dt_evento));
                        if (!empty($row->hr_inicio)) {
                            $dtFormatted .= ' ' . date('H:i', strtotime($row->hr_inicio));
                        }
                    }

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo'               => 'cirurgia',
                        'icone'              => 'general-surgery.svg',
                        'descricao'          => $descricao,
                        'ds_subtipo'         => 'Cirurgia ' . $carater,
                        'dt_evento'          => $row->dt_evento,
                        'dt_evento_formatted'=> $dtFormatted,
                        'ds_complemento'     => implode(' · ', $parts),
                        'carater'            => $carater,
                        'local'              => $row->ds_cirurgia ?? null,
                        'sala'               => $row->nr_seq_sala ?? null,
                        'observacoes'        => $row->ds_observacao ?? null,
                        'urgente'            => in_array($row->ie_carater_cirurgia, ['U', 'G']),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[PendingEvents] Cirurgias query failed: ' . $e->getMessage());
                continue;
            }
        }

        Log::info("[PendingEvents] Cirurgias: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
