<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes chat_messages_archive to store multiple sectors as a JSON array,
 * since a patient can pass through several wards during a long admission.
 *
 * sector_name: VARCHAR(120) → TEXT (stores JSON, e.g. ["HSR 2º", "HDVS-CIEM"])
 * sector_id:   dropped — a single int has no meaning when multiple sectors exist
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages_archive', function (Blueprint $table) {
            $table->dropIndex(['sector_id']);
            $table->dropColumn('sector_id');
            $table->text('sector_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages_archive', function (Blueprint $table) {
            $table->unsignedInteger('sector_id')->nullable()->after('source');
            $table->index('sector_id');
            // Keep TEXT to avoid truncating JSON data already stored; VARCHAR(120) would silently truncate.
            $table->text('sector_name')->nullable()->change();
        });
    }
};
