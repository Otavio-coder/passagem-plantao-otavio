<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChemotherapyPendingHandler implements PendingEventHandler
{
    public function handle(array &$results, array $attendances): void
    {
        if (empty($attendances)) return;

        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                $personRows = DB::connection('tasy')->select("
                    SELECT nr_atendimento, cd_pessoa_fisica
                    FROM tasy.atendimento_paciente
                    WHERE nr_atendimento IN ($placeholders)
                ", $chunk);

                $attendanceToPerson = [];
                foreach ($personRows as $row) {
                    $attendanceToPerson[$row->nr_atendimento] = $row->cd_pessoa_fisica;
                }

                $personIds = array_unique(array_filter(array_values($attendanceToPerson)));
                if (empty($personIds)) continue;

                $personPlaceholders = implode(',', array_fill(0, count($personIds), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT
                        aq.cd_pessoa_fisica,
                        aq.dt_agenda AS dt_evento,
                        aq.ds_local,
                        aq.nm_medico_resp,
                        aq.ds_protocolo_medic,
                        aq.nr_ciclo
                    FROM tasy.agenda_quimioterapia_pep_v aq
                    WHERE aq.cd_pessoa_fisica IN ($personPlaceholders)
                        AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + 30
                    ORDER BY aq.cd_pessoa_fisica, aq.dt_agenda
                ", $personIds);

                foreach ($rows as $row) {
                    $nr = array_search($row->cd_pessoa_fisica, $attendanceToPerson);
                    if ($nr === false || !isset($results[$nr])) continue;

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

                    $results[$nr]['events'][] = [
                        'tipo'               => 'quimioterapia',
                        'icone'              => 'quimioterapia.svg',
                        'descricao'          => $descricao,
                        'ds_subtipo'         => 'Quimioterapia',
                        'dt_evento'          => $row->dt_evento,
                        'dt_evento_formatted'=> $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                        'ds_complemento'     => implode(' · ', $parts),
                        'local'              => $row->ds_local ?? null,
                        'medico'             => $row->nm_medico_resp ?? null,
                        'ciclo'              => $row->nr_ciclo ?? null,
                        'urgente'            => false,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[PendingEvents] Quimioterapia query failed: ' . $e->getMessage());
                continue;
            }
        }

        Log::info("[PendingEvents] Quimioterapia: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
