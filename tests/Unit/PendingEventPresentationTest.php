<?php

namespace Tests\Unit;

use App\Support\PendingEventHelper;
use App\Support\PendingEventTypeClassifier;
use App\View\Presenters\PendingEventPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventPresentationTest extends TestCase
{
    #[Test]
    public function it_uses_alta_icon_for_all_discharge_pending_types(): void
    {
        $data = PendingEventPresenter::buildPendingModalData([
            ['tipo' => 'alta', 'descricao' => 'Alta efetivada', 'icone' => 'alert.svg'],
            ['tipo' => 'alta_medica', 'descricao' => 'Alta médica', 'icone' => 'something-else.svg'],
            ['tipo' => 'previsao_alta', 'descricao' => 'Previsão de alta', 'icone' => 'clock.svg'],
        ]);

        $icons = collect($data['events'])->pluck('icone')->all();

        $this->assertSame(['alta.svg', 'alta.svg', 'alta.svg'], $icons);
        $this->assertSame('alta.svg', PendingEventPresenter::firstEventCardStyle($data['events'][0])['icon']);
    }

    #[Test]
    public function prefers_explicit_execution_sector_label(): void
    {
        $label = PendingEventPresenter::executionSectorLabel([
            'setor_execucao' => 'Hemodinâmica',
            'setor_desc_raw' => 'Enfermaria',
            'local' => 'Sala 1',
        ]);

        $this->assertSame('Hemodinâmica', $label);
    }

    #[Test]
    public function falls_back_to_other_sector_fields(): void
    {
        $label = PendingEventPresenter::executionSectorLabel([
            'setor_execucao' => '',
            'setor_desc_raw' => 'Centro Cirúrgico',
        ]);

        $this->assertSame('Centro Cirúrgico', $label);
    }

    #[Test]
    public function returns_dash_when_no_sector_is_available(): void
    {
        $label = PendingEventPresenter::executionSectorLabel([]);

        $this->assertSame('-', $label);
    }

    #[Test]
    public function builds_a_richer_hemotherapy_description(): void
    {
        $description = PendingEventPresenter::hemotherapyDescription([
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
        $description = PendingEventPresenter::surgeryDescription([
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
        $classification = PendingEventPresenter::classificationLabel([
            'tipo_cirurgia_codigo' => 7,
            'ds_grupo_lab' => 'Grupo não usado',
        ], PendingEventTypeClassifier::SURGERY);

        $this->assertNull($classification);
    }

    #[Test]
    public function returns_null_classification_when_surgery_type_is_missing(): void
    {
        $classification = PendingEventPresenter::classificationLabel([
            'descricao' => 'Cirurgia sem tipo retornado',
        ], PendingEventTypeClassifier::SURGERY);

        $this->assertNull($classification);
    }

    #[Test]
    public function returns_lab_group_as_classification_for_non_surgery_events(): void
    {
        $classification = PendingEventPresenter::classificationLabel([
            'ds_grupo_lab' => 'Hematologia',
        ], PendingEventTypeClassifier::EXAM);

        $this->assertSame('Hematologia', $classification);
    }

    #[Test]
    public function builds_surgery_diagnostic_with_status_and_missing_fields(): void
    {
        $diagnostic = PendingEventPresenter::surgeryDiagnosticLabel([
            'status_agenda_codigo' => 'PS',
            'status_laudo' => 'Paciente em sala',
            'tipo_cirurgia_codigo' => null,
            'local' => null,
            'sala' => null,
        ]);

        $this->assertSame('Paciente em sala', $diagnostic);
    }

    // ── motivoPendente — Exames ───────────────────────────────────────────────

    #[Test]
    public function motivo_exame_aguardando_coleta(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Pendente',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_urgente_aguardando_coleta(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Pendente',
            'urgente' => true,
        ]);

        $this->assertSame('Urgente — aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_usa_label_valor_dominio_diretamente(): void
    {
        // Quando status_laudo (ds_status_execucao_label de valor_dominio) está disponível,
        // é retornado diretamente — prioridade sobre nossa interpretação.
        // Código 20 do domínio 1226 = "Coleta do material" no Tasy.
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Coleta do material',
            'ie_status_execucao' => '20',
            'urgente' => false,
        ]);

        $this->assertSame('Coleta do material', $motivo);
    }

    #[Test]
    public function motivo_exame_aguardando_laudo_por_dt_coleta_sem_label_tasy(): void
    {
        // Quando status_laudo está vazio (valor_dominio não retornou label),
        // fallback para nossa interpretação baseada em dt_coleta.
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => '',
            'dt_coleta' => '2026-04-06 10:00:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_dt_coleta_com_status_pre_coleta_usa_fallback(): void
    {
        // dt_coleta preenchido mas ie_status_execucao ainda em código pré-coleta (10="Prescrito")
        // indica inconsistência no Tasy — coleta foi registrada mas status não atualizado.
        // Deve retornar fallback "Aguardando laudo", não o label "Prescrito" do domínio 1226.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '10',
            'status_laudo' => 'Prescrito',
            'dt_coleta' => '2026-01-02 16:38:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_dt_coleta_com_status_pos_coleta_usa_label_tasy(): void
    {
        // Código 22 (Aguardando Laudo) com dt_coleta = estado consistente → usa label Tasy.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '22',
            'status_laudo' => 'Aguardando Laudo',
            'dt_coleta' => '2026-06-24 08:00:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando Laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_em_analise_usa_label_tasy(): void
    {
        // Código 30 do domínio 1226 = "Laudo sem liberação" no Tasy.
        // Label de valor_dominio é mais preciso que nossa interpretação genérica.
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'exame',
            'status_laudo' => 'Digitação do resultado',
            'ie_status_execucao' => '30',
            'urgente' => false,
        ]);

        $this->assertSame('Digitação do resultado', $motivo);
    }

    #[Test]
    public function motivo_exame_flags_diagnosticas_tem_prioridade(): void
    {
        $this->assertSame(
            'Realizado — prescrição não baixada no sistema',
            PendingEventPresenter::motivoPendente([
                'tipo' => 'exame',
                'foi_executado_sem_baixa' => true,
                'urgente' => true,
            ])
        );

        $this->assertSame(
            'Exame realizado em solicitação mais recente',
            PendingEventPresenter::motivoPendente([
                'tipo' => 'exame',
                'exame_coletado_em_prescricao_mais_nova' => true,
            ])
        );
    }

    #[Test]
    public function motivo_exame_nao_e_afetado_por_scola_data_liberado(): void
    {
        // scola_status e scola_data_liberado são exibidos como label separado na view (Scola:),
        // não devem contaminar motivo_pendente que é exclusivamente Tasy.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'scola_status' => 'Laudo liberado (aguardando integração TASY)',
            'scola_data_liberado' => '2026-06-24 08:00:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_nao_e_afetado_por_scola_colheita(): void
    {
        // Mesmo com coleta registrada no Scola, motivo_pendente reflete estado Tasy.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'scola_status' => 'Coletado (aguardando resultado)',
            'scola_data_colheita' => '2026-06-24 08:00:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando coleta', $motivo);
    }

    #[Test]
    public function motivo_exame_com_dt_coleta_tasy_retorna_aguardando_laudo(): void
    {
        // dt_coleta no Tasy (prescr_procedimento) → estado pós-coleta conforme Tasy.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'dt_coleta' => '2026-06-24 07:00:00',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando laudo', $motivo);
    }

    #[Test]
    public function motivo_exame_scola_e_foi_executado_sem_baixa_ambos_retornam_status_tasy(): void
    {
        // com e sem foi_executado_sem_baixa, se não há dt_coleta Tasy → ambos retornam Aguardando coleta.
        $semBaixa = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'foi_executado_sem_baixa' => true,
            'scola_status' => 'Laudo liberado (aguardando integração TASY)',
            'scola_data_liberado' => '2026-06-24 08:00:00',
        ]);

        $semFlag = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'scola_status' => 'Laudo liberado (aguardando integração TASY)',
            'scola_data_liberado' => '2026-06-24 08:00:00',
        ]);

        $this->assertSame($semBaixa, $semFlag);
        $this->assertSame('Aguardando coleta', $semBaixa);
    }

    // ── motivoPendente — Procedimentos ───────────────────────────────────────

    #[Test]
    public function motivo_procedimento_aguardando_execucao(): void
    {
        $this->assertSame(
            'Aguardando execução',
            PendingEventPresenter::motivoPendente(['tipo' => 'procedimento', 'urgente' => false])
        );
    }

    #[Test]
    public function motivo_procedimento_urgente(): void
    {
        $this->assertSame(
            'Urgente — aguardando execução',
            PendingEventPresenter::motivoPendente(['tipo' => 'procedimento', 'urgente' => true])
        );
    }

    // ── motivoPendente — Cirurgias ────────────────────────────────────────────

    #[Test]
    public function motivo_cirurgia_eletiva_aguardando(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'hemoterapia',
            'ie_tipo_hemoterap' => '1',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando transfusão de Concentrado de Hemácias', $motivo);
    }

    #[Test]
    public function motivo_hemoterapia_urgente_com_tipo(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'hemoterapia',
            'ie_tipo_hemoterap' => '3',
            'urgente' => true,
        ]);

        $this->assertSame('Urgente — aguardando transfusão de Plasma Fresco Congelado', $motivo);
    }

    #[Test]
    public function motivo_hemoterapia_sem_tipo_usa_hemocomponente(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'hemoterapia',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando transfusão de hemocomponente', $motivo);
    }

    #[Test]
    public function motivo_hemoterapia_usa_tipo_label_quando_disponivel(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
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
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'quimioterapia',
        ]);

        $this->assertSame('Sessão de quimioterapia agendada', $motivo);
    }

    #[Test]
    public function motivo_quimioterapia_com_ciclo(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'quimioterapia',
            'ciclo' => '3',
        ]);

        $this->assertSame('Sessão de quimioterapia agendada — Ciclo 3', $motivo);
    }

    // ── motivoPendente — Antimicrobianos ──────────────────────────────────────

    #[Test]
    public function motivo_antibiotico_sem_complemento(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'antibiotico',
        ]);

        $this->assertSame('Dose não administrada', $motivo);
    }

    #[Test]
    public function motivo_antibiotico_com_complemento(): void
    {
        $motivo = PendingEventPresenter::motivoPendente([
            'tipo' => 'antibiotico',
            'ds_complemento' => 'Dia 3 · 500mg · IV · 8/8h',
        ]);

        $this->assertSame('Dose não administrada — Dia 3 · 500mg · IV · 8/8h', $motivo);
    }

    #[Test]
    public function builds_pending_modal_data_with_sorted_groups_and_near_flag(): void
    {
        $data = PendingEventPresenter::buildPendingModalData([
            [
                'tipo' => 'procedimento',
                'descricao' => 'ECG',
                'dt_evento' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ],
            [
                'tipo' => 'alta',
                'descricao' => 'Alta efetivada',
                'dt_evento' => now()->format('Y-m-d H:i:s'),
            ],
        ]);

        $this->assertCount(2, $data['events']);
        $this->assertSame('alta', $data['groups'][0]['type']);
        $this->assertSame('Alta Efetivada', $data['groups'][0]['label']);
        $this->assertTrue((bool) $data['events'][1]['is_near']);
        $this->assertFalse((bool) $data['events'][0]['is_near']);
    }

    // ── motivoPendente — Tipos de alta ───────────────────────────────────────────

    #[Test]
    public function motivo_alta_medica_retorna_vazio(): void
    {
        // alta_medica não tem motivo clínico — grupo e descrição já informam o contexto.
        // Sem este guard, fromPendingEvent() classifica como PROCEDURE → "Urgente — aguardando execução".
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'alta_medica',
            'urgente' => true,
        ]);

        $this->assertSame('', $motivo);
    }

    #[Test]
    public function motivo_alta_e_previsao_alta_retornam_vazio(): void
    {
        $this->assertSame('', PendingEventHelper::motivoPendente(['tipo' => 'alta']));
        $this->assertSame('', PendingEventHelper::motivoPendente(['tipo' => 'previsao_alta']));
    }

    // ── Correção de roteamento: hemoterapia/quimio com _fonte=agenda ─────────────

    #[Test]
    public function motivo_hemoterapia_com_fonte_agenda_nao_usa_motivo_agenda(): void
    {
        // Bug corrigido: _fonte='agenda' não deve desviar hemoterapia para motivoAgenda.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'hemoterapia',
            'ie_tipo_hemoterap' => '1',
            '_fonte' => 'agenda',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando transfusão de Concentrado de Hemácias', $motivo);
        $this->assertStringNotContainsString('Agendado', $motivo);
    }

    #[Test]
    public function motivo_quimioterapia_com_fonte_agenda_nao_usa_motivo_agenda(): void
    {
        // Bug corrigido: _fonte='agenda' não deve desviar quimioterapia para motivoAgenda.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'quimioterapia',
            'ciclo' => '2',
            '_fonte' => 'agenda',
        ]);

        $this->assertStringContainsString('quimioterapia', mb_strtolower($motivo));
        $this->assertStringNotContainsString('Agendado', $motivo);
    }

    #[Test]
    public function motivo_quimioterapia_com_status_label_usa_separador_correto(): void
    {
        // Quando status_laudo está preenchido e ie_status_agenda não tem match,
        // deve usar ' — ' como separador (não ': ').
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'quimioterapia',
            'status_laudo' => 'Prescrito',
        ]);

        $this->assertSame('Sessão de quimioterapia — prescrito', $motivo);
    }

    #[Test]
    public function motivo_procedimento_prioriza_label_tasy(): void
    {
        // Quando status_laudo (ds_status_execucao_label de valor_dominio) está disponível,
        // deve ser usado diretamente em vez de nossa interpretação do código.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'procedimento',
            'ie_status_execucao' => '15',
            'status_laudo' => 'Em exame',
            'urgente' => false,
        ]);

        $this->assertSame('Em exame', $motivo);
    }

    #[Test]
    public function motivo_procedimento_codigos_19_20_unificados(): void
    {
        // Códigos 19 e 20 do domínio 1226 são semanticamente equivalentes para procedimentos.
        $motivo19 = PendingEventHelper::motivoPendente(['tipo' => 'procedimento', 'ie_status_execucao' => '19']);
        $motivo20 = PendingEventHelper::motivoPendente(['tipo' => 'procedimento', 'ie_status_execucao' => '20']);

        $this->assertSame($motivo19, $motivo20);
        $this->assertSame('Executado — aguardando baixa', $motivo19);
    }

    #[Test]
    public function motivo_exame_usa_label_tasy_no_fallback_tasy(): void
    {
        // Quando status_laudo (valor_dominio) está disponível para exame sem Scola,
        // deve ser priorizado sobre nossa interpretação do código.
        $motivo = PendingEventHelper::motivoPendente([
            'tipo' => 'exame',
            'ie_status_execucao' => '22',
            'status_laudo' => 'Aguardando Laudo',
            'urgente' => false,
        ]);

        $this->assertSame('Aguardando Laudo', $motivo);
    }

    #[Test]
    public function resolves_first_event_respecting_front_window_for_exam_and_procedure(): void
    {
        $data = PendingEventPresenter::buildPendingModalData([
            [
                'tipo' => 'exame',
                'descricao' => 'Exame distante',
                'dt_evento' => now()->addDays(4)->format('Y-m-d H:i:s'),
            ],
            [
                'tipo' => 'quimioterapia',
                'descricao' => 'Quimio válida',
                'dt_evento' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ],
        ]);

        $this->assertNotNull($data['first_event']);
        $this->assertSame('quimioterapia', $data['first_event']['tipo']);
    }
}
