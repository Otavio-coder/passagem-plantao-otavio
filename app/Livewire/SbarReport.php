<?php

namespace App\Livewire;

use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use App\Services\ShiftService;
use App\Services\Tasy\TasyService;
use App\Support\PatientCardPresentation;
use App\Support\PendingEventPresentation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SbarReport extends Component
{
    // Primitive state — safe in wire:snapshot
    public $errorMessage = null;

    public $lastRefresh = null;

    // User selection state
    public $selectedHospital = null;

    public $selectedSector = null;

    // Sector onboarding
    public bool $showSectorOnboarding = false;

    public array $selectedSectors = [];

    // Services
    protected TasyService $tasyService;

    protected $listeners = [
        'refreshData' => 'refreshData',
        'sectorOnboardingSaved' => 'onSectorOnboardingSaved',
        'handover-updated' => 'onHandoverUpdated',
    ];

    public function boot(TasyService $tasyService)
    {
        $this->tasyService = $tasyService;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Computed properties — NOT serialized into wire:snapshot
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * User's available hospitals. Persisted for the component lifecycle;
     * cleared with unset($this->hospitals) when preferences change.
     */
    #[Computed(persist: true)]
    public function hospitals(): array
    {
        return Auth::user()->sectorPreferences()
            ->select(['hospital_code', 'hospital_name'])
            ->distinct()
            ->orderBy('hospital_name')
            ->get()
            ->map(fn ($p) => [
                'hospital_id' => (int) $p->hospital_code,
                'hospital_name' => $p->hospital_name,
            ])
            ->toArray();
    }

    /**
     * Sectors for the currently selected hospital. Persisted; cleared on
     * hospital change via unset($this->sectors).
     */
    #[Computed(persist: true)]
    public function sectors(): array
    {
        if (! $this->selectedHospital) {
            return [];
        }

        return Auth::user()->sectorPreferences()
            ->where('hospital_code', $this->selectedHospital)
            ->orderBy('sector_name')
            ->get()
            ->map(fn ($p) => [
                'cd_setor_atendimento' => (int) $p->sector_code,
                'ds_setor_atendimento' => $p->sector_name,
            ])
            ->toArray();
    }

    /**
     * Patient list for the selected sector. Persisted; cleared on sector
     * change, refresh, or handover update via unset($this->patients).
     *
     * TasyService already caches Oracle data for 15 min — this computed layer
     * eliminates the cost of serializing 30+ patient objects into every
     * Livewire wire:snapshot (~150 KB overhead).
     */
    #[Computed(persist: true)]
    public function patients(): array
    {
        if (! $this->selectedSector) {
            return [];
        }

        try {
            $patients = $this->tasyService->getSectorPatientsForSbar($this->selectedSector);

            return $this->preparePatientsForView($this->injectHandoverStatus($patients));
        } catch (\Exception $e) {
            Log::error('Error loading patients', [
                'exception' => $e,
                'selected_sector' => $this->selectedSector,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro ao carregar pacientes: '.$e->getMessage();

            return [];
        }
    }

    /**
     * All available sectors for the onboarding modal. Not persisted — only
     * computed when the modal is open.
     */
    #[Computed]
    public function availableSectors(): array
    {
        if (! $this->showSectorOnboarding) {
            return [];
        }

        try {
            return Sector::allowedForPreferences();
        } catch (\Exception $e) {
            Log::error('Error loading available sectors for onboarding', [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);

            return [];
        }
    }

    /**
     * Display name of the currently selected hospital.
     */
    #[Computed]
    public function currentHospitalName(): string
    {
        if (! $this->selectedHospital) {
            return 'Carregando...';
        }

        $hospital = collect($this->hospitals)->firstWhere('hospital_id', (int) $this->selectedHospital);

        return $hospital['hospital_name'] ?? 'Hospital';
    }

    #[Computed]
    public function currentShiftName(): string
    {
        return ShiftService::getShiftInfo()['shift'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────────────────

    public function mount()
    {
        $user = Auth::user();

        if (! $user->hasConfiguredSectors()) {
            $this->dispatch('checkUserSectors');
            $this->errorMessage = 'Você precisa configurar seus setores de acesso antes de usar o SBAR.';
            $this->showSectorOnboarding = true;

            return;
        }

        try {
            $hospitals = $this->hospitals;

            if (empty($hospitals)) {
                $this->errorMessage = 'Nenhum hospital disponível.';

                return;
            }

            if (! $this->selectedHospital) {
                $this->selectedHospital = $hospitals[0]['hospital_id'];
            }

            $sectors = $this->sectors;

            if (empty($sectors)) {
                $this->errorMessage = 'Nenhum setor configurado para este hospital. Atualize suas preferências de setor.';

                return;
            }

            if (! $this->selectedSector) {
                $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            }

            $this->lastRefresh = now()->format('H:i:s');
            $this->auditSectorView('mount');
        } catch (\Exception $e) {
            Log::error('SBAR mount error', [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro durante inicialização: '.$e->getMessage();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────────────────

    public function changeHospital($hospitalId)
    {
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        // Invalidate cached computed values for the new hospital
        unset($this->sectors);
        unset($this->patients);

        $sectors = $this->sectors;

        if (! empty($sectors)) {
            $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            $this->lastRefresh = now()->format('H:i:s');
            $this->auditSectorView('hospital_change');
        } else {
            $this->errorMessage = 'Nenhum setor configurado para este hospital.';
        }
    }

    public function changeSector($sectorId)
    {
        $user = Auth::user();
        $allowedSectorCodes = $user->sectorPreferences()->pluck('sector_code')->toArray();

        if (! in_array((string) $sectorId, array_map('strval', $allowedSectorCodes))) {
            Log::warning('Tentativa de acesso a setor não autorizado', [
                'user_id' => $user->id,
                'sector_id' => $sectorId,
            ]);
            $this->errorMessage = 'Acesso negado: setor não autorizado.';

            return;
        }

        $this->selectedSector = $sectorId;
        unset($this->patients); // invalidate computed cache → fresh data on next render

        $this->lastRefresh = now()->format('H:i:s');
        $this->auditSectorView('sector_change');
    }

    public function refreshData()
    {
        try {
            if ($this->selectedSector) {
                $this->tasyService->clearSectorCache($this->selectedSector);
            }

            unset($this->patients); // force recompute after cache clear
            $this->lastRefresh = now()->format('H:i:s');
        } catch (\Exception $e) {
            Log::error('Error in refreshData', [
                'exception' => $e,
                'selected_sector' => $this->selectedSector,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro ao atualizar: '.$e->getMessage();
        }
    }

    public function updateSectorPatients()
    {
        unset($this->patients);
        $this->lastRefresh = now()->format('H:i:s');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Onboarding
    // ──────────────────────────────────────────────────────────────────────────

    public function openSectorOnboarding(): void
    {
        $this->selectedSectors = Auth::user()
            ->sectorPreferences()
            ->pluck('sector_code')
            ->toArray();

        $this->showSectorOnboarding = true;
    }

    public function saveSectorPreferences(): void
    {
        $user = Auth::user();

        if (empty($this->selectedSectors)) {
            return;
        }

        try {
            $sectorsMap = collect($this->availableSectors)->keyBy('sector_code');

            $previousSectors = $user->sectorPreferences()
                ->pluck('sector_name', 'sector_code')
                ->toArray();

            UserSectorPreference::where('user_id', $user->id)->delete();

            foreach ($this->selectedSectors as $sectorCode) {
                $meta = $sectorsMap->get($sectorCode, []);
                UserSectorPreference::create([
                    'user_id' => $user->id,
                    'sector_code' => $sectorCode,
                    'sector_name' => $meta['sector_name'] ?? null,
                    'hospital_code' => $meta['hospital_code'] ?? null,
                    'hospital_name' => $meta['hospital_name'] ?? null,
                ]);
            }

            $newSectors = collect($this->selectedSectors)
                ->mapWithKeys(fn ($code) => [$code => $sectorsMap->get($code)['sector_name'] ?? $code])
                ->toArray();

            Log::channel('audit')->info('preferences.updated', [
                'source' => 'onboarding',
                'user_id' => $user->id,
                'user' => $user->name,
                'previous_sectors' => $previousSectors,
                'new_sectors' => $newSectors,
                'ip' => request()->ip(),
            ]);

            $this->showSectorOnboarding = false;
            $this->selectedSectors = [];
            $this->selectedHospital = null;
            $this->selectedSector = null;

            // Invalidate all cached computed values
            unset($this->hospitals);
            unset($this->sectors);
            unset($this->patients);

            // Re-initialize with new preferences
            $this->mount();
        } catch (\Exception $e) {
            Log::error('Error saving sector preferences', [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function onSectorOnboardingSaved(): void
    {
        $this->showSectorOnboarding = false;
        $this->selectedHospital = null;
        $this->selectedSector = null;

        unset($this->hospitals);
        unset($this->sectors);
        unset($this->patients);

        $this->mount();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Event handlers
    // ──────────────────────────────────────────────────────────────────────────

    public function onHandoverUpdated(string $nr): void
    {
        // Invalidate computed cache — next render will re-fetch handover status
        // from DB (batch query) while hitting TasyService's 15-min Oracle cache.
        unset($this->patients);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Rendering
    // ──────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('sbar.report.index', [
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'patients' => $this->patients,
            'errorMessage' => $this->errorMessage,
            'selectedHospital' => $this->selectedHospital,
            'selectedSector' => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'currentShiftName' => $this->currentShiftName,
            'lastRefresh' => $this->lastRefresh,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function auditSectorView(string $action): void
    {
        if (! $this->selectedSector) {
            return;
        }

        $sectorName = collect($this->sectors)
            ->firstWhere('cd_setor_atendimento', $this->selectedSector)['ds_setor_atendimento'] ?? $this->selectedSector;

        Log::channel('audit')->info('sbar.viewed', [
            'action' => $action,
            'user_id' => Auth::id(),
            'user' => Auth::user()->name ?? 'unknown',
            'sector_id' => $this->selectedSector,
            'sector_name' => $sectorName,
            'hospital' => $this->currentHospitalName,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Injects handover_done / handover_last_time / handover_msg_count into
     * each patient via a single batch MySQL query.
     */
    private function injectHandoverStatus(array $patients): array
    {
        $nrs = collect($patients)
            ->filter(fn ($p) => ! empty($p['nr_atendimento']) && ($p['has_patient'] ?? false))
            ->pluck('nr_atendimento')
            ->values()
            ->toArray();

        if (empty($nrs)) {
            return $patients;
        }

        [$shiftStart, $shiftEnd] = ShiftService::getShiftWindow();

        // Tolerância de borda do turno para não perder anotações na virada.
        $shiftStart = $shiftStart->copy()->subMinutes(30);
        $shiftEnd = $shiftEnd->copy()->addMinutes(30);

        $rows = DB::table('chat_messages')
            ->whereIn('nr_atendimento', $nrs)
            ->whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->select([
                'nr_atendimento',
                DB::raw('COUNT(*) as msg_count'),
                DB::raw('MAX(created_at) as last_msg'),
            ])
            ->groupBy('nr_atendimento')
            ->get()
            ->keyBy('nr_atendimento');

        // Fetch pinned evaluations (priority)
        $pinnedRows = DB::table('chat_message_pins as cmp')
            ->join('chat_messages as cm', 'cm.id', '=', 'cmp.message_id')
            ->leftJoin('users as u', 'u.id', '=', 'cmp.pinned_by')
            ->whereIn('cm.nr_atendimento', $nrs)
            ->whereNull('cmp.unpinned_at')
            ->orderByDesc('cmp.pinned_at')
            ->select([
                'cm.nr_atendimento',
                'cm.content',
                'cm.created_at as message_created_at',
                'cmp.pinned_at',
                'u.name as pinned_by_name',
                'u.photo',
            ])
            ->get();

        $latestPinnedByAttendance = [];
        foreach ($pinnedRows as $pinnedRow) {
            $attendance = (int) ($pinnedRow->nr_atendimento ?? 0);
            if ($attendance <= 0 || isset($latestPinnedByAttendance[$attendance])) {
                continue;
            }

            $latestPinnedByAttendance[$attendance] = [
                'content' => (string) ($pinnedRow->content ?? ''),
                'pinned_at' => $pinnedRow->pinned_at,
                'pinned_at_formatted' => $pinnedRow->pinned_at
                    ? Carbon::parse($pinnedRow->pinned_at)->format('d/m H:i')
                    : null,
                'message_created_at' => $pinnedRow->message_created_at,
                'message_created_at_formatted' => $pinnedRow->message_created_at
                    ? Carbon::parse($pinnedRow->message_created_at)->format('d/m H:i')
                    : null,
                'pinned_by_name' => $pinnedRow->pinned_by_name,
                'photo' => (string) ($pinnedRow->photo ?? ''),
            ];
        }

        // Fetch latest evaluations (fallback when no pinned) for attendances without pinned
        $attendancesWithoutPinned = array_diff($nrs, array_keys($latestPinnedByAttendance));
        $latestEvaluationByAttendance = [];

        if (! empty($attendancesWithoutPinned)) {
            $latestRows = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'u.id', '=', 'cm.user_id')
                ->whereIn('cm.nr_atendimento', $attendancesWithoutPinned)
                ->select([
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'u.name as user_name',
                    'u.photo',
                ])
                ->orderByDesc('cm.created_at')
                ->get();

            foreach ($latestRows as $latestRow) {
                $attendance = (int) ($latestRow->nr_atendimento ?? 0);
                if ($attendance <= 0 || isset($latestEvaluationByAttendance[$attendance])) {
                    continue;
                }

                $latestEvaluationByAttendance[$attendance] = [
                    'content' => (string) ($latestRow->content ?? ''),
                    'created_at' => $latestRow->created_at,
                    'created_at_formatted' => $latestRow->created_at
                        ? Carbon::parse($latestRow->created_at)->format('d/m H:i')
                        : null,
                    'user_name' => $latestRow->user_name,
                    'photo' => (string) ($latestRow->photo ?? ''),
                ];
            }
        }

        return array_map(function ($patient) use ($rows, $latestPinnedByAttendance, $latestEvaluationByAttendance) {
            if (! ($patient['has_patient'] ?? false)) {
                return $patient;
            }

            $nr = $patient['nr_atendimento'] ?? null;
            $row = $nr ? $rows->get($nr) : null;

            $patient['handover_done'] = $row !== null;
            $patient['handover_last_time'] = $row
                ? Carbon::parse($row->last_msg)->format('H:i')
                : null;
            $patient['handover_msg_count'] = $row ? (int) $row->msg_count : 0;

            // Priority: pinned evaluation; fallback: latest evaluation
            $patient['pinned_evaluation'] = $latestPinnedByAttendance[(int) $nr] ?? null;
            $patient['latest_evaluation'] = $latestEvaluationByAttendance[(int) $nr] ?? null;

            return $patient;
        }, $patients);
    }

    private function preparePatientsForView(array $patients): array
    {
        return array_map(function (array $patient): array {
            $pendingEvents = $patient['pending_events'] ?? [];

            if (! is_array($pendingEvents)) {
                $pendingEvents = [];
            }

            $structured = PendingEventPresentation::buildPendingModalData($pendingEvents);
            $surgeries = $patient['procedimentos_cirurgicos'] ?? [];
            if (! is_array($surgeries)) {
                $surgeries = [];
            }

            $dischargeInfo = $patient['discharge_info'] ?? null;
            if (! is_array($dischargeInfo)) {
                $dischargeInfo = null;
            }

            $multidisciplinaryRequests = $patient['multidisciplinary_requests'] ?? [];
            if (! is_array($multidisciplinaryRequests)) {
                $multidisciplinaryRequests = [];
            }

            $patient['pending_events'] = $structured['events'];
            $patient['pending_groups'] = $structured['groups'];
            $patient['first_pending_event'] = $structured['first_event'];
            $patient['allergy_items'] = PatientCardPresentation::buildAllergyItems($patient);
            $patient['isolation_items'] = PatientCardPresentation::buildIsolationItems($patient['motivos_isolamento'] ?? null);
            $patient['first_pending_style'] = PatientCardPresentation::buildFirstPendingStyle($structured['first_event']);
            $patient['sector_exec_fallback'] = PatientCardPresentation::buildSectorFallbackLabel($patient);
            $patient['handover_shift_name'] = PatientCardPresentation::shiftDisplayName($this->currentShiftName);
            $patient['procedimentos_cirurgicos'] = PatientCardPresentation::buildSurgeryItems($surgeries);
            $patient['discharge_display'] = PatientCardPresentation::buildDischargeDisplay($dischargeInfo);
            $patient['ews_display'] = PatientCardPresentation::buildEwsDisplay($patient);
            $patient['multidisciplinary_requests'] = PatientCardPresentation::buildMultidisciplinaryRequests($multidisciplinaryRequests);
            $patient['pending_modal_meta'] = PatientCardPresentation::buildPendingModalMeta($structured['events']);
            $patient['pending_type_filter'] = collect($structured['events'])
                ->pluck('tipo')
                ->map(fn ($type) => $type === 'proc_exame' ? 'exame' : $type)
                ->filter()
                ->unique()
                ->implode(',');
            $patient['multi_team_filter'] = collect($patient['multidisciplinary'] ?? [])
                ->filter()
                ->keys()
                ->implode(',');
            $patient['convenio_short'] = collect(explode(' ', (string) ($patient['convenio'] ?? 'N/A')))
                ->filter(fn (string $part): bool => trim($part) !== '')
                ->first() ?? 'N/A';

            return $patient;
        }, $patients);
    }
}
