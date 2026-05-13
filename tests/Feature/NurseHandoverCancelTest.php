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

class NurseHandoverCancelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cancel_handover_closes_patient_modal_and_dispatches_cancel_event(): void
    {
        $component = Livewire::test(SbarPatientModal::class)
            ->set('handoverMode', true)
            ->set('handoverStartedAt', now()->toISOString())
            ->set('showModal', true)
            ->call('cancelHandover');

        $component->assertSet('handoverMode', false);
        $component->assertSet('handoverStartedAt', '');
        $component->assertSet('showModal', false);
        $component->assertSet('modalPatients', []);
        $component->assertDispatched('cancelNurseHandoverSession');
        $component->assertDispatched('modal-closed');
    }

    public function test_cancel_session_preserves_record_with_canceled_status(): void
    {
        $user = User::factory()->create();
        $token = Str::uuid()->toString();

        $handover = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => $token,
            'shift' => 'morning',
            'sector_ids' => [101],
            'sector_name' => 'UTI Adulto',
            'bed_codes' => ['A1'],
            'beds_total' => 1,
            'beds_expected' => 1,
            'beds_visited' => 0,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $component = Livewire::test(NurseHandoverSession::class)
            ->set('handoverId', $handover->id)
            ->set('sessionToken', $token)
            ->set('sectorId', 101)
            ->set('startedAt', now()->toISOString())
            ->set('bedsTotal', 1)
            ->set('handoverPatients', [['nr_atendimento' => 1]])
            ->call('cancelSession');

        // Record is preserved, not deleted
        $this->assertDatabaseHas('shift_handovers', [
            'id' => $handover->id,
            'status' => ShiftHandover::STATUS_CANCELED,
            'cancel_reason' => 'user_canceled',
        ]);

        $this->assertNotNull($handover->fresh()->canceled_at);

        $component->assertSet('handoverId', null);
        $component->assertSet('startedAt', '');
        $component->assertSet('sessionToken', '');
        $component->assertSet('handoverPatients', []);
        $component->assertSet('bedsTotal', 0);
        $component->assertSet('sectorId', null);
        $component->assertDispatched('nurse-handover-cancelled');
    }

    public function test_open_does_not_block_when_only_previous_shift_handover_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00'));

        try {
            $user = User::factory()->create();

            NurseHandoverBed::create([
                'user_id' => $user->id,
                'sector_id' => 101,
                'bed_code' => 'A1',
            ]);

            ShiftHandover::create([
                'user_id' => $user->id,
                'status' => ShiftHandover::STATUS_COMPLETED,
                'session_token' => Str::uuid()->toString(),
                'shift' => 'morning',
                'sector_ids' => [101],
                'sector_name' => 'UTI Adulto',
                'bed_codes' => ['A1'],
                'beds_total' => 1,
                'beds_expected' => 1,
                'beds_visited' => 1,
                'started_at' => Carbon::parse('2026-05-04 09:15:00'),
                'finished_at' => Carbon::parse('2026-05-04 09:45:00'),
                'duration_seconds' => 1800,
            ]);

            $this->actingAs($user);

            $component = Livewire::test(NurseHandoverSession::class)
                ->call('open', 101);

            $component->assertSet('showBlockedModal', false);
            $component->assertSet('blockedType', '');
            $component->assertSet('blockedReason', '');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_open_blocks_when_shift_handover_exists_in_current_shift_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00'));

        try {
            $user = User::factory()->create();

            NurseHandoverBed::create([
                'user_id' => $user->id,
                'sector_id' => 101,
                'bed_code' => 'A1',
            ]);

            ShiftHandover::create([
                'user_id' => $user->id,
                'status' => ShiftHandover::STATUS_COMPLETED,
                'session_token' => Str::uuid()->toString(),
                'shift' => 'morning',
                'sector_ids' => [101],
                'sector_name' => 'UTI Adulto',
                'bed_codes' => ['A1'],
                'beds_total' => 1,
                'beds_expected' => 1,
                'beds_visited' => 1,
                'started_at' => Carbon::parse('2026-05-05 08:15:00'),
                'finished_at' => Carbon::parse('2026-05-05 08:45:00'),
                'duration_seconds' => 1800,
            ]);

            $this->actingAs($user);

            $component = Livewire::test(NurseHandoverSession::class)
                ->call('open', 101);

            $component->assertSet('showBlockedModal', true);
            $component->assertSet('blockedType', 'shift_done');
        } finally {
            Carbon::setTestNow();
        }
    }
}
