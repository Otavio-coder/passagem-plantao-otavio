<?php

namespace App\Livewire;

use App\Actions\Huddle\AnswerChecklistItemAction;
use App\Actions\Huddle\OpenPatientDayAction;
use App\Actions\Huddle\SaveHuddleCommentsAction;
use App\Actions\Huddle\SetHuddleTriageAction;
use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\Huddle\HuddlePatientDay;
use App\Services\Huddle\HuddleAvailability;
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

    public ?string $clinicalCriteria = null;

    /** Cor do dia derivada do checklist ('red' | 'green'). Controla o campo de comentários. */
    public ?string $dayColor = null;

    /** Comentários do dia — exibido quando o dia é vermelho. */
    public ?string $comments = null;

    /** Auditoria do dia: login e data/hora da última atualização de quem preencheu. */
    public ?string $filledByLogin = null;

    public ?string $filledByName = null;

    public ?string $filledAt = null;

    /** @var array<string, array{answer: ?string, signal: ?string, responsible: ?string, due_at: ?string, notes: ?string}> */
    public array $checklist = [];

    #[On('openModal')]
    public function openWithPatient(int $attendanceNumber, string $hospital, array $sbarPatient, array $patients = [], int $sectorId = 0): void
    {
        $this->hospitalName = $hospital;
        $this->sectorId = $sectorId;

        // Lista de navegação: carrega do serviço (robusto) quando há setor; senão usa
        // a lista recebida no evento como fallback.
        $sectorPatients = $sectorId > 0
            ? app(\App\Services\Huddle\HuddleBoardService::class)->forSector($sectorId)
            : $patients;

        $this->modalPatients = collect($sectorPatients)
            ->filter(fn ($p) => ! empty($p['has_patient']) && ! empty($p['nr_atendimento']))
            ->values()
            ->map(fn ($p) => $this->slimPatient($p))
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
            'triageStatus', 'clinicalCriteria', 'checklist',
            'dayColor', 'comments', 'filledByLogin', 'filledByName', 'filledAt',
        ]);
    }

    // ── Ações do Huddle (edição — exige 'conduzir huddle') ──────────────────

    public function setTriage(string $status): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        $day = $this->ensureDay();
        app(SetHuddleTriageAction::class)->execute($day, $status, (int) Auth::id());

        $this->loadHuddleState();
    }

    public function answerItem(string $itemCode, string $answer): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        $item = HuddleChecklistItem::tryFrom($itemCode);
        if (! $item) {
            return;
        }

        // Toggle: clicar na mesma resposta desmarca o item
        $currentAnswer = $this->checklist[$itemCode]['answer'] ?? null;
        if ($currentAnswer === $answer) {
            $this->clearItem($itemCode, $item);

            return;
        }

        $signal = $answer === $item->greenAnswer() ? DayColor::Green : DayColor::Red;
        $day = $this->ensureDay();

        app(AnswerChecklistItemAction::class)->execute(
            day: $day,
            item: $item,
            answer: $answer,
            signal: $signal,
            userId: (int) Auth::id(),
            responsible: $this->checklist[$itemCode]['responsible'] ?? null,
            dueAt: $this->checklist[$itemCode]['due_at'] ?? null,
            notes: $this->checklist[$itemCode]['notes'] ?? null,
            cdPessoaFisica: $this->currentPatient['cd_pessoa_fisica'] ?? null,
            nmUsuario: Auth::user()?->username,
            cdSetorAtendimento: $this->currentSector() ?: null,
        );

        $this->loadHuddleState();
    }

    /**
     * Remove a resposta de um item do checklist (toggle off).
     */
    public function clearItem(string $itemCode, ?HuddleChecklistItem $item = null): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        $item ??= HuddleChecklistItem::tryFrom($itemCode);
        if (! $item) {
            return;
        }

        $day = HuddlePatientDay::query()
            ->forPatient($this->currentAttendance())
            ->forDate(Carbon::today()->toDateString())
            ->first();

        if ($day) {
            $day->checklistAnswers()->where('item_code', $itemCode)->delete();
            unset($day->checklistAnswers); // limpa relação carregada
            $day->color = $day->deriveColorFromChecklist();
            $day->save();
        }

        $this->loadHuddleState();
    }

    public function saveItemDetails(string $itemCode): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        $item = HuddleChecklistItem::tryFrom($itemCode);
        $current = $this->checklist[$itemCode] ?? null;

        // Só persiste detalhes se o item já foi respondido
        if (! $item || ! $current || empty($current['answer']) || empty($current['signal'])) {
            return;
        }

        $day = $this->ensureDay();

        app(AnswerChecklistItemAction::class)->execute(
            day: $day,
            item: $item,
            answer: $current['answer'],
            signal: DayColor::from($current['signal']),
            userId: (int) Auth::id(),
            responsible: $current['responsible'] ?? null,
            dueAt: $current['due_at'] ?? null,
            notes: $current['notes'] ?? null,
            cdPessoaFisica: $this->currentPatient['cd_pessoa_fisica'] ?? null,
            nmUsuario: Auth::user()?->username,
            cdSetorAtendimento: $this->currentSector() ?: null,
        );

        $this->loadHuddleState();
    }

    /**
     * Salva as recomendações (notes) de todos os itens respondidos de uma vez.
     * Substitui os botões individuais de "Salvar" por item.
     */
    public function saveAllNotes(): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        // Valida: todas as questões devem estar respondidas antes de salvar.
        $allAnswered = collect(HuddleChecklistItem::cases())->every(
            fn ($item) => ! empty($this->checklist[$item->value]['answer'] ?? null)
        );

        if (! $allAnswered) {
            return;
        }

        $day = $this->ensureDay();

        foreach ($this->checklist as $code => $state) {
            $item = HuddleChecklistItem::tryFrom($code);
            if (! $item || empty($state['answer']) || empty($state['signal'])) {
                continue;
            }

            app(AnswerChecklistItemAction::class)->execute(
                day: $day,
                item: $item,
                answer: $state['answer'],
                signal: DayColor::from($state['signal']),
                userId: (int) Auth::id(),
                responsible: $state['responsible'] ?? null,
                dueAt: $state['due_at'] ?? null,
                notes: $state['notes'] ?? null,
                cdPessoaFisica: $this->currentPatient['cd_pessoa_fisica'] ?? null,
                nmUsuario: Auth::user()?->username,
                cdSetorAtendimento: $this->currentSector() ?: null,
            );
        }

        $this->loadHuddleState();
        $this->dispatch('huddle-notes-saved', message: 'Recomendações salvas com sucesso!');
    }

    public function render()
    {
        $availability = app(HuddleAvailability::class);
        $tasyLabels = app(\App\Services\Tasy\TasyEvaluationService::class)->getChecklistLabels();

        return view('huddle.patient-modal.index', [
            'checklistItems' => HuddleChecklistItem::cases(),
            'tasyLabels' => $tasyLabels,
            'huddleAvailable' => $availability->isAvailable(),
            'huddleBlockedReason' => $availability->blockedReason(),
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

    /**
     * Projeta apenas os campos que o modal exibe — evita serializar o paciente inteiro
     * (~150 KB com pending_events/checklist) no snapshot do Livewire, o que deixava a
     * navegação lenta.
     *
     * @return array<string, mixed>
     */
    private function slimPatient(array $p): array
    {
        return [
            'has_patient' => true,
            'nr_atendimento' => $p['nr_atendimento'] ?? null,
            'cd_pessoa_fisica' => $p['cd_pessoa_fisica'] ?? null,
            'cd_setor_atendimento' => $p['cd_setor_atendimento'] ?? $this->sectorId,
            'cd_unidade_basica' => $p['cd_unidade_basica'] ?? null,
            'nm_pessoa_fisica' => $p['nm_pessoa_fisica'] ?? null,
            'age_label' => $p['age_label'] ?? null,
            'internment_days' => $p['internment_days'] ?? null,
            'convenio' => $p['convenio'] ?? null,
            'medico_responsavel' => $p['medico_responsavel'] ?? null,
            'mews_score' => $p['mews_score'] ?? null,
            'pews_score' => $p['pews_score'] ?? null,
            'braden_score' => $p['braden_score'] ?? null,
            'morse_score' => $p['morse_score'] ?? null,
            'pain_score' => $p['pain_score'] ?? null,
            'vte_score' => $p['vte_score'] ?? null,
            'huddle_discharge_within_72h' => $p['huddle_discharge_within_72h'] ?? false,
            'huddle_discharge_within_24h' => $p['huddle_discharge_within_24h'] ?? false,
            'discharge_info' => [
                'dt_previsto_alta_formatted' => $p['discharge_info']['dt_previsto_alta_formatted'] ?? null,
            ],
            'label' => trim(($p['cd_unidade_basica'] ?? '').' – '.($p['nm_pessoa_fisica'] ?? 'Paciente')),
        ];
    }

    private function authorizeConduct(): void
    {
        abort_unless(Auth::user()?->can('conduzir huddle'), 403);
    }

    /**
     * Bloqueia a gravação em dias sem funcionamento (fim de semana/feriado).
     */
    private function ensureAvailable(): void
    {
        abort_unless(app(HuddleAvailability::class)->isAvailable(), 403, 'Huddle indisponível hoje.');
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
            ->with(['updatedBy', 'createdBy', 'checklistAnswers.answeredBy'])
            ->forPatient($this->currentAttendance())
            ->forDate(Carbon::today()->toDateString())
            ->first();

        if (! $day) {
            return;
        }

        $this->triageStatus = $day->status;
        $this->clinicalCriteria = $day->clinical_criteria;
        $this->dayColor = $day->color instanceof DayColor ? $day->color->value : (string) $day->color;
        $this->comments = $day->comments;

        // Auditoria do dia: quem fez a última atualização (updatedBy) ou, se ainda não
        // houve atualização, quem abriu o registro (createdBy).
        $auditUser = $day->updatedBy ?? $day->createdBy;
        $this->filledByLogin = $auditUser?->username;
        $this->filledByName = $auditUser?->name;
        $this->filledAt = ($day->updated_at ?? $day->created_at)?->format('d/m/Y H:i');

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
                'answered_by_login' => $answer->answeredBy?->username,
                'answered_at' => $answer->updated_at?->format('d/m/Y H:i'),
            ];
        }
    }

    private function resetHuddleForm(): void
    {
        $this->triageStatus = null;
        $this->clinicalCriteria = null;
        $this->dayColor = null;
        $this->comments = null;
        $this->filledByLogin = null;
        $this->filledByName = null;
        $this->filledAt = null;

        $this->checklist = [];
        foreach (HuddleChecklistItem::cases() as $item) {
            $this->checklist[$item->value] = [
                'answer' => null,
                'signal' => null,
                'responsible' => null,
                'due_at' => null,
                'notes' => null,
                'answered_by_login' => null,
                'answered_at' => null,
            ];
        }
    }
}
