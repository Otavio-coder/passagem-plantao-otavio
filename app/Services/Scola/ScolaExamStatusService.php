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

            $movEx = $this->fetchMovEx(array_values($pedidos));
            $statusMap = $this->buildStatusMap($pedidos, $movEx);

            foreach ($results as &$attendance) {
                foreach ($attendance['events'] as &$event) {
                    $nr = (string) ($event['nr_prescricao'] ?? '');
                    if (isset($statusMap[$nr])) {
                        $event['scola_status'] = $statusMap[$nr]['label'];
                        $event['scola_integration_issue'] = $statusMap[$nr]['integration_issue'];
                        // Nova flag: coleta no SCOLA mas não no Tasy
                        if (
                            $statusMap[$nr]['label'] === 'Coletado (aguardando resultado)'
                            && empty($event['dt_coleta'])
                        ) {
                            $event['scola_colheita_sem_tasy'] = true;
                        }
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

    /** @return array<string, string> nr_prescricao => codigo_pedido */
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
            'SELECT id_prescricao_integracao, codigo_pedido FROM scola.pedido WHERE id_prescricao_integracao IN (' . implode(',', $placeholders) . ')',
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->id_prescricao_integracao] = (string) $row->codigo_pedido;
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
            'SELECT codigo_pedido, cod_exame, data_colheita, data_resultado, data_liberado FROM scola.mov_ex WHERE codigo_pedido IN (' . implode(',', $placeholders) . ')',
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->codigo_pedido][] = $row;
        }

        return $result;
    }

    /** @param  array<string, string>  $pedidos
     *  @param  array<string, object[]>  $movEx
     *  @return array<string, string>
     */
    private function buildStatusMap(array $pedidos, array $movEx): array
    {
        $map = [];

        foreach ($pedidos as $nrPrescricao => $codigoPedido) {
            $exams = $movEx[$codigoPedido] ?? [];

            if (empty($exams)) {
                continue;
            }

            $map[$nrPrescricao] = $this->summarizeStatus($exams);
        }

        return $map;
    }

    /**
     * @param  object[]  $exams
     * @return array{label: string, integration_issue: bool}
     */
    private function summarizeStatus(array $exams): array
    {
        $allLiberado = true;
        $anyColheita = false;
        $anyResultado = false;

        foreach ($exams as $exam) {
            if (empty($exam->data_liberado)) {
                $allLiberado = false;
            }
            if (! empty($exam->data_colheita)) {
                $anyColheita = true;
            }
            if (! empty($exam->data_resultado)) {
                $anyResultado = true;
            }
        }

        // Exige colheita registrada para declarar laudo liberado.
        // Sem data_colheita, data_liberado preenchido pode ser artefato de integração.
        if ($allLiberado && $anyColheita) {
            return [
                'label' => 'Laudo liberado (baixa não integrada)',
                'integration_issue' => true,
            ];
        }

        if ($anyColheita) {
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
