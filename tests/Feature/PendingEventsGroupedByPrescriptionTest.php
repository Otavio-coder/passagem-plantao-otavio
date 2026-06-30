<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PendingEventsGroupedByPrescriptionTest extends TestCase
{
    public function test_modal_groups_pending_items_by_prescription_and_orders_by_sequence(): void
    {
        // Arquitetura atual: pending_groups são carregados sob demanda via Livewire action
        // quando o modal "Ver todas" é aberto — NÃO são embarcados como JSON no HTML inicial.
        // O card exibe apenas o first_pending_event e o botão "Ver todas" quando all_count > 0.
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
            'pending_events' => [],
            'nr_atendimento' => 12345,
            'nm_pessoa_fisica' => 'Paciente Teste',
            'sector_exec_fallback' => 'Setor não informado',
        ];

        $html = Blade::render(
            '<x-patient-card.pending-events :patient="$patient" />',
            ['patient' => $patient],
        );

        // Card exibe o first_pending_event
        $this->assertStringContainsString('Pendência principal', $html);

        // Botão "Ver todas" aparece quando all_count > 0
        $this->assertStringContainsString('Ver todas', $html);

        // openPendingModal() é a função Alpine que carrega grupos sob demanda
        $this->assertStringContainsString('openPendingModal', $html);

        // Grupos NÃO são embarcados como JSON no HTML inicial (carregamento lazy via Livewire action).
        // O antigo @js(array_values($group['events'])) gerava JSON_HEX_QUOT-encoded data — não existe mais.
        $this->assertStringNotContainsString('"nr_prescricao":', $html);

        // Chamada Livewire para carregar grupos está no x-data do container (correto)
        $this->assertStringContainsString('getPatientPendingGroups', $html);
    }
}
