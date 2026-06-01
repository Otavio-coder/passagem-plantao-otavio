<?php

namespace App\Livewire;

use App\Models\NurseHandoverBed;
use App\Models\ShiftHandover;
use App\Services\PatientData\PatientDataLoader;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NurseHandoverSession extends Component
{
    public ?int $sectorId = null;

    public ?int $handoverId = null;

    public int $bedsTotal = 0;

    public string $startedAt = '';

    public string $sessionToken = '';

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

        $hasConfiguredBeds = NurseHandoverBed::where('user_id', $user->id)
            ->where('sector_id', $sectorId)
            ->exists();

        if (! $hasConfiguredBeds) {
            $this->blockedType = 'no_beds';
            $this->blockedReason = 'Você ainda não configurou seus leitos para este setor. Acesse as configurações de leitos antes de iniciar a passagem.';
            $this->showBlockedModal = true;

            return;
        }

        $nurseBedCodes = NurseHandoverBed::where('user_id', $user->id)
            ->where('sector_id', $sectorId)
            ->pluck('bed_code')
            ->all();

        // Janela com 30 min de antecedência: permite iniciar a passagem do próximo turno
        // até 30 min antes da virada (ex: 06:30 já conta como turno da manhã).
        [$shiftStart, $shiftEnd] = ShiftService::getShiftWindowWithGrace(30);
        $currentShift = ShiftService::getCurrentShiftWithGrace(30);

        $blocked = false;
        $completedBlockedBeds = [];

        DB::transaction(function () use ($user, $currentShift, $shiftStart, $shiftEnd, $nurseBedCodes, &$blocked, &$completedBlockedBeds): void {
            // Trava as linhas existentes para evitar criação concorrente de sessão
            $existing = ShiftHandover::where('user_id', $user->id)
                ->whereIn('status', [ShiftHandover::STATUS_ACTIVE, ShiftHandover::STATUS_COMPLETED])
                ->whereBetween('started_at', [$shiftStart, $shiftEnd])
                ->lockForUpdate()
                ->get();

            foreach ($existing as $session) {
                $sessionBeds = $session->bed_codes ?? [];
                $overlap = array_intersect($nurseBedCodes, $sessionBeds);

                if (empty($overlap)) {
                    continue;
                }

                if ($session->isActive()) {
                    $this->blockedType = 'active_session';
                    $this->blockedReason = 'Já existe uma passagem em andamento para estes leitos neste turno. Conclua a sessão atual antes de iniciar uma nova.';
                    $this->showBlockedModal = true;
                    $blocked = true;

                    return;
                }

                if ($session->isCompleted()) {
                    $completedBlockedBeds = array_values(array_unique(array_merge($completedBlockedBeds, array_values($overlap))));
                }
            }

            $availableBedCodes = array_values(array_diff($nurseBedCodes, $completedBlockedBeds));

            if (empty($availableBedCodes)) {
                $this->blockedType = 'shift_done';
                $this->blockedReason = 'A passagem deste turno já foi concluída para todos os seus leitos. O botão ficará liberado novamente no próximo turno.';
                $this->showBlockedModal = true;
                $blocked = true;

                return;
            }

            $this->createSessionRecord($user, $currentShift, $availableBedCodes);
        });

        if ($blocked || $this->showBlockedModal) {
            return;
        }

        if (! empty($completedBlockedBeds)) {
            $bedList = implode(', ', $completedBlockedBeds);
            $this->dispatch('show-toast', [
                'message' => "Leito(s) {$bedList} ignorado(s): passagem já concluída neste turno.",
                'type' => 'warning',
            ]);
        }

        try {
            $this->loadSessionPatients($this->sessionToken);
        } catch (\Throwable) {
            $this->handoverPatients = [];
            $this->bedsTotal = 0;
        }

        $firstPatient = collect($this->handoverPatients)
            ->first(fn (array $p) => ($p['has_patient'] ?? false) && ! empty($p['nr_atendimento']));

        if (! $firstPatient) {
            if ($this->handoverId) {
                ShiftHandover::where('id', $this->handoverId)->update([
                    'status' => ShiftHandover::STATUS_CANCELED,
                    'canceled_at' => now(),
                    'cancel_reason' => 'no_patients',
                ]);
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
            'sessionToken' => $this->sessionToken,
        ]);
    }

    #[On('resumeNurseHandoverSession')]
    public function resume(array $data): void
    {
        $handoverId = $data['handoverId'] ?? null;

        if (! $handoverId) {
            return;
        }

        $session = ShiftHandover::where('id', $handoverId)
            ->where('user_id', Auth::id())
            ->where('status', ShiftHandover::STATUS_ACTIVE)
            ->first();

        if (! $session) {
            $this->dispatch('show-toast', [
                'message' => 'Sessão de passagem não encontrada ou já encerrada.',
                'type' => 'warning',
            ]);

            return;
        }

        $this->handoverId = $session->id;
        $this->sessionToken = $session->session_token ?? '';
        $this->startedAt = $session->started_at->toISOString();
        $this->sectorId = $session->sector_ids[0] ?? null;

        $nurseBedCodes = $session->bed_codes ?? [];

        $allPatients = PatientDataLoader::forSector($this->sectorId)
            ->include('demographics', 'scales', 'clinical', 'pending_events')
            ->get();

        $this->handoverPatients = array_values(array_filter(
            $allPatients,
            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $nurseBedCodes, true)
        ));

        $this->bedsTotal = count($this->handoverPatients);

        $session->update(['resumed_at' => now(), 'last_activity_at' => now()]);

        $firstPatient = collect($this->handoverPatients)
            ->first(fn (array $p) => ($p['has_patient'] ?? false) && ! empty($p['nr_atendimento']));

        if (! $firstPatient) {
            $this->dispatch('show-toast', [
                'message' => 'Nenhum paciente encontrado nos leitos da passagem.',
                'type' => 'info',
            ]);

            return;
        }

        $this->dispatch('openPatientModalHandover', [
            'attendanceNumber' => $firstPatient['nr_atendimento'],
            'patients' => $this->handoverPatients,
            'handoverMode' => true,
            'startedAt' => $this->startedAt,
            'sessionToken' => $this->sessionToken,
        ]);
    }

    #[On('cancelNurseHandoverSession')]
    public function cancelSession(): void
    {
        if ($this->handoverId) {
            ShiftHandover::where('id', $this->handoverId)
                ->where('session_token', $this->sessionToken)
                ->where('status', ShiftHandover::STATUS_ACTIVE)
                ->update([
                    'status' => ShiftHandover::STATUS_CANCELED,
                    'canceled_at' => now(),
                    'cancel_reason' => 'user_canceled',
                ]);
        }

        $this->resetSessionState();
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
        $sessionToken = $data['sessionToken'] ?? $this->sessionToken;

        $started = Carbon::parse($startedAt);
        $duration = (int) abs($started->diffInSeconds(now()));

        $completionPercentage = $this->bedsTotal > 0
            ? round($bedsVisited / $this->bedsTotal * 100, 2)
            : null;

        $forcedFinish = $completionPercentage !== null && $completionPercentage < 100;

        $affected = ShiftHandover::where('id', $this->handoverId)
            ->where('session_token', $sessionToken)
            ->where('status', ShiftHandover::STATUS_ACTIVE)
            ->update([
                'status' => ShiftHandover::STATUS_COMPLETED,
                'beds_visited' => $bedsVisited,
                'finished_at' => now(),
                'duration_seconds' => $duration,
                'completion_percentage' => $completionPercentage,
                'forced_finish' => $forcedFinish,
                'last_activity_at' => now(),
            ]);

        if ($affected === 0) {
            $this->dispatch('show-toast', [
                'message' => 'Esta passagem já foi encerrada em outra aba ou sessão.',
                'type' => 'warning',
            ]);
            $this->resetSessionState();

            return;
        }

        $sectorId = $this->sectorId;
        $this->resetSessionState();

        $this->dispatch('handover-updated');
        $this->dispatch('nurseHandoverCompleted', sectorId: $sectorId);
    }

    private function createSessionRecord(object $user, string $currentShift, array $nurseBedCodes): void
    {
        $token = Str::uuid()->toString();
        $this->startedAt = now()->toISOString();

        $record = ShiftHandover::create([
            'user_id' => $user->id,
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => $token,
            'shift' => $currentShift,
            'sector_ids' => [$this->sectorId],
            'sector_name' => null,
            'bed_codes' => $nurseBedCodes,
            'beds_total' => 0,
            'beds_expected' => 0,
            'beds_visited' => 0,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->handoverId = $record->id;
        $this->sessionToken = $token;
    }

    private function loadSessionPatients(string $sessionToken): void
    {
        $session = ShiftHandover::where('id', $this->handoverId)
            ->where('session_token', $sessionToken)
            ->first();

        if (! $session) {
            return;
        }

        $nurseBedCodes = $session->bed_codes ?? [];

        $allPatients = PatientDataLoader::forSector($this->sectorId)
            ->include('demographics', 'scales', 'clinical', 'pending_events')
            ->get();

        $this->handoverPatients = array_values(array_filter(
            $allPatients,
            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $nurseBedCodes, true)
        ));

        $this->bedsTotal = count($this->handoverPatients);

        $sectorName = collect($this->handoverPatients)->first()['ds_setor_atendimento']
            ?? collect($this->handoverPatients)->first()['ds_prescricao']
            ?? null;

        $session->update([
            'sector_name' => $sectorName,
            'beds_total' => $this->bedsTotal,
            'beds_expected' => $this->bedsTotal,
        ]);
    }

    private function resetSessionState(): void
    {
        $this->handoverId = null;
        $this->startedAt = '';
        $this->sessionToken = '';
        $this->handoverPatients = [];
        $this->bedsTotal = 0;
        $this->sectorId = null;
        $this->showBlockedModal = false;
        $this->blockedType = '';
        $this->blockedReason = '';
    }

    public function render(): View
    {
        return view('livewire.nurse-handover-session');
    }
}
