<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AntibioticPendingHandler implements PendingEventHandler
{
    public function handle(array &$results, array $attendances): void
    {
        if (empty($attendances)) return;

        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT nr_atendimento, descricao, nr_dia_util, qt_dose,
                       cd_unidade_medida_dose, ie_via_aplicacao, cd_intervalo, dt_inicio_medic
                FROM (
                    SELECT
                        pm.nr_atendimento,
                        INITCAP(TRIM(REGEXP_REPLACE(m.ds_material, '\\s*&&\\s*$', ''))) AS descricao,
                        pt.nr_dia_util,
                        pt.qt_dose,
                        pt.cd_unidade_medida_dose,
                        pt.ie_via_aplicacao,
                        pt.cd_intervalo,
                        pt.dt_inicio_medic,
                        ROW_NUMBER() OVER (
                            PARTITION BY pm.nr_atendimento, m.ds_material
                            ORDER BY pt.nr_dia_util DESC
                        ) AS rn
                    FROM tasy.material m
                    JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia = (
                        SELECT nr_seq_ficha_tecnica
                        FROM tasy.material
                        WHERE cd_material = m.cd_material_estoque
                    )
                    JOIN tasy.prescr_material pt ON m.cd_material = pt.cd_material
                    JOIN tasy.prescr_medica pm ON pm.nr_prescricao = pt.nr_prescricao
                    WHERE mf.ie_antimicrobiano = 'S'
                        AND pm.nr_atendimento IN ($placeholders)
                        AND pm.dt_liberacao IS NOT NULL
                        AND pm.dt_validade_prescr >= SYSDATE
                        AND pm.dt_suspensao IS NULL
                        AND pt.dt_suspensao IS NULL
                        AND pt.nr_dia_util IS NOT NULL
                )
                WHERE rn = 1
                ORDER BY nr_atendimento, descricao
            ", $chunk);

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;

                $intervalo = '';
                if (!empty($row->cd_intervalo) && preg_match('/^(\d+)/', $row->cd_intervalo, $m)) {
                    $h         = (int)$m[1];
                    $intervalo = $h . '/' . $h . 'h';
                }

                $dose = '';
                if (!empty($row->qt_dose)) {
                    $dose = (string)(int)$row->qt_dose;
                    if (!empty($row->cd_unidade_medida_dose)) {
                        $dose .= $row->cd_unidade_medida_dose;
                    }
                }

                $parts = [];
                if ($row->nr_dia_util)              $parts[] = 'Dia ' . $row->nr_dia_util;
                if ($dose)                          $parts[] = $dose;
                if (!empty($row->ie_via_aplicacao)) $parts[] = $row->ie_via_aplicacao;
                if ($intervalo)                     $parts[] = $intervalo;

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => 'antibiotico',
                    'icone'              => 'antimicrobiano.svg',
                    'descricao'          => substr($row->descricao ?? 'Antimicrobiano', 0, 60),
                    'ds_subtipo'         => 'Antimicrobiano',
                    'dt_evento'          => $row->dt_inicio_medic,
                    'dt_evento_formatted'=> $row->dt_inicio_medic
                        ? date('d/m/Y H:i', strtotime($row->dt_inicio_medic)) : null,
                    'ds_complemento'     => implode(' · ', $parts),
                    'urgente'            => false,
                ];
            }
        }

        Log::info("[PendingEvents] Antibióticos: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
