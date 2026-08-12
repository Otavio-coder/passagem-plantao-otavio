<?php

namespace App\Livewire;

use App\Actions\Huddle\SaveSafetyAssessmentAction;
use App\Models\Huddle\HuddleSafetyAssessment;
use App\Models\Huddle\HuddleUnitQuestion;
use App\Services\Huddle\HuddleAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal do Huddle de Segurança (gestão à vista) por unidade.
 *
 * Acionado pelo botão "Round Unidade" de qualquer card do setor. O preenchimento é
 * único por unidade/dia (huddle_safety_assessments) e compartilhado por todos os
 * leitos daquela unidade.
 *
 * As perguntas são lidas dinamicamente da tabela `huddle_unit_questions` (MySQL),
 * permitindo edição sem deploy. Cache de 24h (raramente mudam).
 */
class HuddleUnitSafetyModal extends Component
{
    public bool $showModal = false;

    public int $sectorId = 0;

    public string $hospitalName = '';

    public string $sectorLabel = '';

    /** Auditoria: login e data/hora da última atualização. */
    public ?string $filledByLogin = null;

    public ?string $filledAt = null;

    /** Campos dos 4 eixos. @var array<string, mixed> */
    public array $safety = [];

    /** Travado: já existe registro da unidade no dia — não pode ser alterado. */
    public bool $locked = false;

    #[On('openUnitSafety')]
    public function openForSector(int $sectorId, string $hospital = '', string $sectorLabel = ''): void
    {
        $this->sectorId = $sectorId;
        $this->hospitalName = $hospital;
        $this->sectorLabel = $sectorLabel;

        $this->loadSafety();

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset([
            'showModal', 'sectorId', 'hospitalName', 'sectorLabel',
            'safety', 'filledByLogin', 'filledAt', 'locked',
        ]);
    }

    public function saveSafetyAssessment(): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        // Trava: uma vez preenchido no dia, o Round Unidade não pode ser alterado.
        $jaPreenchido = HuddleSafetyAssessment::query()
            ->forSector($this->sectorId)
            ->forDate(Carbon::today()->toDateString())
            ->exists();

        abort_if($jaPreenchido, 403, 'O Round Unidade já foi preenchido hoje e não pode ser alterado.');

        app(SaveSafetyAssessmentAction::class)->execute($this->sectorId, $this->safety, (int) Auth::id());

        // Avisa a tela (toast + marca o card) e fecha o modal.
        $this->dispatch('huddle-round-saved', message: 'Round Unidade salvo com sucesso!');
        $this->closeModal();
    }

    public function render()
    {
        $availability = app(HuddleAvailability::class);

        return view('huddle.unit-safety-modal.index', [
            'huddleAvailable' => $availability->isAvailable(),
            'huddleBlockedReason' => $availability->blockedReason(),
            'questionsByAxis' => $this->getQuestionsByAxis(),
        ]);
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private function authorizeConduct(): void
    {
        abort_unless(Auth::user()?->can('conduzir huddle'), 403);
    }

    private function ensureAvailable(): void
    {
        abort_unless(app(HuddleAvailability::class)->isAvailable(), 403, 'Huddle indisponível hoje.');
    }

    /**
     * Retorna as perguntas ativas agrupadas por eixo, com cache de 24h.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, HuddleUnitQuestion>>
     */
    private function getQuestionsByAxis(): \Illuminate\Support\Collection
    {
        return Cache::remember('huddle_unit_questions_by_axis', 86400, function () {
            return HuddleUnitQuestion::activeGroupedByAxis();
        });
    }

    /**
     * Carrega o card da unidade do dia (se houver) e a auditoria; senão, deixa vazio.
     */
    private function loadSafety(): void
    {
        $this->safety = $this->defaultSafety();
        $this->filledByLogin = null;
        $this->filledAt = null;
        $this->locked = false;

        $sa = HuddleSafetyAssessment::query()
            ->with('updatedBy', 'createdBy')
            ->forSector($this->sectorId)
            ->forDate(Carbon::today()->toDateString())
            ->first();

        if (! $sa) {
            return;
        }

        // Já existe registro no dia: abre só-leitura (não pode ser alterado).
        $this->locked = true;

        // Carrega dinamicamente as respostas com base nas perguntas cadastradas
        $questions = $this->getQuestionsByAxis()->flatten();
        foreach ($questions as $question) {
            $key = $question->field_key;
            $value = $sa->{$key};

            // Para enums (unit_classification), pega o ->value
            if (is_object($value) && property_exists($value, 'value')) {
                $value = $value->value;
            }

            $this->safety[$key] = $value;
        }

        $auditUser = $sa->updatedBy ?? $sa->createdBy;
        $this->filledByLogin = $auditUser?->username;
        $this->filledAt = ($sa->updated_at ?? $sa->created_at)?->format('d/m/Y H:i');
    }

    /**
     * Gera os valores padrão (null) a partir das perguntas cadastradas no banco.
     *
     * @return array<string, mixed>
     */
    private function defaultSafety(): array
    {
        $questions = $this->getQuestionsByAxis()->flatten();
        $defaults = [];

        foreach ($questions as $question) {
            $defaults[$question->field_key] = null;
        }

        // Fallback: se o banco ainda não tiver perguntas, mantém os campos base
        if (empty($defaults)) {
            return [
                'expected_discharges' => null,
                'expected_admissions' => null,
                'blocked_beds_isolation' => null,
                'blocked_beds_maintenance' => null,
                'critical_patient_no_bed' => null,
                'critical_medication_failure' => null,
                'adverse_event_24h' => null,
                'physical_chemical_restraint' => null,
                'barrier_breach' => null,
                'pressure_injuries' => null,
                'falls' => null,
                'staff_shortage' => null,
                'critical_exam_delay' => null,
                'unit_classification' => null,
                'justification' => null,
                'immediate_measures' => null,
            ];
        }

        return $defaults;
    }
}
