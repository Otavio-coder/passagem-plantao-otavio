<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('archived_message_count')->nullable()->after('writing_analyzed_at')
                ->comment('Contagem de mensagens no chat_messages_archive — populado por cache:rebuild-archive-stats');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('archived_message_count');
        });
    }
};
