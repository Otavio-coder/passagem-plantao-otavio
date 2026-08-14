<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('huddle_safety_assessments', function (Blueprint $table) {
            // Marca explícita de registro finalizado. Default true para preservar comportamento existente.
            $table->boolean('finalized')->default(true)->after('huddle_date');
        });
    }

    public function down(): void
    {
        Schema::table('huddle_safety_assessments', function (Blueprint $table) {
            $table->dropColumn('finalized');
        });
    }
};