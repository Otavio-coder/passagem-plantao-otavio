<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sessões de quimioterapia agendadas nos próximos 30 dias.
 *
 * Fonte: agenda_quimioterapia_pep_v (view Tasy que agrega sessões por cd_pessoa_fisica)
 *
 * Performance:
 *   - Versão anterior fazia 2 round-trips por chunk:
 *       1. SELECT cd_pessoa_fisica FROM atendimento_paciente WHERE nr_atendimento IN (...)
 *       2. SELECT ... FROM agenda_quimioterapia_pep_v WHERE cd_pessoa_fisica IN (...)
 *   - Versão atual: único JOIN no Oracle — atendimento_paciente é consultado uma vez
 *     com index scan em nr_atendimento (PK) e o otimizador aplica o hash join com a view.
 */
class ChemotherapyPendingHandler extends AbstractPendingHandler
{
    protected function handlerName(): string { return 'Quimioterapia'; }

    protected function processChunk(array &$results, array $chunk): void
    {
        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.nr_atendimento,
                    aq.dt_agenda            AS dt_evento,
                    aq.ds_local,
                    aq.nm_medico_resp,
                    aq.ds_protocolo_medic,
                    aq.nr_ciclo
                FROM tasy.atendimento_paciente ap
                JOIN tasy.agenda_quimioterapia_pep_v aq
                    ON aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
                WHERE ap.nr_atendimento IN ({$this->placeholders($chunk)})
                    AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + 30
                ORDER BY ap.nr_atendimento, aq.dt_agenda
            ", $chunk);

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;

                $descricao = 'Quimioterapia';
                if (!empty($row->ds_protocolo_medic)) {
                    $descricao .= ' - ' . $row->ds_protocolo_medic;
                }
                if (!empty($row->nr_ciclo)) {
                    $descricao .= ' (Ciclo ' . $row->nr_ciclo . ')';
                }

                $parts = [];
                if (!empty($row->ds_local))       $parts[] = $row->ds_local;
                if (!empty($row->nm_medico_resp)) $parts[] = $row->nm_medico_resp;

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'                => 'quimioterapia',
                    'icone'               => 'quimioterapia.svg',
                    'descricao'           => $descricao,
                    'ds_subtipo'          => 'Quimioterapia',
                    'dt_evento'           => $row->dt_evento,
                    'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                    'ds_complemento'      => implode(' · ', $parts),
                    'local'               => $row->ds_local ?? null,
                    'medico'              => $row->nm_medico_resp ?? null,
                    'ciclo'               => $row->nr_ciclo ?? null,
                    'urgente'             => false,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] Quimioterapia query failed: ' . $e->getMessage());
        }
    }
}
