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

    /** Mensagem de erro de validação (campos obrigatórios). */
    public ?string $errorMsg = null;

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
            'safety', 'filledByLogin', 'filledAt', 'errorMsg',
            // NÃO reseta 'locked' — precisa persistir após finalizar
        ]);
    }

    public function saveSafetyAssessment(): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        // Trava: se já existe registro finalizado no dia, não pode ser alterado.
        $jaFinalizado = HuddleSafetyAssessment::query()
            ->forSector($this->sectorId)
            ->forDate(Carbon::today()->toDateString())
            ->where('finalized', true)
            ->exists();

        abort_if($jaFinalizado, 403, 'O Round Unidade já foi preenchido hoje e não pode ser alterado.');

        // Todos os campos são obrigatórios; números não podem ser negativos.
        if (! $this->allFieldsFilled()) {
            $this->errorMsg = 'Preencha todos os campos (sem números negativos) antes de salvar.';
            return;
        }

        $this->errorMsg = null;

        app(SaveSafetyAssessmentAction::class)->execute($this->sectorId, $this->safety, (int) Auth::id());

        // Salvo com sucesso: marca como finalizado e fecha modal
        $this->locked = true;
        $this->dispatch('huddle-round-saved', message: 'Round Unidade salvo com sucesso!');
        $this->dispatch('huddle-round-closed');
        
        // Fecha sem resetar 'locked'
        $this->showModal = false;
    }

    /**
     * Valida que todos os campos do Round Unidade foram preenchidos.
     * Números: inteiro >= 0. Sim/Não: boolean definido. Classificação e textos: preenchidos.
     */
    private function allFieldsFilled(): bool
    {
        $numeros = [
            'expected_discharges', 'expected_admissions', 'blocked_beds_isolation',
            'blocked_beds_maintenance', 'pressure_injuries', 'falls',
        ];
        foreach ($numeros as $campo) {
            $valor = $this->safety[$campo] ?? null;
            if ($valor === null || $valor === '' || ! is_numeric($valor) || (int) $valor < 0) {
                return false;
            }
        }

        $simNao = [
            'critical_patient_no_bed', 'critical_medication_failure', 'adverse_event_24h',
            'physical_chemical_restraint', 'barrier_breach', 'staff_shortage', 'critical_exam_delay',
        ];
        foreach ($simNao as $campo) {
            if (! is_bool($this->safety[$campo] ?? null)) {
                return false;
            }
        }

        if (! in_array($this->safety['unit_classification'] ?? null, ['verde', 'amarelo', 'vermelho'], true)) {
            return false;
        }

        foreach (['justification', 'immediate_measures'] as $campo) {
            if (trim((string) ($this->safety[$campo] ?? '')) === '') {
                return false;
            }
        }

        return true;
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
        $this->errorMsg = null;

        $sa = HuddleSafetyAssessment::query()
            ->with('updatedBy', 'createdBy')
            ->forSector($this->sectorId)
            ->forDate(Carbon::today()->toDateString())
            ->first();

        if (! $sa) {
            return;
        }

        // Se o registro existe e está marcado como finalizado, abre somente-leitura.
        $this->locked = (bool) ($sa->finalized ?? true);

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
