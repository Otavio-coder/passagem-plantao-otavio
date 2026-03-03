<?php

namespace App\Services\PendingEvents\Contracts;

interface PendingEventHandler
{
    /**
     * Adiciona eventos pendentes ao mapa de resultados para os atendimentos informados.
     *
     * @param array $results   Mapa [nr_atendimento => ['events' => [...], 'discharge' => ...]] (por referência)
     * @param array $attendances Lista de nr_atendimento a processar
     */
    public function handle(array &$results, array $attendances): void;
}
