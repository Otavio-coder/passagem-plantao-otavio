<?php

namespace Tests\Unit;

use App\Services\Scola\ScolaExamStatusService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ScolaExamStatusServiceTest extends TestCase
{
    private function callSummarizeStatus(array $exams): array
    {
        $method = new ReflectionMethod(ScolaExamStatusService::class, 'summarizeStatus');

        return $method->invoke(new ScolaExamStatusService, $exams);
    }

    private function makeExam(?string $colheita, ?string $resultado, ?string $liberado): object
    {
        return (object) [
            'data_colheita' => $colheita,
            'data_resultado' => $resultado,
            'data_liberado' => $liberado,
        ];
    }

    #[Test]
    public function laudo_liberado_exige_colheita_registrada(): void
    {
        // data_liberado preenchido mas sem colheita — não deve declarar laudo liberado
        $result = $this->callSummarizeStatus([
            $this->makeExam(null, null, '2026-04-06 14:00:00'),
        ]);

        $this->assertSame('Solicitado (aguardando coleta)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function laudo_liberado_com_colheita_e_liberacao(): void
    {
        $result = $this->callSummarizeStatus([
            $this->makeExam('2026-04-06 08:00:00', '2026-04-06 12:00:00', '2026-04-06 14:00:00'),
        ]);

        $this->assertSame('Laudo liberado (baixa não integrada)', $result['label']);
        $this->assertTrue($result['integration_issue']);
    }

    #[Test]
    public function multiplos_exames_sem_colheita_nao_geram_laudo_liberado(): void
    {
        // Antes da correção, count > 1 era suficiente mesmo sem colheita
        $result = $this->callSummarizeStatus([
            $this->makeExam(null, null, '2026-04-06 14:00:00'),
            $this->makeExam(null, null, '2026-04-06 14:05:00'),
        ]);

        $this->assertSame('Solicitado (aguardando coleta)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function exame_com_colheita_sem_liberacao_retorna_coletado(): void
    {
        $result = $this->callSummarizeStatus([
            $this->makeExam('2026-04-06 08:00:00', null, null),
        ]);

        $this->assertSame('Coletado (aguardando resultado)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function exame_sem_nenhum_dado_retorna_solicitado(): void
    {
        $result = $this->callSummarizeStatus([
            $this->makeExam(null, null, null),
        ]);

        $this->assertSame('Solicitado (aguardando coleta)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }

    #[Test]
    public function painel_com_um_exame_liberado_e_outro_pendente_retorna_coletado(): void
    {
        // allLiberado = false porque o segundo exame não tem data_liberado
        $result = $this->callSummarizeStatus([
            $this->makeExam('2026-04-06 08:00:00', '2026-04-06 12:00:00', '2026-04-06 14:00:00'),
            $this->makeExam('2026-04-06 08:00:00', null, null),
        ]);

        $this->assertSame('Coletado (aguardando resultado)', $result['label']);
        $this->assertFalse($result['integration_issue']);
    }
}
