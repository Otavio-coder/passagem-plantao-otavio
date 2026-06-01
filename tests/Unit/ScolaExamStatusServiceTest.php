<?php

namespace Tests\Unit;

use App\Services\Scola\ScolaExamStatusService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ScolaExamStatusServiceTest extends TestCase
{
    private function callSummarizeItemStatus(object $exam): array
    {
        $method = new ReflectionMethod(ScolaExamStatusService::class, 'summarizeItemStatus');

        return $method->invoke(new ScolaExamStatusService, $exam);
    }

    private function makeExam(
        ?string $colheita,
        ?string $resultado,
        ?string $liberado,
        ?string $exportado = null,
        ?string $status = null
    ): object {
        return (object) [
            'data_colheita' => $colheita,
            'data_resultado' => $resultado,
            'data_liberado' => $liberado,
            'data_exportacao_resultado' => $exportado,
            'status_exame_integracao' => $status,
        ];
    }

    #[Test]
    public function laudo_liberado_e_exportado_retorna_baixa_nao_integrada(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam('2026-04-06 08:00:00', '2026-04-06 12:00:00', '2026-04-06 14:00:00', '2026-04-06 14:01:00')
        );

        $this->assertSame('Laudo liberado (baixa não integrada)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function laudo_liberado_sem_exportacao_retorna_aguardando_integracao(): void
    {
        // Released in Scola but not yet sent to TASY
        $result = $this->callSummarizeItemStatus(
            $this->makeExam('2026-04-06 08:00:00', '2026-04-06 12:00:00', '2026-04-06 14:00:00', null)
        );

        $this->assertSame('Laudo liberado (aguardando integração TASY)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function liberado_sem_colheita_mas_com_exportacao_retorna_baixa_nao_integrada(): void
    {
        // Some exams don't record data_colheita but still get exported — exportado takes priority
        $result = $this->callSummarizeItemStatus(
            $this->makeExam(null, '2026-04-06 12:00:00', '2026-04-06 14:00:00', '2026-04-06 14:01:00')
        );

        $this->assertSame('Laudo liberado (baixa não integrada)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function liberado_sem_colheita_sem_exportacao_retorna_aguardando_integracao(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam(null, null, '2026-04-06 14:00:00', null)
        );

        $this->assertSame('Laudo liberado (aguardando integração TASY)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function status_n_com_colheita_retorna_amostra_rejeitada(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam('2026-04-06 08:00:00', null, null, null, 'N')
        );

        $this->assertSame('Amostra rejeitada pelo laboratório', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function status_n_sem_colheita_retorna_nova_coleta(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam(null, null, null, null, 'N')
        );

        $this->assertSame('Nova coleta necessária', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function exame_com_colheita_sem_liberacao_retorna_coletado(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam('2026-04-06 08:00:00', null, null)
        );

        $this->assertSame('Coletado (aguardando resultado)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function exame_sem_nenhum_dado_retorna_solicitado(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam(null, null, null)
        );

        $this->assertSame('Solicitado (aguardando coleta)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function status_c_retorna_cancelado_scola(): void
    {
        // 'C' = cancelado administrativamente no SCOLA (ex: cultura em incubação longa)
        $result = $this->callSummarizeItemStatus(
            $this->makeExam(null, null, null, null, 'C')
        );

        $this->assertSame('Cancelado no SCOLA (verificar laboratório)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function status_c_com_colheita_retorna_cancelado_scola(): void
    {
        $result = $this->callSummarizeItemStatus(
            $this->makeExam('2026-04-06 08:00:00', null, null, null, 'C')
        );

        $this->assertSame('Cancelado no SCOLA (verificar laboratório)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function enrich_events_aplica_status_por_item_nao_por_prescricao(): void
    {
        // Prescription has two items: seq 1 collected, seq 2 not collected.
        // Each should get its own per-item Scola status.
        $results = [
            999 => [
                'events' => [
                    [
                        'tipo' => 'exame',
                        'nr_prescricao' => '1001',
                        'nr_sequencia_pp' => '1',
                        'dt_coleta' => null,
                    ],
                    [
                        'tipo' => 'exame',
                        'nr_prescricao' => '1001',
                        'nr_sequencia_pp' => '2',
                        'dt_coleta' => null,
                    ],
                ],
                'discharge' => null,
            ],
        ];

        // Build a service with a mocked status map via the public enrichEvents path would
        // require a live DB. We test the internal buildStatusMap logic instead via reflection.
        $service = new ScolaExamStatusService;

        $pedidos = ['1001' => ['500']];
        $movEx = [
            '500' => [
                // seq 1: collected (status A = ativo/processando)
                (object) [
                    'id_seq_prescricao_integracao' => '1',
                    'data_colheita' => '2026-04-06 08:00:00',
                    'data_resultado' => null,
                    'data_liberado' => null,
                    'data_exportacao_resultado' => null,
                    'status_exame_integracao' => 'A',
                ],
                // seq 2: not collected
                (object) [
                    'id_seq_prescricao_integracao' => '2',
                    'data_colheita' => null,
                    'data_resultado' => null,
                    'data_liberado' => null,
                    'data_exportacao_resultado' => null,
                    'status_exame_integracao' => 'A',
                ],
            ],
        ];

        $buildStatusMap = new ReflectionMethod(ScolaExamStatusService::class, 'buildStatusMap');
        $map = $buildStatusMap->invoke($service, $pedidos, $movEx);

        $this->assertSame('Coletado (aguardando resultado)', $map['1001_1']['label']);
        $this->assertSame('Solicitado (aguardando coleta)', $map['1001_2']['label']);
    }

    #[Test]
    public function multiplos_pedidos_pedido_mais_recente_tem_prioridade(): void
    {
        // Same prescription, two pedidos. Seq 1 appears in both.
        // Higher codigo_pedido (200) should override lower (100).
        $service = new ScolaExamStatusService;

        $pedidos = ['2000' => ['100', '200']];
        $movEx = [
            '100' => [
                (object) [
                    'id_seq_prescricao_integracao' => '1',
                    'data_colheita' => '2026-04-06 08:00:00',
                    'data_resultado' => '2026-04-06 12:00:00',
                    'data_liberado' => '2026-04-06 14:00:00',
                    'data_exportacao_resultado' => '2026-04-06 14:01:00',
                    'status_exame_integracao' => 'L',
                ],
            ],
            '200' => [
                (object) [
                    'id_seq_prescricao_integracao' => '1',
                    'data_colheita' => null,
                    'data_resultado' => null,
                    'data_liberado' => null,
                    'data_exportacao_resultado' => null,
                    'status_exame_integracao' => 'C',
                ],
            ],
        ];

        $buildStatusMap = new ReflectionMethod(ScolaExamStatusService::class, 'buildStatusMap');
        $map = $buildStatusMap->invoke($service, $pedidos, $movEx);

        // pedido 200 (higher) overwrites pedido 100 → cancelado do pedido mais novo vence
        $this->assertSame('Cancelado no SCOLA (verificar laboratório)', $map['2000_1']['label']);
        $this->assertTrue($map['2000_1']['integration_issue']);
    }

    #[Test]
    public function itens_sem_id_seq_prescricao_sao_ignorados(): void
    {
        $service = new ScolaExamStatusService;

        $pedidos = ['3000' => ['300']];
        $movEx = [
            '300' => [
                (object) [
                    'id_seq_prescricao_integracao' => null,
                    'data_colheita' => '2026-04-06 08:00:00',
                    'data_resultado' => null,
                    'data_liberado' => null,
                    'data_exportacao_resultado' => null,
                    'status_exame_integracao' => 'C',
                ],
            ],
        ];

        $buildStatusMap = new ReflectionMethod(ScolaExamStatusService::class, 'buildStatusMap');
        $map = $buildStatusMap->invoke($service, $pedidos, $movEx);

        $this->assertEmpty($map);
    }
}
