<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateChatMensagensTable extends Migration
{
    public function up()
    {
        // Limpa tabelas antigas antes de criar
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_auditoria');
        Schema::dropIfExists('chat_sessoes');

        // Tabela de sessões do chat
        Schema::create('chat_sessoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('nr_atendimento')->index();
            $table->string('turno_id', 10)->index(); // 'dia' ou 'noite'
            $table->date('data_sessao')->index();
            $table->timestamp('inicio')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('fim')->nullable();
            $table->boolean('encerrada')->default(false);
            $table->timestamps();
            $table->unique(['nr_atendimento', 'turno_id', 'data_sessao'], 'sessao_unica');
        });

        // Tabela de mensagens
        Schema::create('chat_mensagens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sessao_id')->index(); // Relaciona sessão
            $table->bigInteger('nr_atendimento')->index();
            $table->string('turno_id', 10)->index();
            $table->unsignedInteger('usuario_id')->index();
            $table->text('mensagem');
            $table->timestamp('dt_criacao')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('dt_edicao')->nullable();
            $table->boolean('is_fixed')->default(false)->index();
            $table->unsignedInteger('fixed_by')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('expiracao')->nullable();
            $table->softDeletes();
            $table->foreign('sessao_id')->references('id')->on('chat_sessoes')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('fixed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['sessao_id', 'is_fixed']);
            $table->index(['nr_atendimento', 'turno_id', 'dt_criacao'], 'idx_atendimento_turno_data');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_mensagens');
        Schema::dropIfExists('chat_sessoes');
    }
}