<?php

namespace App\Livewire;

use App\Actions\Huddle\AnswerChecklistItemAction;
use App\Actions\Huddle\OpenPatientDayAction;
use App\Actions\Huddle\SetExpectedDischargeAction;
use App\Actions\Huddle\SetHuddleTriageAction;
use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\Huddle\HuddlePatientDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class HuddlePatientModal extends Component
{
    public bool $showModal = false;

    public array $currentPatient = [];

    public string $hospitalName = '';

    public int $sectorId = 0;

    public array $modalPatients = [];

    public ?int $currentPatientIndex = null;

    public bool $canGoPrevious = false;

    public bool $canGoNext = false;

    // ── Estado do Huddle para o paciente atual ──────────────────────────────
    public ?string $triageStatus = null;      // 'huddle' | 'round'

    public ?string $expectedDischarge = null; // DPA registrada no Huddle (Y-m-d)

    public ?string $clinicalCriteria = null;

    /** @var array<string, array{answer: ?string, signal: ?string, responsible: ?string, due_at: ?string, notes: ?string}> */
    public array $checklist = [];

    #[On('openModal')]
    public function openWithPatient(int $attendanceNumber, string $hospital, array $sbarPatient, array $patients = [], int $sectorId = 0): void
    {
        $this->hospitalName = $hospital;
        $this->sectorId = $sectorId;

        $this->modalPatients = collect($patients)
            ->filter(fn ($p) => ! empty($p['has_patient']) && ! empty($p['nr_atendimento']))
            ->values()
            ->map(fn ($p) => array_merge($p, [
                'label' => trim(($p['cd_unidade_basica'] ?? '').' – '.($p['nm_pessoa_fisica'] ?? 'Paciente')),
            ]))
            ->toArray();

        $index = collect($this->modalPatients)
            ->search(fn ($p) => ($p['nr_atendimento'] ?? 0) == $attendanceNumber);

        $this->currentPatientIndex = $index !== false ? $index : 0;
        $this->currentPatient = $this->modalPatients[$this->currentPatientIndex] ?? $sbarPatient;

        $this->updateNavigationState();
        $this->loadHuddleState();

        $this->showModal = true;
    }

    public function goToPreviousPatient(): void
    {
        if (! $this->canGoPrevious || $this->currentPatientIndex === null) {
            return;
        }

        $this->navigateToIndex($this->currentPatientIndex - 1);
    }

    public function goToNextPatient(): void
    {
        if (! $this->canGoNext || $this->currentPatientIndex === null) {
            return;
        }

        $this->navigateToIndex($this->currentPatientIndex + 1);
    }

    public function closeModal(): void
    {
        $this->reset([
            'showModal', 'currentPatient', 'hospitalName', 'sectorId', 'modalPatients',
            'currentPatientIndex', 'canGoPrevious', 'canGoNext',
            'triageStatus', 'expectedDischarge', 'clinicalCriteria', 'checklist',
        ]);
    }

    // ── Ações do Huddle (edição — exige 'conduzir huddle') ──────────────────

    public function setTriage(string $status): void
    {
        $this->authorizeConduct();

        $day = $this->ensureDay();
        app(SetHuddleTriageAction::class)->execute($day, $status, (int) Auth::id());

        $this->loadHuddleState();
    }

    public function answerItem(string $itemCode, string $answer): void
    {
        $this->authorizeConduct();

        $item = HuddleChecklistItem::tryFrom($itemCode);
        if (! $item) {
            return;
        }

        $signal = $answer === $item->greenAnswer() ? DayColor::Green : DayColor::Red;
        $day = $this->ensureDay();

        app(AnswerChecklistItemAction::class)->execute(
            $day,
            $item,
            $answer,
            $signal,
            (int) Auth::id(),
            $this->checklist[$itemCode]['responsible'] ?? null,
            $this->checklist[$itemCode]['due_at'] ?? null,
            $this->checklist[$itemCode]['notes'] ?? null,
        );

        $this->loadHuddleState();
    }

    public function saveItemDetails(string $itemCode): void
    {
        $this->authorizeConduct();

        $item = HuddleChecklistItem::tryFrom($itemCode);
        $current = $this->checklist[$itemCode] ?? null;

        // Só persiste detalhes se o item já foi respondido
        if (! $item || ! $current || empty($current['answer']) || empty($current['signal'])) {
            return;
        }

        $day = $this->ensureDay();

        app(AnswerChecklistItemAction::class)->execute(
            $day,
            $item,
            $current['answer'],
            DayColor::from($current['signal']),
            (int) Auth::id(),
            $current['responsible'] ?? null,
            $current['due_at'] ?? null,
            $current['notes'] ?? null,
        );

        $this->loadHuddleState();
    }

    public function saveDischarge(): void
    {
        $this->authorizeConduct();

        $day = $this->ensureDay();
        app(SetExpectedDischargeAction::class)->execute(
            $day,
            $this->expectedDischarge,
            $this->clinicalCriteria,
            (int) Auth::id(),
        );

        $this->loadHuddleState();
    }

    public function render()
    {
        return view('huddle.patient-modal.index', [
            'checklistItems' => HuddleChecklistItem::cases(),
        ]);
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private function navigateToIndex(int $index): void
    {
        $patient = $this->modalPatients[$index] ?? null;
        if (! $patient) {
            return;
        }

        $this->currentPatient = $patient;
        $this->currentPatientIndex = $index;
        $this->updateNavigationState();
        $this->loadHuddleState();
    }

    private function updateNavigationState(): void
    {
        $total = count($this->modalPatients);
        $this->canGoPrevious = $this->currentPatientIndex > 0;
        $this->canGoNext = $this->currentPatientIndex < ($total - 1);
    }

    private function authorizeConduct(): void
    {
        abort_unless(Auth::user()?->can('conduzir huddle'), 403);
    }

    private function currentAttendance(): int
    {
        return (int) ($this->currentPatient['nr_atendimento'] ?? 0);
    }

    private function currentSector(): int
    {
        return $this->sectorId ?: (int) ($this->currentPatient['cd_setor_atendimento'] ?? 0);
    }

    private function ensureDay(): HuddlePatientDay
    {
        return app(OpenPatientDayAction::class)->execute(
            $this->currentAttendance(),
            $this->currentSector(),
            (int) Auth::id(),
        );
    }

    /**
     * Carrega o estado do Huddle (dia + checklist) do paciente atual, sem criar
     * registro — só leitura. Popula os campos do formulário.
     */
    private function loadHuddleState(): void
    {
        $this->resetHuddleForm();

        $day = HuddlePatientDay::query()
            ->with('checklistAnswers')
            ->forPatient($this->currentAttendance())
            ->forDate(Carbon::today()->toDateString())
            ->first();

        if (! $day) {
            return;
        }

        $this->triageStatus = $day->status;
        $this->expectedDischarge = $day->expected_discharge_date?->format('Y-m-d');
        $this->clinicalCriteria = $day->clinical_criteria;

        foreach ($day->checklistAnswers as $answer) {
            $code = $answer->item_code instanceof HuddleChecklistItem
                ? $answer->item_code->value
                : (string) $answer->item_code;

            $this->checklist[$code] = [
                'answer' => $answer->answer,
                'signal' => $answer->signal?->value,
                'responsible' => $answer->responsible,
                'due_at' => $answer->due_at?->format('Y-m-d'),
                'notes' => $answer->notes,
            ];
        }
    }

    private function resetHuddleForm(): void
    {
        $this->triageStatus = null;
        $this->expectedDischarge = null;
        $this->clinicalCriteria = null;

        $this->checklist = [];
        foreach (HuddleChecklistItem::cases() as $item) {
            $this->checklist[$item->value] = [
                'answer' => null,
                'signal' => null,
                'responsible' => null,
                'due_at' => null,
                'notes' => null,
            ];
        }
    }
}
