<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove duplicatas e adiciona índice único em message_id
     */
    public function up(): void
    {
        // Remove duplicatas mantendo apenas o pin mais recente (maior id) para cada message_id
        // que ainda está ativo (unpinned_at IS NULL)
        $duplicates = DB::table('chat_message_pins')
            ->select('message_id', DB::raw('MAX(id) as keep_id'))
            ->whereNull('unpinned_at')
            ->groupBy('message_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            // Deleta todos os pins duplicados exceto o mais recente (keep_id)
            DB::table('chat_message_pins')
                ->where('message_id', $dup->message_id)
                ->where('id', '!=', $dup->keep_id)
                ->whereNull('unpinned_at')
                ->delete();
        }

        // Também verifica duplicatas gerais (incluindo desfixados) e mantém apenas o mais recente
        $allDuplicates = DB::table('chat_message_pins')
            ->select('message_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('message_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($allDuplicates as $dup) {
            DB::table('chat_message_pins')
                ->where('message_id', $dup->message_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        // Adiciona índice único para prevenir futuras duplicatas
        Schema::table('chat_message_pins', function (Blueprint $table) {
            $table->unique('message_id', 'uniq_message_pin');
        });
    }

    /**
     * Remove o índice único
     */
    public function down(): void
    {
        Schema::table('chat_message_pins', function (Blueprint $table) {
            $table->dropUnique('uniq_message_pin');
        });
    }
};
