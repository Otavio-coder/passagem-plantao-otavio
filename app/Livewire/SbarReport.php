<?php
namespace App\Livewire;

use App\Models\EMR\Core\{Sector, Hospital};
use App\Models\System\UserSectorPreference;
use App\Services\TasyService;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Lazy]
class SbarReport extends Component
{
    // Primitive state — safe in wire:snapshot
    public $errorMessage = null;
    public $lastRefresh  = null;

    // User selection state
    public $selectedHospital = null;
    public $selectedSector   = null;

    // Sector onboarding
    public bool  $showSectorOnboarding = false;
    public array $selectedSectors      = [];

    // Services
    protected TasyService $tasyService;

    protected $listeners = [
        'refreshData'           => 'refreshData',
        'sectorOnboardingSaved' => 'onSectorOnboardingSaved',
        'handover-updated'      => 'onHandoverUpdated',
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
        $user             = Auth::user();
        $userHospitalCodes = $user->sectorPreferences()->pluck('hospital_code')->unique()->toArray();

        if (empty($userHospitalCodes)) {
            return [];
        }

        try {
            return Hospital::whereIn('nr_sequencia', $userHospitalCodes)
                ->where('ie_situacao', 'A')
                ->select(['nr_sequencia as hospital_id', 'ds_agrupamento as hospital_name'])
                ->get()
                ->map(fn ($h) => [
                    'hospital_id'   => $h->hospital_id,
                    'hospital_name' => $h->hospital_name,
                ])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading hospitals: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sectors for the currently selected hospital. Persisted; cleared on
     * hospital change via unset($this->sectors).
     */
    #[Computed(persist: true)]
    public function sectors(): array
    {
        if (!$this->selectedHospital) {
            return [];
        }

        $user            = Auth::user();
        $userSectorCodes = $user->sectorPreferences()
            ->where('hospital_code', $this->selectedHospital)
            ->pluck('sector_code')
            ->toArray();

        try {
            return Sector::where('nr_seq_agrupamento', $this->selectedHospital)
                ->whereIn('cd_setor_atendimento', $userSectorCodes)
                ->allowed()
                ->get()
                ->map(fn ($s) => [
                    'cd_setor_atendimento' => $s->cd_setor_atendimento,
                    'ds_setor_atendimento' => $s->ds_setor_atendimento,
                ])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading sectors: ' . $e->getMessage());
            return [];
        }
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
        if (!$this->selectedSector) {
            return [];
        }

        try {
            $patients = $this->tasyService->getSectorPatientsForSbar($this->selectedSector);
            return $this->injectHandoverStatus($patients);
        } catch (\Exception $e) {
            Log::error('Error loading patients: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errorMessage = "Erro ao carregar pacientes: " . $e->getMessage();
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
        if (!$this->showSectorOnboarding) {
            return [];
        }

        try {
            $allowedHospitalIds = [1, 2, 3, 4, 5, 6, 7, 8, 10, 18, 25];

            $sectors = Sector::whereIn('nr_seq_agrupamento', $allowedHospitalIds)
                ->where('ie_situacao', 'A')
                ->select(['cd_setor_atendimento', 'ds_setor_atendimento', 'nr_seq_agrupamento'])
                ->get();

            $hospitalIds   = $sectors->pluck('nr_seq_agrupamento')->unique()->filter();
            $hospitalNames = Hospital::whereIn('nr_sequencia', $hospitalIds)
                ->pluck('ds_agrupamento', 'nr_sequencia');

            return $sectors->map(fn ($s) => [
                'sector_code'   => $s->cd_setor_atendimento,
                'sector_name'   => $s->ds_setor_atendimento,
                'hospital_code' => $s->nr_seq_agrupamento,
                'hospital_name' => $hospitalNames->get($s->nr_seq_agrupamento, 'Hospital'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading available sectors for onboarding: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Display name of the currently selected hospital.
     */
    #[Computed]
    public function currentHospitalName(): string
    {
        if (!$this->selectedHospital) {
            return 'Carregando...';
        }

        $hospital = collect($this->hospitals)->firstWhere('hospital_id', (int) $this->selectedHospital);
        return $hospital['hospital_name'] ?? 'Hospital';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────────────────

    public function mount()
    {
        $user = Auth::user();

        if (!$user->hasConfiguredSectors()) {
            $this->dispatch('checkUserSectors');
            $this->errorMessage        = "Você precisa configurar seus setores de acesso antes de usar o SBAR.";
            $this->showSectorOnboarding = true;
            return;
        }

        try {
            $hospitals = $this->hospitals;

            if (empty($hospitals)) {
                $this->errorMessage = "Nenhum hospital disponível.";
                return;
            }

            if (!$this->selectedHospital) {
                $this->selectedHospital = $hospitals[0]['hospital_id'];
            }

            $sectors = $this->sectors;

            if (empty($sectors)) {
                $this->errorMessage = "Nenhum setor configurado para este hospital. Atualize suas preferências de setor.";
                return;
            }

            if (!$this->selectedSector) {
                $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            }

            $this->lastRefresh = now()->format('H:i:s');
            $this->auditSectorView('mount');
        } catch (\Exception $e) {
            Log::error('SBAR mount error: ' . $e->getMessage());
            $this->errorMessage = "Erro durante inicialização: " . $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────────────────

    public function changeHospital($hospitalId)
    {
        $this->selectedHospital = $hospitalId;
        $this->selectedSector   = null;

        // Invalidate cached computed values for the new hospital
        unset($this->sectors);
        unset($this->patients);

        $sectors = $this->sectors;

        if (!empty($sectors)) {
            $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            $this->lastRefresh    = now()->format('H:i:s');
            $this->auditSectorView('hospital_change');
        } else {
            $this->errorMessage = "Nenhum setor configurado para este hospital.";
        }
    }

    public function changeSector($sectorId)
    {
        $user              = Auth::user();
        $allowedSectorCodes = $user->sectorPreferences()->pluck('sector_code')->toArray();

        if (!in_array((string) $sectorId, array_map('strval', $allowedSectorCodes))) {
            Log::warning('Tentativa de acesso a setor não autorizado', [
                'user_id'   => $user->id,
                'sector_id' => $sectorId,
            ]);
            $this->errorMessage = "Acesso negado: setor não autorizado.";
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
            Log::error('Error in refreshData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errorMessage = "Erro ao atualizar: " . $e->getMessage();
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
                    'user_id'       => $user->id,
                    'sector_code'   => $sectorCode,
                    'sector_name'   => $meta['sector_name'] ?? null,
                    'hospital_code' => $meta['hospital_code'] ?? null,
                    'hospital_name' => $meta['hospital_name'] ?? null,
                ]);
            }

            $newSectors = collect($this->selectedSectors)
                ->mapWithKeys(fn ($code) => [$code => $sectorsMap->get($code)['sector_name'] ?? $code])
                ->toArray();

            Log::channel('audit')->info('preferences.updated', [
                'source'           => 'onboarding',
                'user_id'          => $user->id,
                'user'             => $user->name,
                'previous_sectors' => $previousSectors,
                'new_sectors'      => $newSectors,
                'ip'               => request()->ip(),
            ]);

            $this->showSectorOnboarding = false;
            $this->selectedSectors      = [];
            $this->selectedHospital     = null;
            $this->selectedSector       = null;

            // Invalidate all cached computed values
            unset($this->hospitals);
            unset($this->sectors);
            unset($this->patients);

            // Re-initialize with new preferences
            $this->mount();
        } catch (\Exception $e) {
            Log::error('Error saving sector preferences: ' . $e->getMessage());
        }
    }

    public function onSectorOnboardingSaved(): void
    {
        $this->showSectorOnboarding = false;
        $this->selectedHospital     = null;
        $this->selectedSector       = null;

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

    public function placeholder()
    {
        return view('sbar.report.placeholder');
    }

    public function render()
    {
        return view('sbar.report.index', [
            'hospitals'           => $this->hospitals,
            'sectors'             => $this->sectors,
            'patients'            => $this->patients,
            'errorMessage'        => $this->errorMessage,
            'selectedHospital'    => $this->selectedHospital,
            'selectedSector'      => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'lastRefresh'         => $this->lastRefresh,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function auditSectorView(string $action): void
    {
        if (!$this->selectedSector) {
            return;
        }

        $sectorName = collect($this->sectors)
            ->firstWhere('cd_setor_atendimento', $this->selectedSector)['ds_setor_atendimento'] ?? $this->selectedSector;

        Log::channel('audit')->info('sbar.viewed', [
            'action'      => $action,
            'user_id'     => Auth::id(),
            'user'        => Auth::user()->name ?? 'unknown',
            'sector_id'   => $this->selectedSector,
            'sector_name' => $sectorName,
            'hospital'    => $this->currentHospitalName,
            'ip'          => request()->ip(),
        ]);
    }

    /**
     * Injects handover_done / handover_last_time / handover_msg_count into
     * each patient via a single batch MySQL query.
     */
    private function injectHandoverStatus(array $patients): array
    {
        $nrs = collect($patients)
            ->filter(fn ($p) => !empty($p['nr_atendimento']) && ($p['has_patient'] ?? false))
            ->pluck('nr_atendimento')
            ->values()
            ->toArray();

        if (empty($nrs)) {
            return $patients;
        }

        [$shiftStart, $shiftEnd] = \App\Services\ShiftService::getShiftWindow();

        $rows = \Illuminate\Support\Facades\DB::table('chat_messages')
            ->whereIn('nr_atendimento', $nrs)
            ->whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->select([
                'nr_atendimento',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as msg_count'),
                \Illuminate\Support\Facades\DB::raw('MAX(created_at) as last_msg'),
            ])
            ->groupBy('nr_atendimento')
            ->get()
            ->keyBy('nr_atendimento');

        return array_map(function ($patient) use ($rows) {
            if (!($patient['has_patient'] ?? false)) {
                return $patient;
            }

            $nr  = $patient['nr_atendimento'] ?? null;
            $row = $nr ? $rows->get($nr) : null;

            $patient['handover_done']      = $row !== null;
            $patient['handover_last_time'] = $row
                ? \Carbon\Carbon::parse($row->last_msg)->format('H:i')
                : null;
            $patient['handover_msg_count'] = $row ? (int) $row->msg_count : 0;

            return $patient;
        }, $patients);
    }
}
