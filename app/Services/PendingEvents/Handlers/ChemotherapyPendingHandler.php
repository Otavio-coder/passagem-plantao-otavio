<?php

namespace App\Services\PendingEvents\Handlers;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\Log;

/**
 * Sessões de quimioterapia agendadas nos próximos 30 dias.
 *
 * Fonte: PatientPrescriptionsRepository::queryChemotherapyChunk()
 */
class ChemotherapyPendingHandler extends AbstractPendingHandler
{
    public function __construct(private readonly PatientPrescriptionsRepository $repository) {}

    protected function handlerName(): string
    {
        return 'Quimioterapia';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        try {
            $rows = $this->repository->queryChemotherapyChunk($chunk);

            foreach ($rows as $row) {
                if (! isset($results[$row->nr_atendimento])) {
                    continue;
                }

                $descricao = '';
                if (! empty($row->ds_protocolo_medic)) {
                    $descricao = trim((string) $row->ds_protocolo_medic);
                }
                if (! empty($row->nr_ciclo)) {
                    $descricao = trim($descricao.' (Ciclo '.$row->nr_ciclo.')');
                }
                if ($descricao === '') {
                    $descricao = 'Quimioterapia';
                }

                $parts = [];
                if (! empty($row->ds_local)) {
                    $parts[] = $row->ds_local;
                }
                if (! empty($row->nm_medico_resp)) {
                    $parts[] = $row->nm_medico_resp;
                }

                $statusCode = strtoupper(trim((string) ($row->ie_status_agenda ?? '')));
                $statusLabel = ! empty($row->ds_status_agenda_label)
                    ? trim((string) $row->ds_status_agenda_label)
                    : ($statusCode !== '' ? $statusCode : null);

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo' => 'quimioterapia',
                    'icone' => 'quimioterapia.svg',
                    'descricao' => $descricao,
                    'ds_subtipo' => $row->ds_local ?? null,
                    'status_laudo' => $statusLabel,
                    'ie_status_agenda' => $statusCode !== '' ? $statusCode : null,
                    'dt_evento' => $row->dt_evento,
                    'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                    'ds_complemento' => implode(' · ', $parts),
                    'local' => $row->ds_local ?? null,
                    'setor' => $row->ds_local ?? null,
                    'setor_execucao' => $row->ds_local ?? null,
                    'medico' => $row->nm_medico_resp ?? null,
                    'ciclo' => $row->nr_ciclo ?? null,
                    'urgente' => false,
                    '_fonte' => 'agenda',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] Quimioterapia query failed', [
                'exception' => $e,
                'attendance_count' => count($chunk),
            ]);
        }
    }
}
