<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar e criar tabelas de chat se não existirem
        if (!Schema::hasTable('chat_sessoes')) {
            Schema::create('chat_sessoes', function (Blueprint $table) {
                $table->id();
                $table->string('nr_atendimento', 50)->index();
                $table->string('turno_id', 20)->index();
                $table->date('data_sessao')->index();
                $table->timestamp('inicio')->nullable();
                $table->timestamp('fim')->nullable();
                $table->boolean('encerrada')->default(false);
                $table->json('usuarios_participantes')->nullable();
                $table->integer('total_mensagens')->default(0);
                $table->timestamps();
                
                $table->unique(['nr_atendimento', 'turno_id', 'data_sessao']);
                $table->index(['nr_atendimento', 'data_sessao']);
            });
        }

        if (!Schema::hasTable('chat_mensagens')) {
            Schema::create('chat_mensagens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sessao_id')->constrained('chat_sessoes')->onDelete('cascade');
                $table->string('nr_atendimento', 50)->index();
                $table->string('turno_id', 20)->index();
                $table->unsignedBigInteger('usuario_id')->index();
                $table->text('mensagem');
                $table->timestamp('dt_criacao')->useCurrent();
                $table->timestamp('dt_edicao')->nullable();
                $table->boolean('is_fixed')->default(false);
                $table->unsignedBigInteger('fixed_by')->nullable();
                $table->timestamp('fixed_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('expiracao')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
                
                $table->index(['sessao_id', 'is_deleted']);
                $table->index(['nr_atendimento', 'turno_id', 'dt_criacao']);
            });
        }

        if (!Schema::hasTable('chat_auditoria')) {
            Schema::create('chat_auditoria', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mensagem_id')->nullable();
                $table->unsignedBigInteger('sessao_id')->nullable();
                $table->string('nr_atendimento', 50)->index();
                $table->string('turno_id', 20);
                $table->string('acao', 100);
                $table->unsignedBigInteger('usuario_id');
                $table->timestamp('dt_acao')->useCurrent();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('detalhes_acao')->nullable();
                $table->json('estado_anterior')->nullable();
                $table->json('estado_posterior')->nullable();
                $table->timestamps();
                
                $table->index(['nr_atendimento', 'acao']);
                $table->index(['usuario_id', 'dt_acao']);
            });
        }

        // Verificar se as colunas necessárias existem
        if (Schema::hasTable('chat_mensagens')) {
            if (!Schema::hasColumn('chat_mensagens', 'is_deleted')) {
                Schema::table('chat_mensagens', function (Blueprint $table) {
                    $table->boolean('is_deleted')->default(false)->after('expiracao');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_auditoria');
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_sessoes');
    }
};