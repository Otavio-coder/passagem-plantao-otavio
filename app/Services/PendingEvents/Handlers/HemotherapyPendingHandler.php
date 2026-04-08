<?php

namespace App\Services\PendingEvents\Handlers;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Services\PendingEvents\AbstractPendingHandler;
use App\Support\PendingEventPresentation;

/**
 * Pendências de hemoterapia programada nas próximas 48h.
 *
 * Fonte: PatientPrescriptionsRepository::queryHemotherapyChunk()
 * Janela: SYSDATE até SYSDATE + 2 (48h)
 * Urgência: campo ie_urgencia = 'S'
 */
class HemotherapyPendingHandler extends AbstractPendingHandler
{
    private const TIPO_MAP = [
        '0' => 'Hemocomponente',
        '1' => 'Concentrado de Hemácias',
        '2' => 'Concentrado de Plaquetas',
        '3' => 'Plasma Fresco Congelado',
        '4' => 'Crioprecipitado',
        '5' => 'Concentrado de Granulócitos',
    ];

    public function __construct(private readonly PatientPrescriptionsRepository $repository) {}

    protected function handlerName(): string
    {
        return 'Hemoterapia';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        $rows = $this->repository->queryHemotherapyChunk($chunk);

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $tipo = self::TIPO_MAP[(string) ($row->ie_tipo_hemoterap ?? '')] ?? 'Hemocomponente';

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => 'hemoterapia',
                'icone' => 'hemoterapia.svg',
                'descricao' => PendingEventPresentation::hemotherapyDescription([
                    'tipo_label' => $tipo,
                    'ie_tipo_hemoterap' => $row->ie_tipo_hemoterap ?? null,
                    'ds_procedimento_prescrito' => $row->ds_procedimento_prescrito ?? null,
                    'ds_observacao' => $row->ds_observacao ?? null,
                    'ds_observacao_proc' => $row->ds_observacao_proc ?? null,
                    'ds_horarios' => $row->ds_horarios ?? null,
                    'qt_vol_hemocomp' => $row->qt_vol_hemocomp ?? null,
                    'via_aplicacao' => $row->via_aplicacao ?? null,
                    'ie_via_aplicacao' => $row->ie_via_aplicacao ?? null,
                ]),
                'ie_tipo_hemoterap' => $row->ie_tipo_hemoterap ?? null,
                'tipo_label' => $tipo,
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => date('d/m/Y H:i', strtotime($row->dt_evento)),
                'setor_execucao' => $row->setor_execucao ?? null,
                'urgente' => ($row->ie_urgencia ?? 'N') === 'S',
            ];
        }
    }
}
