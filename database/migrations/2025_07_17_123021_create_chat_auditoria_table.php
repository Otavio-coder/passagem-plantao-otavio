<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateChatAuditoriaTable extends Migration
{
    public function up()
    {
        Schema::create('chat_auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mensagem_id')->index();
            $table->string('acao', 30); // 'enviada', 'editada', 'fixada', 'desfixada', 'removida', 'visualizada'
            $table->unsignedInteger('usuario_id')->index();
            $table->timestamp('dt_acao')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->json('detalhes')->nullable(); // Dados extras da ação
            $table->foreign('mensagem_id')->references('id')->on('chat_mensagens')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['mensagem_id', 'acao'], 'idx_mensagem_acao');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_auditoria');
    }
}