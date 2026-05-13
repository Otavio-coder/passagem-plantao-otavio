<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('id');
            $table->string('session_token')->nullable()->unique()->after('status');
            $table->unsignedSmallInteger('beds_expected')->default(0)->after('beds_visited');
            $table->decimal('completion_percentage', 5, 2)->nullable()->after('beds_expected');
            $table->boolean('forced_finish')->default(false)->after('completion_percentage');
            $table->timestamp('last_activity_at')->nullable()->after('started_at');
            $table->timestamp('resumed_at')->nullable()->after('last_activity_at');
            $table->timestamp('canceled_at')->nullable()->after('finished_at');
            $table->timestamp('abandoned_at')->nullable()->after('canceled_at');
            $table->string('cancel_reason')->nullable()->after('canceled_at');

            $table->index(['user_id', 'status']);
        });

        // Backfill: sessions with finished_at → completed
        DB::table('shift_handovers')
            ->whereNotNull('finished_at')
            ->update(['status' => 'completed']);

        // Backfill: very old unfinished sessions → abandoned
        DB::table('shift_handovers')
            ->whereNull('finished_at')
            ->where('started_at', '<', now()->subHours(4))
            ->update([
                'status' => 'abandoned',
                'abandoned_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn([
                'status',
                'session_token',
                'beds_expected',
                'completion_percentage',
                'forced_finish',
                'last_activity_at',
                'resumed_at',
                'canceled_at',
                'abandoned_at',
                'cancel_reason',
            ]);
        });
    }
};
