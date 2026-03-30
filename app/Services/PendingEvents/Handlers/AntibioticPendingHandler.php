<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\DB;

/**
 * Antimicrobianos ativos (em uso hoje).
 *
 * Fonte: cpoe_material (medicamentos via CPOE) + material + medic_ficha_tecnica
 * Critério ie_antimicrobiano = 'S' na ficha técnica do material estoque.
 *
 * Performance:
 *   - Subquery correlacionada eliminada: JOIN material m_stock → medic_ficha_tecnica
 *     substitui (SELECT nr_seq_ficha_tecnica WHERE cd_material = ... AND ROWNUM = 1)
 *   - Explosão cartesiana eliminada: EXISTS(prescr_medica) no lugar de JOIN
 *     — o antigo JOIN multiplicava N prescrições × K antibióticos antes do ROW_NUMBER
 *   - ROW_NUMBER particionado por cm.nr_atendimento (correto), não por pm.nr_atendimento
 *   - Deduplicação por (atendimento, nome do material): mantém somente o registro do dia de uso mais recente
 */
class AntibioticPendingHandler extends AbstractPendingHandler
{
    protected function handlerName(): string { return 'Antibióticos'; }

    protected function processChunk(array &$results, array $chunk): void
    {
        $rows = DB::connection('tasy')->select("
            SELECT nr_atendimento, descricao, nr_dia_util, qt_dose,
                   cd_unidade_medida_dose, ie_via_aplicacao, cd_intervalo, dt_inicio_medic
            FROM (
                SELECT
                    cm.nr_atendimento,
                    INITCAP(TRIM(REGEXP_REPLACE(m.ds_material, '\\s*&&\\s*$', ''))) AS descricao,
                    cm.nr_dia_util,
                    cm.qt_dose,
                    cm.cd_unidade_medida                    AS cd_unidade_medida_dose,
                    cm.ie_via_aplicacao,
                    cm.cd_intervalo,
                    cm.dt_inicio                            AS dt_inicio_medic,
                    ROW_NUMBER() OVER (
                        PARTITION BY cm.nr_atendimento, m.ds_material
                        ORDER BY cm.nr_dia_util DESC
                    ) AS rn
                FROM tasy.cpoe_material cm
                JOIN tasy.material m          ON m.cd_material       = cm.cd_material
                JOIN tasy.material m_stock    ON m_stock.cd_material = m.cd_material_estoque
                JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia  = m_stock.nr_seq_ficha_tecnica
                WHERE mf.ie_antimicrobiano = 'S'
                    AND cm.nr_atendimento IN ({$this->placeholders($chunk)})
                    AND cm.dt_liberacao IS NOT NULL
                    AND TRUNC(cm.dt_inicio) <= TRUNC(SYSDATE)
                    AND (cm.dt_fim IS NULL OR TRUNC(cm.dt_fim) >= TRUNC(SYSDATE))
                    AND cm.dt_suspensao IS NULL
                    AND cm.nr_dia_util IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM tasy.prescr_medica pm
                        WHERE pm.nr_atendimento = cm.nr_atendimento
                          AND pm.dt_liberacao   IS NOT NULL
                          AND pm.dt_suspensao   IS NULL
                    )
            )
            WHERE rn = 1
            ORDER BY nr_atendimento, descricao
        ", $chunk);

        foreach ($rows as $row) {
            if (!isset($results[$row->nr_atendimento])) continue;

            $intervalo = '';
            if (!empty($row->cd_intervalo) && preg_match('/^(\d+)/', $row->cd_intervalo, $m)) {
                $h         = (int) $m[1];
                $intervalo = $h . '/' . $h . 'h';
            }

            $dose = '';
            if (!empty($row->qt_dose)) {
                $dose = (string) (int) $row->qt_dose;
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
                'tipo'                => 'antibiotico',
                'icone'               => 'antimicrobiano.svg',
                'descricao'           => substr($row->descricao ?? 'Antimicrobiano', 0, 60),
                'ds_subtipo'          => 'Antimicrobiano',
                'dt_evento'           => $row->dt_inicio_medic,
                'dt_evento_formatted' => $row->dt_inicio_medic
                    ? date('d/m/Y H:i', strtotime($row->dt_inicio_medic)) : null,
                'ds_complemento'      => implode(' · ', $parts),
                'urgente'             => false,
            ];
        }
    }
}
