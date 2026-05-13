<?php

namespace App\Livewire;

use App\Models\NurseHandoverBed;
use App\Models\ShiftHandover;
use App\Services\PatientData\PatientDataLoader;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NurseHandoverSession extends Component
{
    public ?int $sectorId = null;

    public ?int $handoverId = null;

    public int $bedsTotal = 0;

    public string $startedAt = '';

    /** Filtered patient list for the handover (only nurse's beds) */
    public array $handoverPatients = [];

    /** Controls the blocked modal */
    public bool $showBlockedModal = false;

    /** 'no_beds' | 'shift_done' | 'active_session' */
    public string $blockedType = '';

    public string $blockedReason = '';

    #[On('openNurseHandoverSession')]
    public function open(int $sectorId): void
    {
        $this->sectorId = $sectorId;

        $user = Auth::user();
        [$shiftStart, $shiftEnd] = ShiftService::getShiftWindow();

        $hasConfiguredBeds = NurseHandoverBed::where('user_id', $user->id)
            ->where('sector_id', $sectorId)
            ->exists();

        if (! $hasConfiguredBeds) {
            $this->blockedType = 'no_beds';
            $this->blockedReason = 'Você ainda não configurou seus leitos para este setor. Acesse as configurações de leitos antes de iniciar a passagem.';
            $this->showBlockedModal = true;

            return;
        }

        // Check for an active (unfinished) handover session for this shift
        $currentShift = ShiftService::getCurrentShift();
        $activeSession = ShiftHandover::where('user_id', $user->id)
            ->where('shift', $currentShift)
            ->whereJsonContains('sector_ids', $sectorId)
            ->whereBetween('started_at', [$shiftStart, $shiftEnd])
            ->whereNull('finished_at')
            ->first();

        if ($activeSession) {
            $this->blockedType = 'active_session';
            $this->blockedReason = 'Já existe uma passagem em andamento para este turno. Conclua a sessão atual antes de iniciar uma nova.';
            $this->showBlockedModal = true;

            return;
        }

        // Check for a completed handover in the current shift window
        $alreadyDone = ShiftHandover::where('user_id', $user->id)
            ->where('shift', $currentShift)
            ->whereJsonContains('sector_ids', $sectorId)
            ->whereBetween('started_at', [$shiftStart, $shiftEnd])
            ->whereNotNull('finished_at')
            ->exists();

        if ($alreadyDone) {
            $this->blockedType = 'shift_done';
            $this->blockedReason = 'A passagem deste turno já foi concluída. O botão ficará liberado novamente no próximo turno.';
            $this->showBlockedModal = true;

            return;
        }

        $this->startSession();

        $firstPatient = collect($this->handoverPatients)
            ->first(fn (array $p) => ($p['has_patient'] ?? false) && ! empty($p['nr_atendimento']));

        if (! $firstPatient) {
            if ($this->handoverId) {
                ShiftHandover::where('id', $this->handoverId)->delete();
                $this->handoverId = null;
            }

            $this->dispatch('show-toast', [
                'message' => 'Nenhum dos leitos configurados possui paciente internado no momento.',
                'type' => 'info',
            ]);

            return;
        }

        $this->dispatch('openPatientModalHandover', [
            'attendanceNumber' => $firstPatient['nr_atendimento'],
            'patients' => $this->handoverPatients,
            'handoverMode' => true,
            'startedAt' => $this->startedAt,
        ]);
    }

    #[On('cancelNurseHandoverSession')]
    public function cancelSession(): void
    {
        if ($this->handoverId) {
            ShiftHandover::where('id', $this->handoverId)->delete();
        }

        $this->handoverId = null;
        $this->startedAt = '';
        $this->handoverPatients = [];
        $this->bedsTotal = 0;
        $this->sectorId = null;
        $this->showBlockedModal = false;
        $this->blockedType = '';
        $this->blockedReason = '';

        $this->dispatch('nurse-handover-cancelled');
    }

    public function closeBlockedModal(): void
    {
        $this->showBlockedModal = false;
        $this->blockedType = '';
        $this->blockedReason = '';
    }

    #[On('handoverFinishedFromModal')]
    public function onHandoverFinished(array $data): void
    {
        if (! $this->handoverId) {
            return;
        }

        $startedAt = $data['startedAt'] ?? $this->startedAt;
        $bedsVisited = $data['bedsVisited'] ?? 0;

        $started = Carbon::parse($startedAt);
        $duration = (int) abs($started->diffInSeconds(now()));

        ShiftHandover::where('id', $this->handoverId)->update([
            'beds_visited' => $bedsVisited,
            'finished_at' => now(),
            'duration_seconds' => $duration,
        ]);

        $this->handoverId = null;
        $this->startedAt = '';
        $this->handoverPatients = [];

        // Notify the report view that the handover for this shift is now done.
        $this->dispatch('handover-updated');
        $this->dispatch('nurseHandoverCompleted', sectorId: $this->sectorId);
    }

    private function startSession(): void
    {
        $user = Auth::user();

        $nurseBeds = NurseHandoverBed::where('user_id', $user->id)
            ->where('sector_id', $this->sectorId)
            ->pluck('bed_code')
            ->all();

        $allPatients = PatientDataLoader::forSector($this->sectorId)
            ->include('demographics', 'scales', 'clinical', 'pending_events')
            ->get();

        $this->handoverPatients = array_values(array_filter(
            $allPatients,
            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $nurseBeds, true)
        ));

        $this->bedsTotal = count($this->handoverPatients);
        $this->startedAt = now()->toISOString();

        $shift = ShiftService::getCurrentShift();

        $sectorName = collect($this->handoverPatients)
            ->first()['ds_setor_atendimento']
            ?? collect($this->handoverPatients)->first()['ds_prescricao']
            ?? null;

        $record = ShiftHandover::create([
            'user_id' => $user->id,
            'shift' => $shift,
            'sector_ids' => [$this->sectorId],
            'sector_name' => $sectorName,
            'bed_codes' => $nurseBeds,
            'beds_total' => $this->bedsTotal,
            'beds_visited' => 0,
            'started_at' => now(),
        ]);

        $this->handoverId = $record->id;
    }

    public function render(): View
    {
        return view('livewire.nurse-handover-session');
    }
}
