<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limpeza das tabelas de chat:
     * - Remove coluna usuarios_participantes (não utilizada)
     * - Remove colunas não utilizadas da tabela de mensagens
     */
    public function up(): void
    {
        // Remove coluna usuarios_participantes da tabela chat_sessoes
        if (Schema::hasColumn('chat_sessoes', 'usuarios_participantes')) {
            Schema::table('chat_sessoes', function (Blueprint $table) {
                $table->dropColumn('usuarios_participantes');
            });
        }

        // Remove colunas não utilizadas da tabela chat_mensagens
        Schema::table('chat_mensagens', function (Blueprint $table) {
            // Colunas que não estão sendo utilizadas
            if (Schema::hasColumn('chat_mensagens', 'expiracao')) {
                $table->dropColumn('expiracao');
            }
            if (Schema::hasColumn('chat_mensagens', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
        });
    }

    /**
     * Reverter as alterações
     */
    public function down(): void
    {
        // Restaura coluna usuarios_participantes
        if (!Schema::hasColumn('chat_sessoes', 'usuarios_participantes')) {
            Schema::table('chat_sessoes', function (Blueprint $table) {
                $table->json('usuarios_participantes')->nullable()->after('encerrada');
            });
        }

        // Restaura colunas da tabela chat_mensagens
        Schema::table('chat_mensagens', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_mensagens', 'expiracao')) {
                $table->timestamp('expiracao')->nullable()->after('fixed_at');
            }
            if (!Schema::hasColumn('chat_mensagens', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('fixed_at');
            }
        });
    }
};
