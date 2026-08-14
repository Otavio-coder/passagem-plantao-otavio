<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que o registro diário do Huddle seja criado pela rotina automática
     * (comando huddle:open-day), quando não há usuário logado. Nesse caso,
     * created_by_user_id fica nulo = "criado pelo sistema".
     */
    public function up(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
            $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
            $table->unsignedBigInteger('created_by_user_id')->nullable(false)->change();
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });
    }
};
