<?php

namespace Tests\Unit;

use App\Support\PendingEventHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventHelperTest extends TestCase
{
    // ── Exames: pré-coleta ────────────────────────────────────────────────────

    #[Test]
    public function motivo_exame_codigo_prescrito_retorna_label_tasy(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '10',
            'status_laudo' => 'Prescrito',
            'urgente' => false,
        ]);

        $this->assertSame('Prescrito', $motivo);
    }

    #[Test]
    public function motivo_exame_codigo_prescrito_urgente_adiciona_prefixo(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '10',
            'status_laudo' => 'Prescrito',
            'urgente' => true,
        ]);

        $this->assertSame('Urgente — prescrito', $motivo);
    }

    #[Test]
    public function motivo_exame_asa_retorna_label_tasy(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => 'ASA',
            'status_laudo' => 'Aguardando aprovação para execução',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando aprovação para execução', $motivo);
    }

    #[Test]
    public function motivo_exame_sem_codigo_sem_coleta_retorna_aguardando_coleta(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    // ── Exames: em andamento ──────────────────────────────────────────────────

    #[Test]
    public function motivo_exame_em_andamento_usa_label_tasy(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '15',
            'status_laudo' => 'Em exame',
            'urgente' => false,
        ]);

        $this->assertSame('Em exame', $motivo);
    }

    // ── Exames: pós-coleta ────────────────────────────────────────────────────

    #[Test]
    public function motivo_exame_dt_coleta_sem_label_retorna_aguardando_laudo(): void
    {
        // dt_coleta preenchido sem label de domínio → estado padrão pós-coleta
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'dt_coleta' => '06/04/2026 10:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_dt_coleta_com_label_tasy_retorna_label(): void
    {
        // dt_coleta + label de domínio 1226 → usa label Tasy diretamente
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '22',
            'status_laudo' => 'Aguardando Laudo',
            'dt_coleta' => '06/04/2026 10:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando Laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_dt_resultado_sem_confirmacao_scola_retorna_aguardando_laudo(): void
    {
        // dt_resultado de prescr_procedimento é administrativo — não indica resultado real.
        // Sem label de domínio, retorna fallback pós-coleta.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'dt_coleta' => '06/04/2026 10:00',
            'dt_resultado' => '06/04/2026 14:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_codigo_20_sem_coleta_usa_label_pos_coleta(): void
    {
        // Código 20 (Executado) sem dt_coleta → é pós-coleta pelo código
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '20',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_dt_coleta_com_codigo_pre_coleta_inconsistencia_forca_laudo(): void
    {
        // Inconsistência Tasy: dt_coleta set mas código ainda em pré-coleta → força "Aguardando laudo"
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '10',
            'status_laudo' => 'Prescrito',
            'dt_coleta' => '06/04/2026 10:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    // ── Exames: prescrição mais nova ──────────────────────────────────────────

    #[Test]
    public function motivo_exame_coletado_em_prescricao_mais_nova_retorna_aguardando_coleta(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'exame_coletado_em_prescricao_mais_nova' => true,
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_coletado_em_prescricao_mais_nova_com_scola_colheita_nao_altera_motivo(): void
    {
        // Scola é exibido em coluna separada — não influencia motivo_pendente
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'exame_coletado_em_prescricao_mais_nova' => true,
            'scola_status' => 'Coletado (aguardando resultado)',
            'scola_data_colheita' => '06/04/2026 10:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_prescricao_mais_nova_pendente_retorna_aguardando_coleta(): void
    {
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'prescricao_mais_nova_pendente_info' => '63999999 — 27/04/2026',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }
}
