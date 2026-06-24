<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('handover_ai_analyses');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('logs');
    }

    public function down(): void
    {
        // Intentionally not restored — tables were confirmed dead before drop.
    }
};
