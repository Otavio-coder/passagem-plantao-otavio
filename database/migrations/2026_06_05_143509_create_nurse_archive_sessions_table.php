<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurse_archive_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_name');
            $table->char('shift', 1);           // M | T | N
            $table->date('shift_date')->index();
            $table->string('sector_name', 120)->nullable();
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->unsignedSmallInteger('beds_visited')->default(0); // nr_atendimentos distintos no turno
            $table->timestamp('started_at')->nullable();              // timestamp da primeira msg do turno

            $table->unique(['user_id', 'shift', 'shift_date'], 'nurse_archive_sessions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurse_archive_sessions');
    }
};
