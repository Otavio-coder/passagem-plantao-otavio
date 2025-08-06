<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop existing tables
        Schema::dropIfExists('chat_auditoria');
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_sessoes');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Chat sessions table
        Schema::create('chat_sessoes', function (Blueprint $table) {
            $table->id();
            $table->string('nr_atendimento', 50)->index();
            $table->enum('turno_id', ['dia', 'noite'])->index();
            $table->date('data_sessao')->index();
            $table->datetime('inicio')->nullable();
            $table->datetime('fim')->nullable();
            $table->boolean('encerrada')->default(false)->index();
            $table->json('usuarios_participantes')->nullable();
            $table->integer('total_mensagens')->default(0);
            $table->unsignedInteger('criado_por')->nullable();
            $table->foreign('criado_por')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            
            // Composite indexes
            $table->unique(['nr_atendimento', 'turno_id', 'data_sessao'], 'unique_session');
            $table->index(['data_sessao', 'turno_id', 'encerrada'], 'idx_session_filter');
        });

        // Chat messages table
        Schema::create('chat_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessao_id')->constrained('chat_sessoes')->onDelete('cascade');
            $table->string('nr_atendimento', 50)->index();
            $table->enum('turno_id', ['dia', 'noite']);
            $table->unsignedInteger('usuario_id')->index();
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            $table->text('mensagem');
            $table->datetime('dt_criacao')->index();
            $table->datetime('dt_edicao')->nullable();
            $table->boolean('is_fixed')->default(false)->index();
            $table->unsignedInteger('fixed_by')->nullable();
            $table->foreign('fixed_by')->references('id')->on('users')->onDelete('set null');
            $table->datetime('fixed_at')->nullable();
            $table->datetime('resolved_at')->nullable();
            $table->datetime('expiracao')->nullable();
            $table->boolean('is_deleted')->default(false)->index();
            $table->datetime('deleted_at')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            
            // Performance indexes
            $table->index(['sessao_id', 'dt_criacao', 'is_deleted'], 'idx_session_messages');
            $table->index(['is_fixed', 'sessao_id'], 'idx_pinned_messages');
            $table->index(['nr_atendimento', 'turno_id', 'dt_criacao'], 'idx_patient_timeline');
        });

        // Comprehensive audit table
        Schema::create('chat_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->nullable()->constrained('chat_mensagens')->onDelete('cascade');
            $table->foreignId('sessao_id')->nullable()->constrained('chat_sessoes')->onDelete('cascade');
            $table->string('nr_atendimento', 50)->index();
            $table->enum('turno_id', ['dia', 'noite']);
            $table->enum('acao', [
                'sessao_criada',
                'sessao_encerrada',
                'mensagem_enviada',
                'mensagem_editada',
                'mensagem_fixada',
                'mensagem_desfixada',
                'mensagem_removida',
                'historico_acessado',
                'usuario_entrou',
                'usuario_saiu'
            ])->index();
            $table->unsignedInteger('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            $table->datetime('dt_acao')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('detalhes_acao')->nullable();
            $table->json('estado_anterior')->nullable();
            $table->json('estado_posterior')->nullable();
            $table->timestamps();
            
            // Audit indexes
            $table->index(['nr_atendimento', 'dt_acao'], 'idx_patient_audit');
            $table->index(['usuario_id', 'dt_acao'], 'idx_user_audit');
            $table->index(['acao', 'dt_acao'], 'idx_action_audit');
            $table->index(['dt_acao', 'acao', 'nr_atendimento'], 'idx_timeline_audit');
        });
    }

    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('chat_auditoria');
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_sessoes');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};