<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado diário de um paciente no Huddle de Gestão de Altas.
     *
     * Uma linha por internação (nr_atendimento) por dia (huddle_date). Guarda a cor
     * do dia (Red2Green), a previsão de alta (EDD) e os critérios clínicos de alta (CCD).
     * Os contadores "red/green por internação" são derivados por agregação desta tabela
     * agrupada por nr_atendimento — não há tabela de contador.
     */
    public function up(): void
    {
        Schema::create('huddle_patient_days', function (Blueprint $table): void {
            $table->id();

            $table->integer('nr_atendimento'); // internação no Tasy — única por internação
            $table->unsignedInteger('sector_id');
            $table->date('huddle_date');

            // Cor do dia: valor do enum App\Enums\Huddle\DayColor ('red' | 'green').
            // Metodologia: todo dia inicia vermelho.
            $table->string('color', 10)->default('red');

            $table->date('expected_discharge_date')->nullable(); // EDD
            $table->text('clinical_criteria')->nullable();        // CCD
            $table->timestamp('senior_reviewed_at')->nullable();  // elemento S do SAFER

            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');

            $table->timestamps();

            // Um único registro de huddle por internação por dia (idempotência da materialização)
            $table->unique(['nr_atendimento', 'huddle_date']);

            // Leitura do painel: todos os dias de um setor numa data
            $table->index(['sector_id', 'huddle_date']);

            // Cálculo dos contadores red/green ao longo da internação
            $table->index(['nr_atendimento', 'color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_patient_days');
    }
};
