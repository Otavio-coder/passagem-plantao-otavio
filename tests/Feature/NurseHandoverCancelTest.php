<?php

namespace Tests\Feature;

use App\Livewire\NurseHandoverSession;
use App\Livewire\SbarPatientModal;
use App\Models\NurseHandoverBed;
use App\Models\ShiftHandover;
use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
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

    public function test_cancel_session_deletes_active_shift_handover_and_resets_state(): void
    {
        $user = User::factory()->create();

        $handover = ShiftHandover::create([
            'user_id' => $user->id,
            'shift' => 'manha',
            'sector_ids' => [101],
            'sector_name' => 'UTI Adulto',
            'bed_codes' => ['A1'],
            'beds_total' => 1,
            'beds_visited' => 0,
            'started_at' => now(),
        ]);

        $component = Livewire::test(NurseHandoverSession::class)
            ->set('handoverId', $handover->id)
            ->set('sectorId', 101)
            ->set('startedAt', now()->toISOString())
            ->set('bedsTotal', 1)
            ->set('handoverPatients', [['nr_atendimento' => 1]])
            ->call('cancelSession');

        $this->assertDatabaseMissing('shift_handovers', [
            'id' => $handover->id,
        ]);

        $component->assertSet('handoverId', null);
        $component->assertSet('startedAt', '');
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
                'shift' => 'M',
                'sector_ids' => [101],
                'sector_name' => 'UTI Adulto',
                'bed_codes' => ['A1'],
                'beds_total' => 1,
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
                'shift' => 'M',
                'sector_ids' => [101],
                'sector_name' => 'UTI Adulto',
                'bed_codes' => ['A1'],
                'beds_total' => 1,
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
