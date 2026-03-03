<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrescriptionPendingHandler implements PendingEventHandler
{
    public function handle(array &$results, array $attendances): void
    {
        if (empty($attendances)) return;

        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        $statusMap = [
            '10' => 'Aguardando coleta',
            '20' => 'Coletado',
            '30' => 'Em análise',
        ];

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT
                    pm.nr_atendimento,
                    pp.dt_prev_execucao                   AS dt_evento,
                    pm.dt_prescricao                      AS dt_solicitacao,
                    pm.dt_liberacao                       AS dt_autorizacao,
                    pp.nr_seq_proc_interno,
                    pp.cd_procedimento,
                    pp.ie_origem_proced,
                    pp.ie_status_execucao,
                    CASE
                        WHEN pp.nr_seq_proc_interno IS NOT NULL
                            THEN tasy.obter_desc_proc_interno(pp.nr_seq_proc_interno)
                        ELSE tasy.obter_descricao_procedimento(pp.cd_procedimento, pp.ie_origem_proced)
                    END AS descricao,
                    CASE
                        WHEN pp.nr_seq_proc_interno IS NOT NULL THEN NULL
                        ELSE tasy.obter_valor_dominio(95, (
                            SELECT cd_tipo_procedimento
                            FROM tasy.procedimento
                            WHERE cd_procedimento = pp.cd_procedimento
                            AND ROWNUM = 1
                        ))
                    END AS ds_subtipo
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp ON pp.nr_prescricao = pm.nr_prescricao
                WHERE pm.nr_atendimento IN ($placeholders)
                    AND pp.ie_status_execucao = '10'
                    AND pp.dt_coleta IS NULL
                    AND pp.dt_baixa IS NULL
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_suspensao IS NULL
                    AND pp.ie_origem_proced <> 4
                ORDER BY pm.nr_atendimento, pp.dt_prev_execucao NULLS LAST
            ", $chunk);

            $now = time();

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;

                $isInternal = !empty($row->nr_seq_proc_interno);
                $tipo       = $isInternal ? 'procedimento' : 'exame';
                $icone      = $isInternal ? 'outpatient-department.svg' : 'tac.svg';

                $tempo = '';
                if ($row->dt_evento) {
                    $tsEvento = strtotime($row->dt_evento);
                    $diff     = $tsEvento - $now;
                    if ($diff > 0) {
                        $tempo = $diff < 86400
                            ? 'em ' . round($diff / 3600) . 'h'
                            : 'em ' . floor($diff / 86400) . 'd';
                    } else {
                        $diff  = abs($diff);
                        $tempo = $diff < 86400
                            ? round($diff / 3600) . 'h em aberto'
                            : floor($diff / 86400) . 'd em aberto';
                    }
                } elseif ($row->dt_autorizacao) {
                    $diff  = $now - strtotime($row->dt_autorizacao);
                    $tempo = $diff < 86400
                        ? round($diff / 3600) . 'h em aberto'
                        : floor($diff / 86400) . 'd em aberto';
                }

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => $tipo,
                    'icone'              => $icone,
                    'descricao'          => substr($row->descricao ?? '', 0, 80),
                    'ds_subtipo'         => $row->ds_subtipo ?? null,
                    'dt_evento'          => $row->dt_evento,
                    'dt_evento_formatted'=> $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                    'dt_solicitacao'     => $row->dt_solicitacao ? date('d/m/Y H:i', strtotime($row->dt_solicitacao)) : null,
                    'dt_autorizacao'     => $row->dt_autorizacao ? date('d/m/Y H:i', strtotime($row->dt_autorizacao)) : null,
                    'tempo_pendente'     => $tempo,
                    'status_laudo'       => $statusMap[$row->ie_status_execucao] ?? 'Pendente',
                    'urgente'            => false,
                ];
            }
        }

        Log::info("[PendingEvents] Prescrições: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
