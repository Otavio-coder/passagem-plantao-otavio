<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de PREPARAÇÃO para produção.
 *
 * Cria as novas tabelas do chat (chat_messages, chat_reactions,
 * chat_message_pins, user_sector_preferences) sem dropar as antigas
 * (chat_sessoes, chat_mensagens), permitindo que o command
 * chat:import-prod rode com ambas coexistindo.
 *
 * Ordem de execução em produção:
 *   1. php artisan migrate  ← roda esta + chat_messages_archive
 *   2. php artisan chat:import-prod --execute
 *   3. php artisan migrate  ← roda a 2026_02_23 que dropa as antigas
 *
 * Esta migration é um no-op se as tabelas já existirem (segura para dev).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nr_atendimento', 50)->index();
                $table->string('cd_pessoa_fisica', 50)->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->mediumText('content');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
                $table->index(['nr_atendimento', 'created_at']);
            });
        }

        if (!Schema::hasTable('chat_reactions')) {
            Schema::create('chat_reactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('message_id')
                    ->constrained('chat_messages')
                    ->onDelete('cascade');
                $table->unsignedBigInteger('user_id');
                $table->string('type', 20)->default('check');
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['message_id', 'user_id']);
                $table->index('message_id');
            });
        }

        if (!Schema::hasTable('chat_message_pins')) {
            Schema::create('chat_message_pins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('message_id')
                    ->constrained('chat_messages')
                    ->onDelete('cascade');
                $table->string('nr_atendimento', 50)->index();
                $table->unsignedBigInteger('pinned_by');
                $table->timestamp('pinned_at')->useCurrent();
                $table->timestamp('unpinned_at')->nullable();
                $table->unsignedBigInteger('unpinned_by')->nullable();
                $table->index(['nr_atendimento', 'unpinned_at']);
            });
        }

        if (!Schema::hasTable('user_sector_preferences')) {
            Schema::create('user_sector_preferences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');
                $table->string('sector_code');
                $table->string('sector_name')->nullable();
                $table->string('hospital_code')->nullable();
                $table->string('hospital_name')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['user_id', 'sector_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sector_preferences');
        Schema::dropIfExists('chat_message_pins');
        Schema::dropIfExists('chat_reactions');
        Schema::dropIfExists('chat_messages');
    }
};
