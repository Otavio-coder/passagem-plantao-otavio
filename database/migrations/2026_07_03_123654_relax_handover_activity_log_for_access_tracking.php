<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite reutilizar handover_activity_log como trilha de auditoria de acesso:
     * event vira string (novos eventos report_open/analysis_open) e
     * nr_atendimento/sector_id ficam nullable (abertura de relatório não tem atendimento).
     */
    public function up(): void
    {
        Schema::table('handover_activity_log', function (Blueprint $table) {
            $table->string('event', 30)->change();
            $table->unsignedBigInteger('nr_atendimento')->nullable()->change();
            $table->unsignedInteger('sector_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('handover_activity_log')->whereNotIn('event', ['modal_open', 'chat_post'])->delete();

        Schema::table('handover_activity_log', function (Blueprint $table) {
            $table->enum('event', ['modal_open', 'chat_post'])->change();
            $table->unsignedBigInteger('nr_atendimento')->nullable(false)->change();
            $table->unsignedInteger('sector_id')->nullable(false)->change();
        });
    }
};
