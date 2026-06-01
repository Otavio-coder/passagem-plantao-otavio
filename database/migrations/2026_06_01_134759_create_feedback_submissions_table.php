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
        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('sector')->nullable();
            $table->enum('shift', ['manha', 'tarde', 'noite']);
            $table->enum('rating', ['excelente', 'bom', 'regular', 'ruim']);
            $table->text('difficulty');
            $table->text('suggestion')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name_cached')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_submissions');
    }
};
