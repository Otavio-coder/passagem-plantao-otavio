<?php

namespace App\Services\Scola;

use Carbon\Carbon;
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
                    $event['scola_data_colheita'] = $status['scola_data_colheita'];
                    $event['scola_data_resultado'] = $status['scola_data_resultado'];
                    $event['scola_data_liberado'] = $status['scola_data_liberado'];
                    $event['scola_data_exportado'] = $status['scola_data_exportado'];

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

        // Enriquecer hemoculturas pendentes com resultado bacteriológico
        try {
            $this->enrichHemocBacteriology($results);
        } catch (\Throwable $e) {
            Log::warning('[ScolaExamStatus] Falha ao enriquecer bacteriologia de hemoculturas', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retorna resultado bacteriológico por nr_prescricao consultando res_ex do SCOLA.
     *
     * Campos chave:
     *   GERME1/2/3 → nome do organismo identificado (positivo)
     *   BACTER      → "Ausência de crescimento bacteriano." (negativo) ou organismo (positivo)
     *   RESAUT      → '+' indica positividade automática
     *
     * Retorna: 'Negativo' | 'Positivo — [organismo]' | null (sem resultado ainda)
     *
     * @param  string[]  $prescricaoIds
     * @return array<string, string|null>
     */
    public function getHemocResults(array $prescricaoIds): array
    {
        if (empty($prescricaoIds)) {
            return [];
        }

        try {
            $pedidos = $this->fetchPedidos($prescricaoIds);

            if (empty($pedidos)) {
                return [];
            }

            $allCodigoPedidos = array_unique(array_merge(...array_values($pedidos)));
            $resExSummary = $this->fetchResExSummary($allCodigoPedidos);
            $resExPresence = $this->fetchResExPresence($allCodigoPedidos);
            $movEx = $this->fetchMovEx($allCodigoPedidos);

            $results = [];

            foreach ($pedidos as $nrPrescricao => $codigoPedidos) {
                $organismos = [];
                $hasNegativo = false;
                $hasPositivo = false;
                $hasResult = false;

                foreach ($codigoPedidos as $codigoPedido) {
                    if (in_array($codigoPedido, $resExPresence, true)) {
                        $hasResult = true;
                    }

                    foreach ($resExSummary[$codigoPedido] ?? [] as $row) {
                        $campo = strtoupper(trim((string) ($row->campo ?? '')));
                        $valor = trim((string) ($row->valor_resultado ?? ''));

                        if ($valor === '' || $valor === '-') {
                            continue;
                        }

                        $isResultoField = $campo === 'BACTER'
                            || $campo === 'RESULTADO'
                            || str_starts_with($campo, 'ONEG');

                        if (preg_match('/^GERME\d*$/', $campo)) {
                            $organismos[] = $valor;
                            $hasPositivo = true;
                        } elseif ($isResultoField) {
                            if (str_contains(strtolower($valor), 'aus') && str_contains(strtolower($valor), 'crescimento')) {
                                $hasNegativo = true;
                            } else {
                                $organismos[] = $valor;
                                $hasPositivo = true;
                            }
                        } elseif ($campo === 'RESAUT' && $valor === '+') {
                            $hasPositivo = true;
                        }
                    }
                }

                $organismos = array_values(array_unique(array_filter($organismos)));

                if ($hasPositivo) {
                    $results[$nrPrescricao] = 'Positivo'.(! empty($organismos) ? ' — '.implode(', ', $organismos) : '');
                } elseif ($hasNegativo) {
                    $results[$nrPrescricao] = 'Negativo';
                } elseif ($hasResult) {
                    // res_ex tem dados mas sem campos de resultado definitivo (GERME/BACTER/RESAUT).
                    // Usa movex como fallback para determinar o estágio mais avançado do processo.
                    $results[$nrPrescricao] = $this->movexFallbackStatus($codigoPedidos, $movEx);
                } else {
                    $results[$nrPrescricao] = null;
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('[ScolaExamStatus] Falha ao obter resultados de hemoculturas', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /** @param  array<int|string, mixed>  $results */
    private function enrichHemocBacteriology(array &$results): void
    {
        $hemocPrescricoes = [];

        foreach ($results as $attendance) {
            foreach ($attendance['events'] as $event) {
                if (! str_contains(strtoupper((string) ($event['descricao'] ?? '')), 'HEMOCULTURA')) {
                    continue;
                }

                $nr = (string) ($event['nr_prescricao'] ?? '');
                if ($nr !== '') {
                    $hemocPrescricoes[$nr] = $nr;
                }
            }
        }

        if (empty($hemocPrescricoes)) {
            return;
        }

        $hemocResultados = $this->getHemocResults(array_values($hemocPrescricoes));

        foreach ($results as &$attendance) {
            foreach ($attendance['events'] as &$event) {
                if (! str_contains(strtoupper((string) ($event['descricao'] ?? '')), 'HEMOCULTURA')) {
                    continue;
                }

                $nr = (string) ($event['nr_prescricao'] ?? '');
                if ($nr !== '' && array_key_exists($nr, $hemocResultados)) {
                    $event['scola_resultado'] = $hemocResultados[$nr];
                }
            }
            unset($event);
        }
        unset($attendance);
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
     * @return array<string, array{label: string, integration_issue: bool, scola_data_colheita: string|null, scola_data_resultado: string|null, scola_data_liberado: string|null, scola_data_exportado: string|null}>
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
     *   3. data_resultado set → result entered in SCOLA, laudo in review
     *   4. status = 'N' → new collection required
     *   5. data_colheita set → collected, awaiting result
     *   6. otherwise → waiting for collection
     *
     * @return array{label: string, integration_issue: bool, scola_data_colheita: string|null, scola_data_resultado: string|null, scola_data_liberado: string|null, scola_data_exportado: string|null}
     */
    private function summarizeItemStatus(object $exam): array
    {
        $hasColheita = ! empty($exam->data_colheita);
        $hasResultado = ! empty($exam->data_resultado);
        $hasLiberado = ! empty($exam->data_liberado);
        $hasExportado = ! empty($exam->data_exportacao_resultado);
        $statusCode = trim((string) ($exam->status_exame_integracao ?? ''));

        $dates = [
            'scola_data_colheita' => $this->formatScolaDate($exam->data_colheita ?? null),
            'scola_data_resultado' => $this->formatScolaDate($exam->data_resultado ?? null),
            'scola_data_liberado' => $this->formatScolaDate($exam->data_liberado ?? null),
            'scola_data_exportado' => $this->formatScolaDate($exam->data_exportacao_resultado ?? null),
        ];

        if ($hasExportado) {
            return array_merge($dates, [
                'label' => 'Laudo liberado (baixa não integrada)',
                'integration_issue' => true,
            ]);
        }

        if ($hasLiberado) {
            return array_merge($dates, [
                'label' => 'Laudo liberado (aguardando integração TASY)',
                'integration_issue' => true,
            ]);
        }

        if ($hasResultado) {
            return array_merge($dates, [
                'label' => 'Resultado inserido (aguardando laudo)',
                'integration_issue' => false,
            ]);
        }

        if ($statusCode === 'N') {
            return array_merge($dates, [
                'label' => 'Nova coleta necessária',
                'integration_issue' => false,
            ]);
        }

        if ($hasColheita) {
            return array_merge($dates, [
                'label' => 'Coletado (aguardando resultado)',
                'integration_issue' => false,
            ]);
        }

        return array_merge($dates, [
            'label' => 'Solicitado (aguardando coleta)',
            'integration_issue' => false,
        ]);
    }

    /**
     * Determina o status mais avançado com base no movex quando res_ex não tem campos definitivos.
     * Prioridade: exportado → liberado → resultado inserido → coletado → null
     *
     * @param  string[]  $codigoPedidos
     * @param  array<string, object[]>  $movEx
     */
    private function movexFallbackStatus(array $codigoPedidos, array $movEx): ?string
    {
        $maxPriority = 0;
        $status = null;

        foreach ($codigoPedidos as $codigoPedido) {
            foreach ($movEx[$codigoPedido] ?? [] as $exam) {
                if (! empty($exam->data_exportacao_resultado) && $maxPriority < 4) {
                    $maxPriority = 4;
                    $status = 'Laudo integrado';
                } elseif (! empty($exam->data_liberado) && $maxPriority < 3) {
                    $maxPriority = 3;
                    $status = 'Laudo liberado';
                } elseif (! empty($exam->data_resultado) && $maxPriority < 2) {
                    $maxPriority = 2;
                    $status = 'Resultado em análise';
                } elseif (! empty($exam->data_colheita) && $maxPriority < 1) {
                    $maxPriority = 1;
                    $status = 'Coletado';
                }
            }
        }

        return $status;
    }

    /**
     * Busca os campos-chave de res_ex para determinação de resultado de hemoculturas.
     * O nome do campo varia por pedido/configuração do laboratório:
     *   GERME1..N → organismo identificado (positivo)
     *   BACTER / RESULTADO / ONEG1..N → texto de resultado (negativo ou positivo)
     *   RESAUT → flag '+' de positividade automática
     *
     * @param  string[]  $codigoPedidos
     * @return array<string, object[]> codigo_pedido => rows
     */
    private function fetchResExSummary(array $codigoPedidos): array
    {
        if (empty($codigoPedidos)) {
            return [];
        }

        $bindings = [];
        $placeholders = [];

        foreach ($codigoPedidos as $i => $id) {
            $key = "cp{$i}";
            $placeholders[] = ":{$key}";
            $bindings[$key] = $id;
        }

        $rows = DB::connection('scola')->select(
            'SELECT codigo_pedido, TRIM(ordem_campo_exame) AS campo, valor_resultado
             FROM scola.res_ex
             WHERE codigo_pedido IN ('.implode(',', $placeholders).")
               AND (TRIM(ordem_campo_exame) LIKE 'GERME%'
                    OR TRIM(ordem_campo_exame) = 'BACTER'
                    OR TRIM(ordem_campo_exame) = 'RESULTADO'
                    OR TRIM(ordem_campo_exame) LIKE 'ONEG%'
                    OR TRIM(ordem_campo_exame) = 'RESAUT')
               AND valor_resultado IS NOT NULL
               AND valor_resultado != '-'
             ORDER BY codigo_pedido, ordem_movex, ordem_campo_exame",
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->codigo_pedido][] = $row;
        }

        return $result;
    }

    /**
     * Retorna os codigo_pedido que possuem ao menos um registro em res_ex (resultado gerado).
     *
     * @param  string[]  $codigoPedidos
     * @return string[]
     */
    private function fetchResExPresence(array $codigoPedidos): array
    {
        if (empty($codigoPedidos)) {
            return [];
        }

        $bindings = [];
        $placeholders = [];

        foreach ($codigoPedidos as $i => $id) {
            $key = "cp{$i}";
            $placeholders[] = ":{$key}";
            $bindings[$key] = $id;
        }

        $rows = DB::connection('scola')->select(
            'SELECT DISTINCT codigo_pedido FROM scola.res_ex WHERE codigo_pedido IN ('.implode(',', $placeholders).')',
            $bindings
        );

        return array_map(fn (object $row): string => (string) $row->codigo_pedido, $rows);
    }

    private function formatScolaDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/y H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
