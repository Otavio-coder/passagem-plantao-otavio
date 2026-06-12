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
        Schema::create('chat_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedInteger('sector_id')->nullable();
            $table->string('sector_name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('shift', 1); // M, T, N
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('total_chars')->default(0);
            // {"curtas":5,"adequadas":10,"longas":2}
            $table->json('char_buckets')->nullable();
            // {"pendência":3,"risco":1,"conduta/procedimento":2,"alta/evolução":1,"condição clínica":4}
            $table->json('content_tags')->nullable();
            // {"7":3,"8":2,...} — apenas horas com count > 0
            $table->json('hour_counts')->nullable();
            $table->timestamps();

            $table->index(['date', 'sector_id']);
            $table->index(['date', 'user_id']);
            $table->index('date');
            $table->index('sector_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_analytics_daily');
    }
};
