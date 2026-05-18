<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->string('sector_name')->nullable()->after('sector_ids');
            $table->json('bed_codes')->nullable()->after('sector_name');
        });
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn(['sector_name', 'bed_codes']);
        });
    }
};
