<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedInteger('sector_id')->nullable()->after('cd_pessoa_fisica');
            $table->string('sector_name', 120)->nullable()->after('sector_id');
            $table->index('sector_id');
        });

        Schema::table('chat_messages_archive', function (Blueprint $table) {
            $table->unsignedInteger('sector_id')->nullable()->after('source');
            $table->string('sector_name', 120)->nullable()->after('sector_id');
            $table->index('sector_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['sector_id']);
            $table->dropColumn(['sector_id', 'sector_name']);
        });

        Schema::table('chat_messages_archive', function (Blueprint $table) {
            $table->dropIndex(['sector_id']);
            $table->dropColumn(['sector_id', 'sector_name']);
        });
    }
};
