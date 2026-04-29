<?php

namespace App\Services\Scola;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScolaExamStatusService
{
    /** @param  array<int|string, mixed>  $results */
    public function enrichEvents(array &$results): void
    {
        $prescricaoIds = $this->collectPrescricaoIds($results);

        if (empty($prescricaoIds)) {
            return;
        }

        try {
            $pedidos = $this->fetchPedidos($prescricaoIds);

            if (empty($pedidos)) {
                return;
            }

            $allCodigoPedidos = array_unique(array_merge(...array_values($pedidos)));
            $movEx = $this->fetchMovEx($allCodigoPedidos);
            $statusMap = $this->buildStatusMap($pedidos, $movEx);

            foreach ($results as &$attendance) {
                foreach ($attendance['events'] as &$event) {
                    $nr = (string) ($event['nr_prescricao'] ?? '');
                    $seq = (string) ($event['nr_sequencia_pp'] ?? '');

                    if ($nr === '') {
                        continue;
                    }

                    $itemKey = $seq !== '' ? "{$nr}_{$seq}" : null;
                    $status = ($itemKey !== null && isset($statusMap[$itemKey])) ? $statusMap[$itemKey] : null;

                    if ($status === null) {
                        continue;
                    }

                    $event['scola_status'] = $status['label'];
                    $event['scola_integration_issue'] = $status['integration_issue'];

                    if (
                        $status['label'] === 'Coletado (aguardando resultado)'
                        && empty($event['dt_coleta'])
                    ) {
                        $event['scola_colheita_sem_tasy'] = true;
                    }
                }
                unset($event);
            }
            unset($attendance);
        } catch (\Throwable $e) {
            Log::warning('[ScolaExamStatus] Falha ao enriquecer eventos com status SCOLA', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param  array<int|string, mixed>  $results */
    private function collectPrescricaoIds(array $results): array
    {
        $ids = [];

        foreach ($results as $attendance) {
            foreach ($attendance['events'] as $event) {
                if (! in_array($event['tipo'] ?? '', ['exame', 'proc_exame'], true)) {
                    continue;
                }
                $nr = (string) ($event['nr_prescricao'] ?? '');
                if ($nr !== '') {
                    $ids[$nr] = $nr;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * Returns all codigo_pedido values per prescription.
     * A prescription may have multiple Scola pedidos (e.g. partial orders or re-orders).
     *
     * @return array<string, string[]> nr_prescricao => [codigo_pedido, ...]
     */
    private function fetchPedidos(array $prescricaoIds): array
    {
        $bindings = [];
        $placeholders = [];

        foreach ($prescricaoIds as $i => $id) {
            $key = "p{$i}";
            $placeholders[] = ":{$key}";
            $bindings[$key] = $id;
        }

        $rows = DB::connection('scola')->select(
            'SELECT id_prescricao_integracao, codigo_pedido FROM scola.pedido WHERE id_prescricao_integracao IN ('.implode(',', $placeholders).')',
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->id_prescricao_integracao][] = (string) $row->codigo_pedido;
        }

        return $result;
    }

    /**
     * @param  string[]  $codigoPedidos
     * @return array<string, object[]> codigo_pedido => rows
     */
    private function fetchMovEx(array $codigoPedidos): array
    {
        $bindings = [];
        $placeholders = [];

        foreach ($codigoPedidos as $i => $id) {
            $key = "cp{$i}";
            $placeholders[] = ":{$key}";
            $bindings[$key] = $id;
        }

        $rows = DB::connection('scola')->select(
            'SELECT codigo_pedido, id_seq_prescricao_integracao, data_colheita, data_resultado, data_liberado, data_exportacao_resultado, status_exame_integracao FROM scola.mov_ex WHERE codigo_pedido IN ('.implode(',', $placeholders).')',
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->codigo_pedido][] = $row;
        }

        return $result;
    }

    /**
     * Builds a per-item status map indexed by "{nr_prescricao}_{nr_sequencia}".
     * When the same (prescricao, sequencia) appears in multiple pedidos,
     * the highest codigo_pedido (most recent) takes priority.
     *
     * @param  array<string, string[]>  $pedidos
     * @param  array<string, object[]>  $movEx
     * @return array<string, array{label: string, integration_issue: bool}>
     */
    private function buildStatusMap(array $pedidos, array $movEx): array
    {
        $map = [];

        foreach ($pedidos as $nrPrescricao => $codigoPedidos) {
            // Process from lowest to highest so the most recent pedido overwrites
            sort($codigoPedidos, SORT_NUMERIC);

            foreach ($codigoPedidos as $codigoPedido) {
                $exams = $movEx[$codigoPedido] ?? [];

                foreach ($exams as $exam) {
                    $seq = trim((string) ($exam->id_seq_prescricao_integracao ?? ''));
                    if ($seq === '') {
                        continue;
                    }

                    $map["{$nrPrescricao}_{$seq}"] = $this->summarizeItemStatus($exam);
                }
            }
        }

        return $map;
    }

    /**
     * Returns status for a single mov_ex item.
     *
     * Priority:
     *   1. data_exportacao_resultado set → result was sent to TASY (baixa still pending on TASY side)
     *   2. data_liberado set, not yet exported → released in Scola, awaiting TASY integration
     *   3. data_resultado set with data_colheita → result entered, laudo in review
     *   4. status = 'N' → new collection required
     *   5. data_colheita set → collected, awaiting result
     *   6. otherwise → waiting for collection
     *
     * @return array{label: string, integration_issue: bool}
     */
    private function summarizeItemStatus(object $exam): array
    {
        $hasColheita = ! empty($exam->data_colheita);
        $hasLiberado = ! empty($exam->data_liberado);
        $hasExportado = ! empty($exam->data_exportacao_resultado);
        $statusCode = trim((string) ($exam->status_exame_integracao ?? ''));

        if ($hasExportado) {
            return [
                'label' => 'Laudo liberado (baixa não integrada)',
                'integration_issue' => true,
            ];
        }

        if ($hasLiberado) {
            return [
                'label' => 'Laudo liberado (aguardando integração TASY)',
                'integration_issue' => true,
            ];
        }

        if ($statusCode === 'N') {
            return [
                'label' => 'Nova coleta necessária',
                'integration_issue' => false,
            ];
        }

        if ($hasColheita) {
            return [
                'label' => 'Coletado (aguardando resultado)',
                'integration_issue' => false,
            ];
        }

        return [
            'label' => 'Solicitado (aguardando coleta)',
            'integration_issue' => false,
        ];
    }
}
