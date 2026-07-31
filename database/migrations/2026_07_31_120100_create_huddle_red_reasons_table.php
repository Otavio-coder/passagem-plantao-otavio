<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motivos de "dia vermelho" (red day) associados a um dia de huddle.
     *
     * Cada linha é um motivo registrado para um huddle_patient_day. O método vigente
     * limita a 2 motivos por dia — regra aplicada na Action (RegisterRedReasonsAction),
     * não no schema, para permitir ajuste sem migração. category e reason_code guardam
     * os valores dos enums RedReasonCategory e RedReason.
     */
    public function up(): void
    {
        Schema::create('huddle_red_reasons', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('huddle_patient_day_id')
                ->constrained('huddle_patient_days')
                ->cascadeOnDelete();

            $table->string('category', 40);     // App\Enums\Huddle\RedReasonCategory
            $table->string('reason_code', 60);  // App\Enums\Huddle\RedReason

            // Motivo sugerido automaticamente (Tasy/pendências) vs. informado manualmente
            $table->boolean('auto_derived')->default(false);

            $table->timestamps();

            // Evita o mesmo motivo duplicado no mesmo dia
            $table->unique(['huddle_patient_day_id', 'reason_code']);

            // Agregação de causas por categoria nos relatórios
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_red_reasons');
    }
};
