<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campo condicional de comentários do dia — preenchido quando o dia é vermelho.
     */
    public function up(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->text('comments')->nullable()->after('clinical_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->dropColumn('comments');
        });
    }
};
