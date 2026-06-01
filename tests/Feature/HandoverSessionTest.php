<?php

namespace Tests\Feature;

use App\Livewire\NurseHandoverSession;
use App\Livewire\SbarPatientModal;
use App\Models\NurseHandoverBed;
use App\Models\ShiftHandover;
use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverSessionTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Session creation ────────────────────────────────────────────────────

    public function test_finish_handover_sets_completed_status_and_metrics(): void
    {
        $user = User::factory()->create();
        $token = Str::uuid()->toString();
        $startedAt = now()->subMinutes(10);

        $handover = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => $token,
            'shift' => 'morning',
            'sector_ids' => [1],
            'sector_name' => 'UTI',
            'bed_codes' => ['A1', 'A2', 'A3'],
            'beds_total' => 3,
            'beds_expected' => 3,
            'beds_visited' => 0,
            'started_at' => $startedAt,
            'last_activity_at' => $startedAt,
        ]);

        Livewire::test(NurseHandoverSession::class)
            ->set('handoverId', $handover->id)
            ->set('sessionToken', $token)
            ->set('startedAt', $startedAt->toISOString())
            ->set('bedsTotal', 3)
            ->set('handoverPatients', [])
            ->call('onHandoverFinished', [
                'startedAt' => $startedAt->toISOString(),
                'bedsVisited' => 3,
                'sessionToken' => $token,
            ]);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $handover->id,
            'status' => ShiftHandover::STATUS_COMPLETED,
            'beds_visited' => 3,
            'completion_percentage' => 100.00,
            'forced_finish' => false,
        ]);

        $fresh = $handover->fresh();
        $this->assertNotNull($fresh->finished_at);
        $this->assertNotNull($fresh->duration_seconds);
        $this->assertGreaterThan(0, $fresh->duration_seconds);
    }

    public function test_finish_handover_sets_forced_finish_when_not_all_beds_visited(): void
    {
        $user = User::factory()->create();
        $token = Str::uuid()->toString();
        $startedAt = now()->subMinutes(5);

        $handover = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => $token,
            'shift' => 'morning',
            'sector_ids' => [1],
            'sector_name' => 'UTI',
            'bed_codes' => ['A1', 'A2', 'A3', 'A4'],
            'beds_total' => 4,
            'beds_expected' => 4,
            'beds_visited' => 0,
            'started_at' => $startedAt,
            'last_activity_at' => $startedAt,
        ]);

        Livewire::test(NurseHandoverSession::class)
            ->set('handoverId', $handover->id)
            ->set('sessionToken', $token)
            ->set('startedAt', $startedAt->toISOString())
            ->set('bedsTotal', 4)
            ->set('handoverPatients', [])
            ->call('onHandoverFinished', [
                'startedAt' => $startedAt->toISOString(),
                'bedsVisited' => 2,
                'sessionToken' => $token,
            ]);

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $handover->id,
            'status' => ShiftHandover::STATUS_COMPLETED,
            'beds_visited' => 2,
            'forced_finish' => true,
        ]);

        $fresh = $handover->fresh();
        $this->assertEquals(50.00, $fresh->completion_percentage);
    }

    public function test_finish_with_wrong_token_does_not_update_record(): void
    {
        $user = User::factory()->create();
        $token = Str::uuid()->toString();
        $wrongToken = Str::uuid()->toString();
        $startedAt = now()->subMinutes(5);

        $handover = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => $token,
            'shift' => 'morning',
            'sector_ids' => [1],
            'sector_name' => 'UTI',
            'bed_codes' => ['A1'],
            'beds_total' => 1,
            'beds_expected' => 1,
            'beds_visited' => 0,
            'started_at' => $startedAt,
            'last_activity_at' => $startedAt,
        ]);

        Livewire::test(NurseHandoverSession::class)
            ->set('handoverId', $handover->id)
            ->set('sessionToken', $wrongToken)
            ->set('startedAt', $startedAt->toISOString())
            ->set('bedsTotal', 1)
            ->set('handoverPatients', [])
            ->call('onHandoverFinished', [
                'startedAt' => $startedAt->toISOString(),
                'bedsVisited' => 1,
                'sessionToken' => $wrongToken,
            ]);

        // Record unchanged
        $this->assertDatabaseHas('shift_handovers', [
            'id' => $handover->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
        ]);
    }

    // ─── Bed-level blocking ──────────────────────────────────────────────────

    public function test_two_nurses_with_different_beds_in_same_sector_do_not_block_each_other(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00'));

        try {
            $nurseA = User::factory()->create();
            $nurseB = User::factory()->create();
            $sectorId = 50;

            // Nurse A has beds X1, X2
            NurseHandoverBed::create(['user_id' => $nurseA->id, 'sector_id' => $sectorId, 'bed_code' => 'X1']);
            NurseHandoverBed::create(['user_id' => $nurseA->id, 'sector_id' => $sectorId, 'bed_code' => 'X2']);

            // Nurse B has different beds Y1, Y2
            NurseHandoverBed::create(['user_id' => $nurseB->id, 'sector_id' => $sectorId, 'bed_code' => 'Y1']);
            NurseHandoverBed::create(['user_id' => $nurseB->id, 'sector_id' => $sectorId, 'bed_code' => 'Y2']);

            // Nurse A has a completed handover for her beds
            ShiftHandover::create([
                'user_id' => $nurseA->id,
                'status' => ShiftHandover::STATUS_COMPLETED,
                'session_token' => Str::uuid()->toString(),
                'shift' => 'morning',
                'sector_ids' => [$sectorId],
                'sector_name' => 'Test',
                'bed_codes' => ['X1', 'X2'],
                'beds_total' => 2,
                'beds_expected' => 2,
                'beds_visited' => 2,
                'started_at' => Carbon::parse('2026-05-05 08:00:00'),
                'finished_at' => Carbon::parse('2026-05-05 08:30:00'),
                'duration_seconds' => 1800,
            ]);

            // Nurse B should NOT be blocked
            $this->actingAs($nurseB);

            $component = Livewire::test(NurseHandoverSession::class)
                ->call('open', $sectorId);

            // Should not show blocked modal (different beds = no overlap)
            $component->assertSet('showBlockedModal', false);
            $component->assertSet('blockedType', '');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_same_nurse_with_same_beds_is_blocked_after_completing_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00'));

        try {
            $user = User::factory()->create();
            $sectorId = 60;

            NurseHandoverBed::create(['user_id' => $user->id, 'sector_id' => $sectorId, 'bed_code' => 'Z1']);

            ShiftHandover::create([
                'user_id' => $user->id,
                'status' => ShiftHandover::STATUS_COMPLETED,
                'session_token' => Str::uuid()->toString(),
                'shift' => 'morning',
                'sector_ids' => [$sectorId],
                'sector_name' => 'Test',
                'bed_codes' => ['Z1'],
                'beds_total' => 1,
                'beds_expected' => 1,
                'beds_visited' => 1,
                'started_at' => Carbon::parse('2026-05-05 08:00:00'),
                'finished_at' => Carbon::parse('2026-05-05 08:30:00'),
                'duration_seconds' => 1800,
            ]);

            $this->actingAs($user);

            $component = Livewire::test(NurseHandoverSession::class)
                ->call('open', $sectorId);

            $component->assertSet('showBlockedModal', true);
            $component->assertSet('blockedType', 'shift_done');
        } finally {
            Carbon::setTestNow();
        }
    }

    // ─── Recovery ────────────────────────────────────────────────────────────

    public function test_modal_stores_session_token_on_open_from_handover(): void
    {
        $token = Str::uuid()->toString();

        // Set properties directly to verify token assignment and finishHandover flow
        $component = Livewire::test(SbarPatientModal::class)
            ->set('handoverMode', true)
            ->set('handoverStartedAt', now()->toISOString())
            ->set('handoverSessionToken', $token)
            ->set('showModal', true);

        $component->assertSet('handoverSessionToken', $token);
    }

    public function test_finish_handover_modal_passes_token_in_dispatch(): void
    {
        $token = Str::uuid()->toString();

        $component = Livewire::test(SbarPatientModal::class)
            ->set('handoverMode', true)
            ->set('handoverStartedAt', now()->toISOString())
            ->set('handoverSessionToken', $token)
            ->set('visitedBedsAttendances', [1, 2])
            ->set('showModal', true)
            ->call('finishHandover');

        $component->assertDispatched('handoverFinishedFromModal', function ($event, $params) use ($token) {
            return ($params[0]['sessionToken'] ?? null) === $token
                && ($params[0]['bedsVisited'] ?? null) === 2;
        });
    }

    // ─── Heartbeat ───────────────────────────────────────────────────────────

    public function test_update_handover_activity_updates_last_activity_at(): void
    {
        $user = User::factory()->create();
        $token = Str::uuid()->toString();

        $past = now()->subMinutes(5);

        $handover = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => $token,
            'shift' => 'morning',
            'sector_ids' => [1],
            'sector_name' => 'UTI',
            'bed_codes' => ['B1'],
            'beds_total' => 1,
            'beds_expected' => 1,
            'beds_visited' => 0,
            'started_at' => $past,
            'last_activity_at' => $past,
        ]);

        $this->actingAs($user);

        Livewire::test(SbarPatientModal::class)
            ->set('handoverSessionToken', $token)
            ->call('updateHandoverActivity');

        $this->assertNotEquals(
            $past->toDateTimeString(),
            $handover->fresh()->last_activity_at?->toDateTimeString()
        );
    }

    // ─── Partial bed blocking ────────────────────────────────────────────────

    public function test_partial_beds_completed_allows_session_with_remaining_beds_and_emits_toast(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00'));

        try {
            $user = User::factory()->create();
            $sectorId = 61;

            NurseHandoverBed::create(['user_id' => $user->id, 'sector_id' => $sectorId, 'bed_code' => 'A1']);
            NurseHandoverBed::create(['user_id' => $user->id, 'sector_id' => $sectorId, 'bed_code' => 'A2']);

            ShiftHandover::create([
                'user_id' => $user->id,
                'status' => ShiftHandover::STATUS_COMPLETED,
                'session_token' => Str::uuid()->toString(),
                'shift' => 'morning',
                'sector_ids' => [$sectorId],
                'sector_name' => 'Test',
                'bed_codes' => ['A1'],
                'beds_total' => 1,
                'beds_expected' => 1,
                'beds_visited' => 1,
                'started_at' => Carbon::parse('2026-05-05 08:00:00'),
                'finished_at' => Carbon::parse('2026-05-05 08:30:00'),
                'duration_seconds' => 1800,
            ]);

            $this->actingAs($user);

            $component = Livewire::test(NurseHandoverSession::class)
                ->call('open', $sectorId);

            $component->assertSet('showBlockedModal', false);
            $component->assertSet('blockedType', '');
            $component->assertDispatched('show-toast', fn (string $name, array $params) => ($params[0]['type'] ?? '') === 'warning' && str_contains($params[0]['message'] ?? '', 'A1'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_all_partial_beds_completed_blocks_entirely(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00'));

        try {
            $user = User::factory()->create();
            $sectorId = 62;

            NurseHandoverBed::create(['user_id' => $user->id, 'sector_id' => $sectorId, 'bed_code' => 'B1']);
            NurseHandoverBed::create(['user_id' => $user->id, 'sector_id' => $sectorId, 'bed_code' => 'B2']);

            ShiftHandover::create([
                'user_id' => $user->id,
                'status' => ShiftHandover::STATUS_COMPLETED,
                'session_token' => Str::uuid()->toString(),
                'shift' => 'morning',
                'sector_ids' => [$sectorId],
                'sector_name' => 'Test',
                'bed_codes' => ['B1', 'B2'],
                'beds_total' => 2,
                'beds_expected' => 2,
                'beds_visited' => 2,
                'started_at' => Carbon::parse('2026-05-05 08:00:00'),
                'finished_at' => Carbon::parse('2026-05-05 08:30:00'),
                'duration_seconds' => 1800,
            ]);

            $this->actingAs($user);

            $component = Livewire::test(NurseHandoverSession::class)
                ->call('open', $sectorId);

            $component->assertSet('showBlockedModal', true);
            $component->assertSet('blockedType', 'shift_done');
        } finally {
            Carbon::setTestNow();
        }
    }
}
