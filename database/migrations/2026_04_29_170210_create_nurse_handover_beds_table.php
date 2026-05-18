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
        Schema::create('nurse_handover_beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sector_id');
            $table->string('bed_code'); // nr_unidade_basica
            $table->timestamps();

            $table->unique(['user_id', 'sector_id', 'bed_code']);
            $table->index(['user_id', 'sector_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_handover_beds');
    }
};
