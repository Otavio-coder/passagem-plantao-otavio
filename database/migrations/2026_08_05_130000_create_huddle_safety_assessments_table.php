<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Card do Huddle de Segurança (gestão à vista), preenchido por unidade/dia.
     *
     * Uma linha por setor (sector_id) por dia (huddle_date), compartilhada por todos
     * os leitos daquela unidade. Organiza os 4 eixos: ocupação e fluxo, risco clínico
     * e segurança, condições operacionais e a classificação de risco da unidade.
     */
    public function up(): void
    {
        Schema::create('huddle_safety_assessments', function (Blueprint $table): void {
            $table->id();

            $table->unsignedInteger('sector_id');
            $table->date('huddle_date');

            // Eixo 1 — Ocupação e fluxo (contagens)
            $table->unsignedSmallInteger('expected_discharges')->nullable();       // Altas Previstas
            $table->unsignedSmallInteger('expected_admissions')->nullable();       // Admissões Previstas
            $table->unsignedSmallInteger('blocked_beds_isolation')->nullable();    // Leitos bloqueados por isolamento
            $table->unsignedSmallInteger('blocked_beds_maintenance')->nullable();  // Leitos bloqueados por manutenção

            // Eixo 2 — Risco clínico e segurança (sim/não + contagens)
            $table->boolean('critical_patient_no_bed')->nullable();     // Paciente grave sem leito?
            $table->boolean('critical_medication_failure')->nullable(); // Falha de medicação crítica?
            $table->boolean('adverse_event_24h')->nullable();           // Evento adverso (24h)
            $table->boolean('physical_chemical_restraint')->nullable(); // Contenção física e/ou química
            $table->boolean('barrier_breach')->nullable();              // Quebra de barreira?
            $table->unsignedSmallInteger('pressure_injuries')->nullable(); // LPP
            $table->unsignedSmallInteger('falls')->nullable();             // Quedas

            // Eixo 3 — Condições operacionais (sim/não)
            $table->boolean('staff_shortage')->nullable();     // Déficit de equipe
            $table->boolean('critical_exam_delay')->nullable(); // Atraso de exame crítico

            // Eixo 4 — Classificação de risco da unidade
            $table->string('unit_classification', 10)->nullable(); // App\Enums\Huddle\UnitClassification
            $table->text('justification')->nullable();             // Justificativa
            $table->text('immediate_measures')->nullable();        // Medidas imediatas

            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');

            $table->timestamps();

            // Um único card por unidade por dia
            $table->unique(['sector_id', 'huddle_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_safety_assessments');
    }
};
