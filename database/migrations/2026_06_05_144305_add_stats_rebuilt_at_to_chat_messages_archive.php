<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages_archive', function (Blueprint $table) {
            // NULL = nunca processado
            // Preenchido: payload já foi extraído — reprocessa só se last_message_at > stats_rebuilt_at
            $table->timestamp('stats_rebuilt_at')->nullable()->after('archived_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages_archive', function (Blueprint $table) {
            $table->dropColumn('stats_rebuilt_at');
        });
    }
};
