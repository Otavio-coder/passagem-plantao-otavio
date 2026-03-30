<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks for clean drop
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::dropIfExists('chat_reacoes');
        Schema::dropIfExists('chat_auditoria');
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_sessoes');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // As tabelas abaixo podem já existir se a migration de preparação
        // (2026_03_13_..._create_new_chat_tables_without_dropping_old) rodou
        // antes (fluxo de migração de produção com importação de dados).

        // ── chat_messages ────────────────────────────────────────────────────
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

        // ── chat_reactions ───────────────────────────────────────────────────
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

        // ── chat_message_pins ────────────────────────────────────────────────
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

                // Index for fast "active pin" query: WHERE unpinned_at IS NULL
                $table->index(['nr_atendimento', 'unpinned_at']);
            });
        }

        // ── user_sector_preferences ──────────────────────────────────────────
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

        // Recreate the old tables (minimal structure for rollback)
        Schema::create('chat_sessoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nr_atendimento', 50)->index();
            $table->string('turno_id', 20)->index();
            $table->date('data_sessao')->index();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fim')->nullable();
            $table->boolean('encerrada')->default(false);
            $table->integer('total_mensagens')->default(0);
            $table->timestamps();
            $table->unique(['nr_atendimento', 'turno_id', 'data_sessao']);
        });

        Schema::create('chat_mensagens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sessao_id');
            $table->string('nr_atendimento', 50)->index();
            $table->string('turno_id', 20)->index();
            $table->unsignedBigInteger('usuario_id')->index();
            $table->text('mensagem');
            $table->timestamp('dt_criacao')->useCurrent();
            $table->timestamp('dt_edicao')->nullable();
            $table->boolean('is_fixed')->default(false);
            $table->unsignedBigInteger('fixed_by')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->foreign('sessao_id')->references('id')->on('chat_sessoes')->onDelete('cascade');
        });

        Schema::create('chat_auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mensagem_id')->nullable();
            $table->unsignedBigInteger('sessao_id')->nullable();
            $table->string('nr_atendimento', 50)->index();
            $table->string('turno_id', 20);
            $table->string('acao', 100);
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('dt_acao')->useCurrent();
            $table->timestamps();
        });

        Schema::create('chat_reacoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mensagem_id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('tipo', 20)->default('check');
            $table->timestamps();
            $table->unique(['mensagem_id', 'usuario_id']);
        });
    }
};
