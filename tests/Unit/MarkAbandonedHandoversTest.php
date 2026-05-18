<?php

namespace Tests\Unit;

use App\Models\ShiftHandover;
use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAbandonedHandoversTest extends TestCase
{
    use DatabaseTransactions;

    private function createActiveSession(array $overrides = []): ShiftHandover
    {
        $user = User::factory()->create();

        return ShiftHandover::create(array_merge([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => Str::uuid()->toString(),
            'shift' => 'morning',
            'sector_ids' => [1],
            'sector_name' => 'UTI',
            'bed_codes' => ['A1'],
            'beds_total' => 1,
            'beds_expected' => 1,
            'beds_visited' => 0,
            'started_at' => now(),
            'last_activity_at' => now(),
        ], $overrides));
    }

    public function test_active_session_older_than_timeout_is_marked_abandoned(): void
    {
        $stale = $this->createActiveSession([
            'started_at' => now()->subMinutes(25),
            'last_activity_at' => now()->subMinutes(25),
        ]);

        $this->artisan('handover:mark-abandoned')->assertExitCode(0);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $stale->id,
            'status' => ShiftHandover::STATUS_ABANDONED,
        ]);

        $this->assertNotNull($stale->fresh()->abandoned_at);
    }

    public function test_active_session_with_null_activity_and_old_start_is_marked_abandoned(): void
    {
        $stale = $this->createActiveSession([
            'started_at' => now()->subMinutes(30),
            'last_activity_at' => null,
        ]);

        $this->artisan('handover:mark-abandoned')->assertExitCode(0);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $stale->id,
            'status' => ShiftHandover::STATUS_ABANDONED,
        ]);
    }

    public function test_recent_active_session_is_not_affected(): void
    {
        $recent = $this->createActiveSession([
            'started_at' => now()->subMinutes(5),
            'last_activity_at' => now()->subMinutes(2),
        ]);

        $this->artisan('handover:mark-abandoned')->assertExitCode(0);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $recent->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
        ]);
    }

    public function test_completed_session_is_not_affected(): void
    {
        $user = User::factory()->create();

        $completed = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_COMPLETED,
            'session_token' => Str::uuid()->toString(),
            'shift' => 'morning',
            'sector_ids' => [1],
            'sector_name' => 'UTI',
            'bed_codes' => ['A1'],
            'beds_total' => 1,
            'beds_expected' => 1,
            'beds_visited' => 1,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(15),
            'duration_seconds' => 900,
            'last_activity_at' => now()->subMinutes(15),
        ]);

        $this->artisan('handover:mark-abandoned')->assertExitCode(0);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $completed->id,
            'status' => ShiftHandover::STATUS_COMPLETED,
        ]);
    }

    public function test_custom_timeout_option_is_respected(): void
    {
        // Session inactive for 10 minutes — below default (20) but above custom (5)
        $session = $this->createActiveSession([
            'started_at' => now()->subMinutes(12),
            'last_activity_at' => now()->subMinutes(10),
        ]);

        // Default (20 min) should NOT abandon it
        $this->artisan('handover:mark-abandoned')->assertExitCode(0);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $session->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
        ]);

        // Custom timeout of 5 min SHOULD abandon it
        $this->artisan('handover:mark-abandoned', ['--timeout' => 5])->assertExitCode(0);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $session->id,
            'status' => ShiftHandover::STATUS_ABANDONED,
        ]);
    }

    public function test_dry_run_does_not_update_records(): void
    {
        $stale = $this->createActiveSession([
            'started_at' => now()->subMinutes(30),
            'last_activity_at' => now()->subMinutes(25),
        ]);

        $this->artisan('handover:mark-abandoned', ['--dry-run' => true])->assertExitCode(0);

        // Still active — dry run didn't change anything
        $this->assertDatabaseHas('shift_handovers', [
            'id' => $stale->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
        ]);
    }
}
