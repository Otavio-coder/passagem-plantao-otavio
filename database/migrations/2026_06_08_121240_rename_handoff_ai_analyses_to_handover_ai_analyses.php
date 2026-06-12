<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('handoff_ai_analyses', 'handover_ai_analyses');
    }

    public function down(): void
    {
        Schema::rename('handover_ai_analyses', 'handoff_ai_analyses');
    }
};
