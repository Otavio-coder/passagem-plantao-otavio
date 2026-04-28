<?php

namespace Tests\Unit;

use App\Support\PendingEventHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventHelperTest extends TestCase
{
    #[Test]
    public function motivo_exame_sem_scola_com_dt_coleta_retorna_coletado(): void
    {
        // Sem SCOLA disponível, dt_coleta do TASY é o melhor indicador
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'dt_coleta' => '06/04/2026 10:00',
            'urgente' => false,
        ]);

        $this->assertSame('Coletado — aguardando resultado', $motivo);
    }

    #[Test]
    public function motivo_exame_dt_resultado_preenchido_nao_implica_resultado_real(): void
    {
        // dt_resultado de prescr_procedimento é data administrativa — não indica resultado real.
        // Sem SCOLA confirmando, mesmo com dt_resultado preenchido, retorna apenas "Coletado".
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'dt_coleta' => '06/04/2026 10:00',
            'dt_resultado' => '06/04/2026 14:00',
            'urgente' => false,
        ]);

        $this->assertSame('Coletado — aguardando resultado', $motivo);
    }

    #[Test]
    public function motivo_exame_scola_laudo_liberado_sobrepoe_tasy(): void
    {
        // SCOLA com laudo liberado é fonte de verdade — não importa o que dt_coleta/dt_resultado dizem
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'scola_status' => 'Laudo liberado (baixa não integrada)',
            'urgente' => false,
        ]);

        $this->assertSame('Laudo disponível — baixa não realizada', $motivo);
    }

    #[Test]
    public function motivo_exame_scola_solicitado_contradiz_dt_coleta_usa_dominio(): void
    {
        // SCOLA "Solicitado" contradiz dt_coleta do TASY → SCOLA vence, cai no domínio
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'dt_coleta' => '06/04/2026 10:00',
            'scola_status' => 'Solicitado (aguardando coleta)',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_executado_sem_coleta_usa_status_de_dominio_tasy(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'dt_coleta' => null,
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_executado_sem_coleta_com_status_codigo_20_usa_dominio(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'dt_coleta' => null,
            'ie_status_execucao' => '20',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_sem_scola_sem_foi_executado_retorna_motivo_tasy(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => false,
            'ie_status_execucao' => '10',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_coletado_em_prescricao_mais_nova_exibe_status_atual_mais_sufixo(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'exame_coletado_em_prescricao_mais_nova' => true,
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta · há solicitação mais recente', $motivo);
    }

    #[Test]
    public function motivo_exame_coletado_em_nova_com_laudo_liberado_no_scola(): void
    {
        // Quando SCOLA confirma laudo liberado, "Aguardando coleta" da prescrição antiga seria
        // contradição — deve informar diretamente que o laudo está disponível.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'exame_coletado_em_prescricao_mais_nova' => true,
            'scola_status' => 'Laudo liberado (baixa não integrada)',
            'urgente' => false,
        ]);

        $this->assertSame('Laudo disponível em solicitação mais recente', $motivo);
    }

    #[Test]
    public function motivo_exame_coletado_em_nova_com_coleta_no_scola(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'exame_coletado_em_prescricao_mais_nova' => true,
            'scola_status' => 'Coletado (aguardando resultado)',
            'urgente' => false,
        ]);

        $this->assertSame('Coletado em solicitação mais recente — aguardando resultado', $motivo);
    }

    #[Test]
    public function motivo_prescricao_mais_nova_pendente_exibe_status_atual_mais_info(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'prescricao_mais_nova_pendente_info' => '63999999 — 27/04/2026',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta · há solicitação mais recente: 63999999 — 27/04/2026', $motivo);
    }
}
