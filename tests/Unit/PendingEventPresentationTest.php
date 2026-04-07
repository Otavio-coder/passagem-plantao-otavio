<?php

namespace Tests\Unit;

use App\Support\PendingEventPresentation;
use App\Support\PendingEventTypeClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventPresentationTest extends TestCase
{
    #[Test]
    public function prefers_explicit_execution_sector_label(): void
    {
        $label = PendingEventPresentation::executionSectorLabel([
            'setor_execucao' => 'Hemodinâmica',
            'setor_desc_raw' => 'Enfermaria',
            'local' => 'Sala 1',
        ]);

        $this->assertSame('Hemodinâmica', $label);
    }

    #[Test]
    public function falls_back_to_other_sector_fields(): void
    {
        $label = PendingEventPresentation::executionSectorLabel([
            'setor_execucao' => '',
            'setor_desc_raw' => 'Centro Cirúrgico',
        ]);

        $this->assertSame('Centro Cirúrgico', $label);
    }

    #[Test]
    public function returns_dash_when_no_sector_is_available(): void
    {
        $label = PendingEventPresentation::executionSectorLabel([]);

        $this->assertSame('-', $label);
    }

    #[Test]
    public function builds_a_richer_hemotherapy_description(): void
    {
        $description = PendingEventPresentation::hemotherapyDescription([
            'tipo_label' => 'Concentrado de Hemácias',
            'ds_procedimento_prescrito' => '',
            'ds_observacao' => 'cirurgia às 15h do dia 13/11/25',
            'ds_observacao_proc' => '',
            'ds_horarios' => '',
            'qt_vol_hemocomp' => 500,
            'via_aplicacao' => 'IV',
        ]);

        $this->assertSame(
            'Hemoterapia - Concentrado de Hemácias - 500 mL - IV - cirurgia às 15h do dia 13/11/25',
            $description
        );
    }

    #[Test]
    public function builds_a_richer_surgery_description(): void
    {
        $description = PendingEventPresentation::surgeryDescription([
            'descricao' => 'Dissecção de veia para colocação de cateter (Cirurgia realizada)',
            'local' => 'Centro Cirúrgico',
            'sala' => '3',
        ]);

        $this->assertSame(
            'Dissecção de veia para colocação de cateter - Centro Cirúrgico - Sala: 3',
            $description
        );
    }

    #[Test]
    public function returns_null_classification_for_surgery_events(): void
    {
        $classification = PendingEventPresentation::classificationLabel([
            'tipo_cirurgia_codigo' => 7,
            'ds_grupo_lab' => 'Grupo não usado',
        ], PendingEventTypeClassifier::SURGERY);

        $this->assertNull($classification);
    }

    #[Test]
    public function returns_null_classification_when_surgery_type_is_missing(): void
    {
        $classification = PendingEventPresentation::classificationLabel([
            'descricao' => 'Cirurgia sem tipo retornado',
        ], PendingEventTypeClassifier::SURGERY);

        $this->assertNull($classification);
    }

    #[Test]
    public function returns_lab_group_as_classification_for_non_surgery_events(): void
    {
        $classification = PendingEventPresentation::classificationLabel([
            'ds_grupo_lab' => 'Hematologia',
        ], PendingEventTypeClassifier::EXAM);

        $this->assertSame('Hematologia', $classification);
    }

    #[Test]
    public function builds_surgery_diagnostic_with_status_and_missing_fields(): void
    {
        $diagnostic = PendingEventPresentation::surgeryDiagnosticLabel([
            'status_agenda_codigo' => 'PS',
            'status_laudo' => 'Paciente em sala',
            'tipo_cirurgia_codigo' => null,
            'local' => null,
            'sala' => null,
        ]);

        $this->assertSame('Status agenda: PS - Paciente em sala', $diagnostic);
    }

    // ── motivoPendente — Exames ───────────────────────────────────────────────

    #[Test]
    public function motivo_exame_aguardando_coleta(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Pendente',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_urgente_aguardando_coleta(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Pendente',
            'urgente' => true,
        ]);

        $this->assertSame('Urgente — aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_aguardando_laudo_por_status(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Coletado',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_aguardando_laudo_por_dt_coleta(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Pendente',
            'dt_coleta' => '2026-04-06 10:00:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_em_analise(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Em análise',
            'urgente' => false,
        ]);

        $this->assertSame('Material em análise — aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_flags_diagnosticas_tem_prioridade(): void
    {
        $this->assertSame(
            'Realizado — prescrição não baixada no sistema',
            PendingEventPresentation::motivoPendente([
                'tipo' => 'exame',
                'foi_executado_sem_baixa' => true,
                'urgente' => true,
            ])
        );

        $this->assertSame(
            'Exame realizado em solicitação mais recente',
            PendingEventPresentation::motivoPendente([
                'tipo' => 'exame',
                'exame_coletado_em_prescricao_mais_nova' => true,
            ])
        );
    }

    // ── motivoPendente — Procedimentos ───────────────────────────────────────

    #[Test]
    public function motivo_procedimento_aguardando_execucao(): void
    {
        $this->assertSame(
            'Aguardando execução',
            PendingEventPresentation::motivoPendente(['tipo' => 'procedimento', 'urgente' => false])
        );
    }

    #[Test]
    public function motivo_procedimento_urgente(): void
    {
        $this->assertSame(
            'Urgente — aguardando execução',
            PendingEventPresentation::motivoPendente(['tipo' => 'procedimento', 'urgente' => true])
        );
    }

    // ── motivoPendente — Cirurgias ────────────────────────────────────────────

    #[Test]
    public function motivo_cirurgia_eletiva_aguardando(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Eletiva',
            'status_laudo' => 'Aguardando',
            'urgente' => false,
        ]);

        $this->assertSame('Cirurgia eletiva — aguardando realização', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_eletiva_confirmada(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Eletiva',
            'status_laudo' => 'Confirmada',
            'urgente' => false,
        ]);

        $this->assertSame('Cirurgia eletiva confirmada', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_urgencia_aguardando(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Urgência',
            'status_laudo' => 'Aguardando',
            'urgente' => true,
        ]);

        $this->assertSame('Cirurgia de urgência — aguardando realização', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_urgencia_confirmada(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Emergência',
            'status_laudo' => 'Confirmada',
            'urgente' => true,
        ]);

        $this->assertSame('Cirurgia de emergência — confirmada', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_em_preparo(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Eletiva',
            'status_laudo' => 'Em preparo',
            'urgente' => false,
        ]);

        $this->assertSame('Cirurgia em preparo', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_paciente_em_sala(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Eletiva',
            'status_laudo' => 'Paciente em sala',
            'urgente' => false,
        ]);

        $this->assertSame('Paciente em sala — cirurgia em andamento', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_aguardando_remarcacao(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Eletiva',
            'status_laudo' => 'Aguardando remarcação',
            'urgente' => false,
        ]);

        $this->assertSame('Cirurgia aguardando remarcação', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_ambulatorial_aguardando(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Ambulatorial',
            'status_laudo' => 'Aguardando',
            'urgente' => false,
        ]);

        $this->assertSame('Cirurgia ambulatorial — aguardando realização', $motivo);
    }

    #[Test]
    public function motivo_cirurgia_rotina_aguardando(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'cirurgia',
            'carater' => 'Rotina',
            'status_laudo' => 'Aguardando',
            'urgente' => false,
        ]);

        $this->assertSame('Cirurgia rotina — aguardando realização', $motivo);
    }

    // ── motivoPendente — Hemoterapia ──────────────────────────────────────────

    #[Test]
    public function motivo_hemoterapia_com_tipo_especifico(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'hemoterapia',
            'ie_tipo_hemoterap' => '1',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando transfusão de Concentrado de Hemácias', $motivo);
    }

    #[Test]
    public function motivo_hemoterapia_urgente_com_tipo(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'hemoterapia',
            'ie_tipo_hemoterap' => '3',
            'urgente' => true,
        ]);

        $this->assertSame('Urgente — aguardando transfusão de Plasma Fresco Congelado', $motivo);
    }

    #[Test]
    public function motivo_hemoterapia_sem_tipo_usa_hemocomponente(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'hemoterapia',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando transfusão de hemocomponente', $motivo);
    }

    #[Test]
    public function motivo_hemoterapia_usa_tipo_label_quando_disponivel(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'hemoterapia',
            'tipo_label' => 'Crioprecipitado',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando transfusão de Crioprecipitado', $motivo);
    }

    // ── motivoPendente — Quimioterapia ────────────────────────────────────────

    #[Test]
    public function motivo_quimioterapia_sem_ciclo(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'quimioterapia',
        ]);

        $this->assertSame('Sessão de quimioterapia agendada', $motivo);
    }

    #[Test]
    public function motivo_quimioterapia_com_ciclo(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'quimioterapia',
            'ciclo' => '3',
        ]);

        $this->assertSame('Sessão de quimioterapia agendada — Ciclo 3', $motivo);
    }

    // ── motivoPendente — Antimicrobianos ──────────────────────────────────────

    #[Test]
    public function motivo_antibiotico_sem_complemento(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'antibiotico',
        ]);

        $this->assertSame('Antimicrobiano em uso', $motivo);
    }

    #[Test]
    public function motivo_antibiotico_com_complemento(): void
    {
        $motivo = PendingEventPresentation::motivoPendente([
            'tipo' => 'antibiotico',
            'ds_complemento' => 'Dia 3 · 500mg · IV · 8/8h',
        ]);

        $this->assertSame('Antimicrobiano em uso — Dia 3 · 500mg · IV · 8/8h', $motivo);
    }
}
