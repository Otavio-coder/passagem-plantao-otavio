<?php

namespace Database\Seeders;

use App\Models\Huddle\HuddleUnitQuestion;
use Illuminate\Database\Seeder;

/**
 * Popula a tabela huddle_unit_questions com as perguntas padrão do Round Unidade.
 *
 * Replica as perguntas que estavam hardcoded na view — agora lidas do MySQL.
 * Idempotente: usa updateOrCreate com `field_key` como chave.
 */
class HuddleUnitQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // ── Eixo 1 · Ocupação e fluxo ────────────────────────────────
            ['axis' => 1, 'axis_label' => 'Ocupação e fluxo', 'field_key' => 'expected_discharges', 'label' => 'Altas Previstas', 'field_type' => 'number', 'sort_order' => 1],
            ['axis' => 1, 'axis_label' => 'Ocupação e fluxo', 'field_key' => 'expected_admissions', 'label' => 'Admissões Previstas', 'field_type' => 'number', 'sort_order' => 2],
            ['axis' => 1, 'axis_label' => 'Ocupação e fluxo', 'field_key' => 'blocked_beds_isolation', 'label' => 'Leitos Bloqueados por isolamento', 'field_type' => 'number', 'sort_order' => 3],
            ['axis' => 1, 'axis_label' => 'Ocupação e fluxo', 'field_key' => 'blocked_beds_maintenance', 'label' => 'Leitos Bloqueados por manutenção', 'field_type' => 'number', 'sort_order' => 4],

            // ── Eixo 2 · Risco clínico e segurança ──────────────────────
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'critical_patient_no_bed', 'label' => 'Paciente Grave sem leito?', 'field_type' => 'boolean', 'sort_order' => 1],
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'critical_medication_failure', 'label' => 'Falha de Medicação Crítica?', 'field_type' => 'boolean', 'sort_order' => 2],
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'adverse_event_24h', 'label' => 'Evento adverso (24h)', 'field_type' => 'boolean', 'sort_order' => 3],
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'physical_chemical_restraint', 'label' => 'Contenção Física e/ou Química', 'field_type' => 'boolean', 'sort_order' => 4],
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'barrier_breach', 'label' => 'Quebra de barreira?', 'field_type' => 'boolean', 'sort_order' => 5],
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'pressure_injuries', 'label' => 'LPP', 'field_type' => 'number', 'sort_order' => 6],
            ['axis' => 2, 'axis_label' => 'Risco clínico e segurança', 'field_key' => 'falls', 'label' => 'Quedas', 'field_type' => 'number', 'sort_order' => 7],

            // ── Eixo 3 · Condições operacionais ─────────────────────────
            ['axis' => 3, 'axis_label' => 'Condições operacionais', 'field_key' => 'staff_shortage', 'label' => 'Déficit de Equipe', 'field_type' => 'boolean', 'sort_order' => 1],
            ['axis' => 3, 'axis_label' => 'Condições operacionais', 'field_key' => 'critical_exam_delay', 'label' => 'Atraso de Exame Crítico', 'field_type' => 'boolean', 'sort_order' => 2],

            // ── Eixo 4 · Classificação ──────────────────────────────────
            ['axis' => 4, 'axis_label' => 'Classificação', 'field_key' => 'unit_classification', 'label' => 'Classificação da unidade', 'field_type' => 'select', 'sort_order' => 1, 'options' => [
                ['value' => 'verde', 'label' => 'Verde'],
                ['value' => 'amarelo', 'label' => 'Amarelo'],
                ['value' => 'vermelho', 'label' => 'Vermelho'],
            ]],
            ['axis' => 4, 'axis_label' => 'Classificação', 'field_key' => 'justification', 'label' => 'Justificativa', 'field_type' => 'text', 'sort_order' => 2],
            ['axis' => 4, 'axis_label' => 'Classificação', 'field_key' => 'immediate_measures', 'label' => 'Medidas Imediatas', 'field_type' => 'text', 'sort_order' => 3],
        ];

        foreach ($questions as $q) {
            HuddleUnitQuestion::updateOrCreate(
                ['field_key' => $q['field_key']],
                $q,
            );
        }
    }
}
