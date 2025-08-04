<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Disable foreign key checks to avoid constraint issues
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop existing tables in correct order (respecting foreign keys)
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
            $table->boolean('encerrada')->default(false);
            $table->json('usuarios_participantes')->nullable();
            $table->integer('total_mensagens')->default(0);
            $table->timestamps();
            
            // Composite indexes for performance
            $table->index(['nr_atendimento', 'turno_id', 'data_sessao'], 'idx_session_lookup');
            $table->index(['data_sessao', 'turno_id'], 'idx_history_filter');
        });

        // Chat messages table - FIXED to match users table data type
        Schema::create('chat_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessao_id')->constrained('chat_sessoes')->onDelete('cascade');
            $table->string('nr_atendimento', 50)->index();
            $table->enum('turno_id', ['dia', 'noite']);
            
            // FIXED: Use unsignedInteger to match users.id (int unsigned)
            $table->unsignedInteger('usuario_id')->index();
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->text('mensagem');
            $table->datetime('dt_criacao')->index();
            $table->datetime('dt_edicao')->nullable();
            $table->boolean('is_fixed')->default(false)->index();
            
            // FIXED: Use unsignedInteger to match users.id (int unsigned)
            $table->unsignedInteger('fixed_by')->nullable();
            $table->foreign('fixed_by')->references('id')->on('users')->onDelete('set null');
            
            $table->datetime('fixed_at')->nullable();
            $table->datetime('resolved_at')->nullable();
            $table->datetime('expiracao')->nullable();
            $table->timestamps();
            
            // Performance indexes
            $table->index(['sessao_id', 'dt_criacao'], 'idx_session_messages');
            $table->index(['is_fixed', 'sessao_id'], 'idx_pinned_messages');
        });

        // Chat audit table - FIXED to match users table data type
        Schema::create('chat_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('chat_mensagens')->onDelete('cascade');
            $table->enum('acao', ['enviada', 'editada', 'fixada', 'desfixada', 'removida']);
            
            // FIXED: Use unsignedInteger to match users.id (int unsigned)
            $table->unsignedInteger('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->datetime('dt_acao')->index();
            $table->json('detalhes')->nullable();
            $table->timestamps();
            
            $table->index(['mensagem_id', 'dt_acao'], 'idx_message_audit');
        });
    }

    public function down()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        Schema::dropIfExists('chat_auditoria');
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_sessoes');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};