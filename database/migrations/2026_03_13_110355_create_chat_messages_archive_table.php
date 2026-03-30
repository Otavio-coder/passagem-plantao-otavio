<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de arquivo histórico de mensagens do chat.
 *
 * Uma linha por atendimento (nr_atendimento) com o conteúdo compactado via
 * zlib + JSON para minimizar espaço em disco. O campo `payload` armazena
 * um array JSON antes da compressão:
 *
 *   [
 *     { "ts": <unix timestamp>, "u": "<username>", "m": "<mensagem>", "t": "<turno>" },
 *     ...
 *   ]
 *
 * Campos de metadados permitem buscas rápidas sem descomprimir o payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages_archive', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Atendimento (único — um registro por nr_atendimento)
            $table->string('nr_atendimento', 50)->unique();

            // Metadados para consulta rápida sem descomprimir
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();

            // Payload JSON comprimido com zlib (até ~16 MB por atendimento)
            $table->mediumText('payload'); // armazena binário codificado em base64

            // Origem do arquivo: 'prod_import' | 'cleanup_archive'
            $table->string('source', 20)->default('prod_import');
            $table->timestamp('archived_at')->useCurrent();

            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages_archive');
    }
};
