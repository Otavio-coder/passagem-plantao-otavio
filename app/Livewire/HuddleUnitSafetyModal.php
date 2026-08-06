<?php

namespace App\Livewire;

use App\Actions\Huddle\SaveSafetyAssessmentAction;
use App\Models\Huddle\HuddleSafetyAssessment;
use App\Services\Huddle\HuddleAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal do Huddle de Segurança (gestão à vista) por unidade.
 *
 * Acionado pelo botão "Round Unidade" de qualquer card do setor. O preenchimento é
 * único por unidade/dia (huddle_safety_assessments) e compartilhado por todos os
 * leitos daquela unidade.
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
            'safety', 'filledByLogin', 'filledAt',
        ]);
    }

    public function saveSafetyAssessment(): void
    {
        $this->authorizeConduct();
        $this->ensureAvailable();

        app(SaveSafetyAssessmentAction::class)->execute($this->sectorId, $this->safety, (int) Auth::id());

        $this->loadSafety();
    }

    public function render()
    {
        $availability = app(HuddleAvailability::class);

        return view('huddle.unit-safety-modal.index', [
            'huddleAvailable' => $availability->isAvailable(),
            'huddleBlockedReason' => $availability->blockedReason(),
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
     * Carrega o card da unidade do dia (se houver) e a auditoria; senão, deixa vazio.
     */
    private function loadSafety(): void
    {
        $this->safety = $this->defaultSafety();
        $this->filledByLogin = null;
        $this->filledAt = null;

        $sa = HuddleSafetyAssessment::query()
            ->with('updatedBy', 'createdBy')
            ->forSector($this->sectorId)
            ->forDate(Carbon::today()->toDateString())
            ->first();

        if (! $sa) {
            return;
        }

        $this->safety = [
            'expected_discharges' => $sa->expected_discharges,
            'expected_admissions' => $sa->expected_admissions,
            'blocked_beds_isolation' => $sa->blocked_beds_isolation,
            'blocked_beds_maintenance' => $sa->blocked_beds_maintenance,
            'critical_patient_no_bed' => $sa->critical_patient_no_bed,
            'critical_medication_failure' => $sa->critical_medication_failure,
            'adverse_event_24h' => $sa->adverse_event_24h,
            'physical_chemical_restraint' => $sa->physical_chemical_restraint,
            'barrier_breach' => $sa->barrier_breach,
            'pressure_injuries' => $sa->pressure_injuries,
            'falls' => $sa->falls,
            'staff_shortage' => $sa->staff_shortage,
            'critical_exam_delay' => $sa->critical_exam_delay,
            'unit_classification' => $sa->unit_classification?->value,
            'justification' => $sa->justification,
            'immediate_measures' => $sa->immediate_measures,
        ];

        $auditUser = $sa->updatedBy ?? $sa->createdBy;
        $this->filledByLogin = $auditUser?->username;
        $this->filledAt = ($sa->updated_at ?? $sa->created_at)?->format('d/m/Y H:i');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSafety(): array
    {
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
}
