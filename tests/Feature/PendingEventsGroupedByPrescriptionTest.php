<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PendingEventsGroupedByPrescriptionTest extends TestCase
{
    public function test_modal_groups_pending_items_by_prescription_and_orders_by_sequence(): void
    {
        $patient = [
            'first_pending_event' => [
                'descricao' => 'Pendência principal',
            ],
            'first_pending_style' => [
                'card_style' => '',
                'icon' => 'alert-circle.svg',
                'description_class' => 'text-[#062047] font-semibold',
                'time_class' => 'text-[#004D9D] font-medium',
                'pulse_color' => 'bg-gray-400',
                'show_pulse' => false,
            ],
            'latest_evaluation' => [],
            'pending_modal_meta' => [
                'all_count' => 5,
            ],
            'pending_events' => [
                ['nr_prescricao' => 9001],
                ['nr_prescricao' => 9001],
                ['nr_prescricao' => 9002],
                ['_fonte' => 'agenda'],
                ['_fonte' => 'agenda'],
            ],
            'pending_groups' => [
                [
                    'type' => 'procedimento',
                    'label' => 'Procedimentos',
                    'style' => [
                        'border_header' => 'border-indigo-200',
                        'bg_header' => 'bg-indigo-50/60',
                        'text_header' => 'text-indigo-700',
                        'border_card' => 'border-indigo-200',
                        'bg_card' => 'bg-indigo-50/40',
                    ],
                    'events' => [
                        [
                            'descricao' => 'Item 2',
                            'nr_prescricao' => 9001,
                            'nr_sequencia_pp' => 2,
                            'dt_solicitacao' => '05/05/2026 09:10',
                            'tempo_pendente' => '1h',
                            'icone' => 'alert-circle.svg',
                        ],
                        [
                            'descricao' => 'Item 1',
                            'nr_prescricao' => 9001,
                            'nr_sequencia_pp' => 1,
                            'dt_solicitacao' => '05/05/2026 09:00',
                            'tempo_pendente' => '2h',
                            'icone' => 'alert-circle.svg',
                        ],
                        [
                            'descricao' => 'Outro item',
                            'nr_prescricao' => 9002,
                            'nr_sequencia_pp' => 1,
                            'dt_solicitacao' => '05/05/2026 10:00',
                            'tempo_pendente' => '30m',
                            'icone' => 'alert-circle.svg',
                        ],
                        [
                            'descricao' => 'Item Agenda 1',
                            'dt_evento' => '2026-05-05 14:00:00',
                            '_fonte' => 'agenda',
                            'tempo_pendente' => '2h',
                            'icone' => 'alert-circle.svg',
                        ],
                        [
                            'descricao' => 'Item Agenda 2',
                            'dt_evento' => '2026-05-05 14:00:00',
                            '_fonte' => 'agenda',
                            'tempo_pendente' => '1h',
                            'icone' => 'alert-circle.svg',
                        ],
                    ],
                ],
            ],
            'sector_exec_fallback' => 'Setor não informado',
        ];

        $html = Blade::render(
            '<x-patient-card.pending-events :patient="$patient" :sector-id="$sectorId" />',
            [
                'patient' => $patient,
                'sectorId' => 0,
            ],
        );

        // Js::from() faz double-encode: JSON → json_encode(string) → JSON.parse('...').
        // Geramos a string de busca com o mesmo processo para garantir o match exato.
        $jsFlags = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR;

        /** @param mixed $data */
        $jsSearch = static function ($data) use ($jsFlags): string {
            $firstJson = json_encode($data, $jsFlags);

            return (string) substr((string) substr(json_encode($firstJson, $jsFlags), 1, -1), 1, -1);
        };

        // Prescrição 9001 tem 2 itens → isSingleItem:false; 9002 tem 1 → isSingleItem:true
        $this->assertStringContainsString($jsSearch(['isSingleItem' => false]), $html);
        $this->assertStringContainsString($jsSearch(['isSingleItem' => true]), $html);

        // Grupo de agenda: label com a data gerada pelo mesmo date() do template
        $agendaLabel = 'Agenda - '.date('d/m/Y H:i', strtotime('2026-05-05 14:00:00'));
        $this->assertStringContainsString($jsSearch($agendaLabel), $html);

        $positionItem1 = strpos($html, 'Item 1');
        $positionItem2 = strpos($html, 'Item 2');
        $positionOutro = strpos($html, 'Outro item');
        $positionAgenda = strpos($html, 'Item Agenda');

        $this->assertNotFalse($positionItem1);
        $this->assertNotFalse($positionItem2);
        $this->assertNotFalse($positionOutro);
        $this->assertNotFalse($positionAgenda);
        $this->assertLessThan($positionItem2, $positionItem1);
        $this->assertLessThan($positionOutro, $positionItem2);
    }
}
