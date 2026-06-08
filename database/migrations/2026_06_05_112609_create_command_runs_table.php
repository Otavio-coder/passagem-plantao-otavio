<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('command_id', 60)->index(); // slug identificador
            $table->string('label');
            $table->json('parameters')->nullable();
            $table->enum('status', ['pending', 'running', 'done', 'failed'])->default('pending')->index();
            $table->text('output')->nullable();
            $table->unsignedTinyInteger('exit_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
