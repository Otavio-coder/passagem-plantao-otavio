<?php

namespace App\Services\PendingEvents\Handlers;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Services\PendingEvents\AbstractPendingHandler;

/**
 * Antimicrobianos com slots individuais de administração pendentes (hoje).
 *
 * Fonte: PatientPrescriptionsRepository::queryAntibioticsChunk()
 * Cada linha = um horário de administração pendente no dia atual.
 * Slots já administrados/conferidos/coletados (priority >= 400) são excluídos.
 */
class AntibioticPendingHandler extends AbstractPendingHandler
{
    public function __construct(private readonly PatientPrescriptionsRepository $repository) {}

    protected function handlerName(): string
    {
        return 'Antibióticos';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        $rows = $this->repository->queryAntibioticsChunk($chunk);

        $now = time();

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $priority = (int) $row->priority;

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
                'status_laudo' => ! empty($row->ds_alteracao_label)
                    ? trim((string) $row->ds_alteracao_label)
                    : 'Pendente',
                'urgente' => false,
            ];
        }
    }
}
