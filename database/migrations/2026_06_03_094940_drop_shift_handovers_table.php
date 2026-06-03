<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('shift_handovers');
    }

    public function down(): void
    {
        // shift_handovers was replaced by handover_activity_log
    }
};
