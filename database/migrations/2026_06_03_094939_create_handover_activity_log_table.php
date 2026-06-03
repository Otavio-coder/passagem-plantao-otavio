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
        Schema::create('handover_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('nr_atendimento');
            $table->unsignedInteger('sector_id');
            $table->string('sector_name')->nullable();
            $table->enum('event', ['modal_open', 'chat_post']);
            $table->boolean('only_assigned_beds')->default(false);
            $table->char('shift', 1);
            $table->date('shift_date');
            $table->timestamp('occurred_at');

            $table->index(['user_id', 'sector_id', 'shift', 'shift_date'], 'idx_session_group');
            $table->index('occurred_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_activity_log');
    }
};
