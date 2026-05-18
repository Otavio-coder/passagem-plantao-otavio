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
        Schema::create('shift_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shift'); // manha | tarde | noite
            $table->json('sector_ids');
            $table->unsignedSmallInteger('beds_total')->default(0);
            $table->unsignedSmallInteger('beds_visited')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
