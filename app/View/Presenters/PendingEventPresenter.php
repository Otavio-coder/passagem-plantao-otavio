<?php

namespace App\View\Presenters;

use App\Support\PendingEventHelper;

/**
 * Presenter para exibição de eventos pendentes nas views.
 *
 * Mantém a mesma interface do PendingEventHelper mas com mensagens simplificadas
 * para contextos de visualização direta (sem granularidade de TASY).
 */
class PendingEventPresenter
{
    /**
     * @return array{events: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, first_event: array<string, mixed>|null}
     */
    public static function buildPendingModalData(array $pendingEvents): array
    {
        return PendingEventHelper::buildPendingModalData($pendingEvents);
    }

    /**
     * @return array{icon: string, card_bg: string, card_style: string, description_class: string, time_class: string, pulse_color: string, show_pulse: bool}
     */
    public static function firstEventCardStyle(?array $firstEvent): array
    {
        return PendingEventHelper::firstEventCardStyle($firstEvent);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function executionSectorLabel(array $event): string
    {
        return PendingEventHelper::executionSectorLabel($event);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function hemotherapyDescription(array $event): string
    {
        return PendingEventHelper::hemotherapyDescription($event);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function surgeryDescription(array $event): string
    {
        return PendingEventHelper::surgeryDescription($event);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function classificationLabel(array $event, string $normalizedType): ?string
    {
        return PendingEventHelper::classificationLabel($event, $normalizedType);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function surgeryDiagnosticLabel(array $event): string
    {
        return PendingEventHelper::surgeryDiagnosticLabel($event);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function motivoPendente(array $event): string
    {
        // Executado no sistema mas sem baixa — mensagem única independente de coleta/resultado
        if ($event['foi_executado_sem_baixa'] ?? false) {
            return 'Realizado — prescrição não baixada no sistema';
        }

        // Exame realizado em prescrição mais nova — retorno direto
        if ($event['exame_coletado_em_prescricao_mais_nova'] ?? false) {
            return 'Exame realizado em solicitação mais recente';
        }

        return PendingEventHelper::motivoPendente($event);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public static function withNearFlag(array $events): array
    {
        return PendingEventHelper::withNearFlag($events);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function isNear(array $event): bool
    {
        return PendingEventHelper::isNear($event);
    }
}
