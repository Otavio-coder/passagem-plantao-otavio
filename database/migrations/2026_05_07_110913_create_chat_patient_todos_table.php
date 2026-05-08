<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_patient_todos', function (Blueprint $table) {
            $table->id();
            $table->integer('nr_atendimento')->index();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('done_by_user_id')->nullable()->constrained('users');
            $table->string('content');
            $table->boolean('is_done')->default(false);
            $table->boolean('has_alert')->default(false);
            $table->string('shift')->nullable()->comment('manha, tarde, noite or null for any shift');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_patient_todos');
    }
};
