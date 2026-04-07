<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\DB;

/**
 * Antimicrobianos com slots individuais de administração pendentes (hoje).
 *
 * Fonte: PRESCR_MAT_HOR (horários aprazados) + PRESCR_MAT_ALTERACAO (execuções)
 *        ligados via PRESCR_MATERIAL.NR_SEQ_MAT_CPOE → CPOE_MATERIAL
 *
 * Usa PRESCR_MAT_ALTERACAO diretamente ao invés de ADEP_V (view UNION de vários tipos)
 * para evitar custo desnecessário dos joins de pessoa_fisica e valor_dominio.
 *
 * Cada linha = um horário de administração pendente no dia atual.
 * Slots já administrados/conferidos/coletados (priority >= 400) são excluídos via HAVING.
 * Duplicatas do LEFT JOIN são resolvidas com GROUP BY + MAX(priority).
 */
class AntibioticPendingHandler extends AbstractPendingHandler
{
    protected function handlerName(): string
    {
        return 'Antibióticos';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        $rows = DB::connection('tasy')->select("
            SELECT
                nr_atendimento,
                med_id,
                descricao,
                dt_horario,
                qt_dose,
                cd_unidade_medida_dose,
                ie_via_aplicacao,
                nr_dia_util,
                MAX(priority) AS priority
            FROM (
                -- Antimicrobianos ativos hoje: identifica med_ids primeiro para restringir PRESCR_MAT_HOR
                SELECT
                    cm.nr_atendimento,
                    cm.nr_sequencia                                                          AS med_id,
                    INITCAP(TRIM(REGEXP_REPLACE(m.ds_material, '\\s*&&\\s*\$', '')))        AS descricao,
                    pmh.dt_horario,
                    cm.qt_dose,
                    cm.cd_unidade_medida                                                     AS cd_unidade_medida_dose,
                    cm.ie_via_aplicacao,
                    cm.nr_dia_util,
                    -- PRESCR_MAT_ALTERACAO.IE_ALTERACAO = ie_execucao em ADEP_V
                    CASE pma.ie_alteracao
                        WHEN 3  THEN 600   -- Administrado
                        WHEN 58 THEN 500   -- Conferido
                        WHEN 8  THEN 400   -- Coletado
                        WHEN 38 THEN 300   -- Recusado
                        WHEN 4  THEN 200   -- Desfeito
                        WHEN 10 THEN  30   -- Reaprazado
                        WHEN 15 THEN  20   -- Aprazado
                        ELSE           1   -- Pendente / sem baixa
                    END                                                                      AS priority
                FROM tasy.cpoe_material cm
                JOIN tasy.material m          ON m.cd_material       = cm.cd_material
                JOIN tasy.material m_stock    ON m_stock.cd_material = m.cd_material_estoque
                JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia  = m_stock.nr_seq_ficha_tecnica
                JOIN tasy.prescr_material pm  ON pm.nr_seq_mat_cpoe  = cm.nr_sequencia
                JOIN tasy.prescr_mat_hor pmh
                    ON pmh.nr_prescricao  = pm.nr_prescricao
                   AND pmh.nr_seq_material = pm.nr_sequencia
                LEFT JOIN tasy.prescr_mat_alteracao pma
                    ON pma.nr_seq_horario  = pmh.nr_sequencia
                   AND pma.nr_prescricao   = pmh.nr_prescricao
                   AND pma.nr_seq_prescricao = pm.nr_sequencia
                   AND NVL(pma.ie_alteracao, 0) NOT IN (5, 12)
                WHERE cm.nr_atendimento IN ({$this->placeholders($chunk)})
                  AND mf.ie_antimicrobiano = 'S'
                  AND cm.dt_liberacao      IS NOT NULL
                  AND cm.dt_suspensao      IS NULL
                  AND TRUNC(pmh.dt_horario) = TRUNC(SYSDATE)
            )
            GROUP BY nr_atendimento, med_id, descricao, dt_horario,
                     qt_dose, cd_unidade_medida_dose, ie_via_aplicacao, nr_dia_util
            HAVING MAX(priority) < 400
            ORDER BY nr_atendimento, dt_horario
        ", $chunk);

        $now = time();

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $priority = (int) $row->priority;
            $status = match (true) {
                $priority >= 300 => 'refused',
                $priority >= 200 => 'undone',
                $priority >= 30 => 'rescheduled',
                default => 'scheduled',
            };

            $dose = '';
            if (! empty($row->qt_dose)) {
                $dose = (string) (int) $row->qt_dose;
                if (! empty($row->cd_unidade_medida_dose)) {
                    $dose .= $row->cd_unidade_medida_dose;
                }
            }

            $parts = [];
            if ($row->nr_dia_util) {
                $parts[] = 'Dia '.$row->nr_dia_util;
            }
            if ($dose) {
                $parts[] = $dose;
            }
            if (! empty($row->ie_via_aplicacao)) {
                $parts[] = $row->ie_via_aplicacao;
            }

            $dtHorario = $row->dt_horario;
            $dtTs = $dtHorario ? strtotime($dtHorario) : null;

            $tempo = '';
            if ($dtTs) {
                $diff = $dtTs - $now;
                if ($diff > 0) {
                    $tempo = $diff < 3600
                        ? 'em '.(int) round($diff / 60).'min'
                        : 'em '.(int) round($diff / 3600).'h';
                } else {
                    $diff = abs($diff);
                    $tempo = $diff < 3600
                        ? (int) round($diff / 60).'min em atraso'
                        : (int) round($diff / 3600).'h em atraso';
                }
            }

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => 'antibiotico',
                'icone' => 'antimicrobiano.svg',
                'descricao' => substr($row->descricao ?? 'Antimicrobiano', 0, 60),
                'ds_subtipo' => 'Antimicrobiano',
                'ds_complemento' => implode(' · ', $parts),
                'dt_evento' => $dtHorario,
                'dt_evento_formatted' => $dtTs ? date('d/m/Y H:i', $dtTs) : null,
                'tempo_pendente' => $tempo,
                'status_laudo' => match ($status) {
                    'refused' => 'Recusado',
                    'undone' => 'Desfeito',
                    'rescheduled' => 'Reaprazado',
                    default => 'Pendente',
                },
                'urgente' => false,
            ];
        }
    }
}
