<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca o momento em que o checklist do dia foi finalizado.
     * Após finalizado, nenhum item pode ser alterado — somente leitura.
     */
    public function up(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->timestamp('finalized_at')->nullable()->after('comments');
        });
    }

    public function down(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->dropColumn('finalized_at');
        });
    }
};
