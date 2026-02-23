<?php
namespace App\Livewire;

use App\Models\EMR\Core\{Sector, Hospital};
use App\Models\System\UserSectorPreference;
use App\Services\TasyService;
use Livewire\Component;
use Livewire\Attributes\Lazy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Lazy]
class SbarReport extends Component
{
    public $loading = false;
    public $loadingMessage = '';
    public $errorMessage = null;
    public $lastRefresh = null;

    // Paginação lazy loading
    public $perPage = 20;
    public $hasMore = false;

    // Dados principais
    public $hospitals = [];
    public $sectors = [];
    public $patients = [];

    public array $rawPatientsMap = [];
    public array $rawPatientsHashes = [];

    public $selectedHospital = null;
    public $selectedSector = null;
    public $currentHospitalName = 'Carregando...';

    // Filtros
    public $mewsFilter = 'all';
    public $surgicalFilter = 'all';
    public $orderBy = 'bed';
    public $orderDirection = 'asc';

    // Sector onboarding
    public bool $showSectorOnboarding = false;
    public array $availableSectors    = [];
    public array $selectedSectors     = [];

    // Services
    protected TasyService $tasyService;

    protected $listeners = [
        'refreshData'           => 'refreshData',
        'sectorOnboardingSaved' => 'onSectorOnboardingSaved',
    ];

    public function boot(TasyService $tasyService)
    {
        $this->tasyService = $tasyService;
    }

    public function mount()
    {
        $user = Auth::user();
        
        // Verifica se o usuário tem setores configurados
        if (!$user->hasConfiguredSectors()) {
            // Dispara evento para mostrar o modal
            $this->dispatch('checkUserSectors');
            $this->errorMessage = "Você precisa configurar seus setores de acesso antes de usar o SBAR.";
            return;
        }

        $this->loading = true;
        $this->loadingMessage = 'Inicializando sistema SBAR...';

        try {
            $this->loadInitialData();
        } catch (\Exception $e) {
            Log::error('SBAR mount error: ' . $e->getMessage());
            $this->errorMessage = "Erro durante inicialização: " . $e->getMessage();
        } finally {
            $this->loading = false;
            $this->loadingMessage = '';
        }
    }

    protected function loadInitialData()
    {
        $user = Auth::user();

        // If user has no configured sectors, show onboarding
        if (!$user->hasConfiguredSectors()) {
            $this->showSectorOnboarding = true;
            $this->loadAvailableSectors();
            $this->loading = false;
            return;
        }

        // Load hospitals based on user's preferred sectors
        $userSectorCodes = $user->sectorPreferences()->pluck('sector_code')->toArray();
        $userHospitalCodes = $user->sectorPreferences()->pluck('hospital_code')->unique()->toArray();
        
        if (empty($userHospitalCodes)) {
            $this->errorMessage = "Nenhum hospital configurado.";
            return;
        }

        $this->loadHospitals($userHospitalCodes);

        if (empty($this->hospitals)) {
            $this->errorMessage = "Nenhum hospital disponível.";
            return;
        }

        $this->selectedHospital    = $this->hospitals[0]['hospital_id'];
        $this->currentHospitalName = $this->hospitals[0]['hospital_name'];

        // Load sectors for selected hospital
        $this->loadSectorsForHospital($this->selectedHospital, $userSectorCodes);

        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'];
            $this->loadPatients();
        } else {
            $this->errorMessage = "Nenhum setor configurado para este hospital. Atualize suas preferências de setor.";
            $this->patients = [];
        }
    }

    protected function loadAvailableSectors(): void
    {
        try {
            // Load all sectors from allowed hospitals based on controller config
            $allowedHospitalIds = [1,2,3,4,5,6,7,8,10,18,25];
            
            $sectors = Sector::whereIn('nr_seq_agrupamento', $allowedHospitalIds)
                ->where('ie_situacao', 'A')
                ->select(['cd_setor_atendimento', 'ds_setor_atendimento', 'nr_seq_agrupamento'])
                ->get();

            // Build hospital name map to avoid N+1
            $hospitalIds  = $sectors->pluck('nr_seq_agrupamento')->unique()->filter();
            $hospitalNames = Hospital::whereIn('nr_sequencia', $hospitalIds)
                ->pluck('ds_agrupamento', 'nr_sequencia');

            $this->availableSectors = $sectors->map(fn ($s) => [
                'sector_code'   => $s->cd_setor_atendimento,
                'sector_name'   => $s->ds_setor_atendimento,
                'hospital_code' => $s->nr_seq_agrupamento,
                'hospital_name' => $hospitalNames->get($s->nr_seq_agrupamento, 'Hospital'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading available sectors for onboarding: ' . $e->getMessage());
            $this->availableSectors = [];
        }
    }

    public function saveSectorPreferences(): void
    {
        $user = Auth::user();

        if (empty($this->selectedSectors)) {
            return;
        }

        try {
            // Remove existing preferences
            UserSectorPreference::where('user_id', $user->id)->delete();

            // Build available sectors map for enrichment
            $sectorsMap = collect($this->availableSectors)->keyBy('sector_code');

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

            $this->showSectorOnboarding = false;
            $this->selectedSectors = [];
            $this->loading = true;
            $this->loadInitialData();

        } catch (\Exception $e) {
            Log::error('Error saving sector preferences: ' . $e->getMessage());
        }
    }

    public function openSectorOnboarding(): void
    {
        $this->loadAvailableSectors();

        // Pre-select current user sectors
        $this->selectedSectors = Auth::user()
            ->sectorPreferences()
            ->pluck('sector_code')
            ->toArray();

        $this->showSectorOnboarding = true;
    }

    public function onSectorOnboardingSaved(): void
    {
        $this->showSectorOnboarding = false;
        $this->loading = true;
        $this->loadInitialData();
    }

    protected function loadHospitals($hospitalCodes)
    {
        try {
            $hospitals = Hospital::whereIn('nr_sequencia', $hospitalCodes)
                ->where('ie_situacao', 'A')
                ->select(['nr_sequencia as hospital_id', 'ds_agrupamento as hospital_name'])
                ->get();

            $this->hospitals = $hospitals->map(fn($h) => [
                'hospital_id' => $h->hospital_id,
                'hospital_name' => $h->hospital_name
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading hospitals: ' . $e->getMessage());
            $this->hospitals = [];
        }
    }

    protected function loadSectorsForHospital($hospitalId, $allowedSectors)
    {
        try {
            $sectors = Sector::where('nr_seq_agrupamento', $hospitalId)
                ->whereIn('cd_setor_atendimento', $allowedSectors)
                ->allowed()
                ->get();

            $this->sectors = $sectors->map(fn($s) => [
                'cd_setor_atendimento' => $s->cd_setor_atendimento,
                'ds_setor_atendimento' => $s->ds_setor_atendimento
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading sectors: ' . $e->getMessage());
            $this->sectors = [];
        }
    }

    public function loadPatients()
    {
        if (!$this->selectedSector) {
            $this->patients = [];
            return;
        }

        $this->loading = true;
        $this->errorMessage = null;

        try {
            // Busca dados do service
            $patientsData = $this->tasyService->getSectorPatientsForSbar($this->selectedSector);

            // Monta mapa raw
            $this->rawPatientsMap = collect($patientsData)
                ->keyBy(fn($p) => $p['nr_atendimento'] ?? ('empty-' . uniqid()))
                ->toArray();

            $this->rawPatientsHashes = array_map(fn($p) => md5(serialize($p)), $this->rawPatientsMap);

            // Aplica filtros e paginação
            $allFiltered = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));
            $this->patients = array_slice($allFiltered, 0, $this->perPage);
            $this->hasMore = count($allFiltered) > $this->perPage;

            $this->lastRefresh = now()->format('H:i:s');
            $this->dispatch('sbar:patients-loaded', ['timestamp' => now()->timestamp]);

        } catch (\Exception $e) {
            Log::error('Error loading patients: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->errorMessage = "Erro ao carregar pacientes: " . $e->getMessage();
            $this->patients = [];
        } finally {
            $this->loading = false;
        }
    }

    public function loadMore()
    {
        try {
            $allFiltered = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));

            $currentCount = count($this->patients);
            $nextBatch = array_slice($allFiltered, $currentCount, $this->perPage);

            $this->patients = array_merge($this->patients, $nextBatch);
            $this->hasMore = count($this->patients) < count($allFiltered);
        } catch (\Exception $e) {
            Log::error('Error loading more patients: ' . $e->getMessage());
        }
    }

    protected function applyFiltersAndSort($data)
    {
        $filtered = collect($data);

        // Filtro MEWS
        if ($this->mewsFilter !== 'all') {
            $filtered = $filtered->filter(function($patient) {
                if (!($patient['has_patient'] ?? false)) return false;

                $score = $patient['mews_score'] ?? $patient['pews_score'] ?? null;

                return match($this->mewsFilter) {
                    'critical' => $score !== null && $score >= 5,
                    'warning' => $score !== null && $score >= 3 && $score <= 4,
                    'normal' => $score === null || $score <= 2,
                    default => true
                };
            });
        }

        // Filtro cirúrgico
        if ($this->surgicalFilter === 'with_surgery') {
            $filtered = $filtered->filter(fn($patient) =>
                ($patient['has_patient'] ?? false) && ($patient['has_surgery'] ?? false)
            );
        } elseif ($this->surgicalFilter === 'without_surgery') {
            $filtered = $filtered->filter(fn($patient) =>
                ($patient['has_patient'] ?? false) && !($patient['has_surgery'] ?? false)
            );
        }

        // Ordenação
        $filtered = $this->sortPatients($filtered);

        return $filtered->values()->toArray();
    }

    protected function sortPatients($collection)
    {
        $descending = $this->orderDirection === 'desc';

        if ($this->orderBy === 'bed') {
            return $collection->sortBy(function($p) {
                return sprintf('%s-%03d',
                    $p['cd_unidade_basica'] ?? '',
                    $p['bed_sequence'] ?? 0
                );
            }, SORT_STRING, $descending);
        }

        return $collection->sortBy(function($p) {
            if (!($p['has_patient'] ?? false)) {
                return PHP_INT_MAX;
            }

            return match($this->orderBy) {
                'mews' => $p['mews_score'] ?? $p['pews_score'] ?? -1,
                'name' => strtolower($p['nm_pessoa_fisica'] ?? 'zzz'),
                'prontuario' => $p['nr_prontuario'] ?? 'zzz',
                'internment' => $p['internment_days'] ?? -1,
                'age' => $p['age'] ?? 0,
                default => sprintf('%s-%03d', $p['cd_unidade_basica'] ?? '', $p['bed_sequence'] ?? 0)
            };
        }, $this->orderBy === 'name' || $this->orderBy === 'prontuario' ? SORT_STRING : SORT_NUMERIC, $descending);
    }

    public function changeHospital($hospitalId)
    {
        $this->loading = true;
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        $this->rawPatientsMap = [];
        $this->rawPatientsHashes = [];

        $hospital = collect($this->hospitals)->firstWhere('hospital_id', $hospitalId);
        $this->currentHospitalName = $hospital['hospital_name'] ?? 'Hospital';

        // Get user's preferred sectors for this hospital
        $user = Auth::user();
        $userSectorCodes = $user->sectorPreferences()
            ->where('hospital_code', $hospitalId)
            ->pluck('sector_code')
            ->toArray();
        
        $this->loadSectorsForHospital($hospitalId, $userSectorCodes);

        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'];
            $this->loadPatients();
        } else {
            $this->patients = [];
            $this->loading = false;
        }
    }

    public function changeSector($sectorId)
    {
        $this->selectedSector = $sectorId;
        $this->rawPatientsMap = [];
        $this->rawPatientsHashes = [];
        $this->loadPatients();
    }

    public function updatedMewsFilter($value)
    {
        $this->refilterFromRaw();
    }

    public function updatedSurgicalFilter($value)
    {
        $this->refilterFromRaw();
    }

    public function updatedOrderBy($value)
    {
        $this->refilterFromRaw();
    }

    public function toggleOrderDirection()
    {
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->refilterFromRaw();
    }

    public function resetFilters()
    {
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'bed';
        $this->orderDirection = 'asc';
        $this->refilterFromRaw();
    }

    protected function refilterFromRaw()
    {
        if (empty($this->rawPatientsMap)) {
            $this->loadPatients();
            return;
        }

        $allFiltered = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));
        $this->patients = array_slice($allFiltered, 0, $this->perPage);
        $this->hasMore = count($allFiltered) > $this->perPage;
    }

    public function refreshData()
    {
        try {
            if ($this->selectedSector) {
                $this->tasyService->clearSectorCache($this->selectedSector);
            }
            $this->loadPatients();
        } catch (\Exception $e) {
            Log::error('Error in refreshData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->errorMessage = "Erro ao atualizar: " . $e->getMessage();
            $this->loading = false;
        }
    }

    public function updateSectorPatients()
    {
        if (!$this->selectedSector) return;

        $newData = $this->tasyService->getSectorPatientsForSbar($this->selectedSector);

        $newMap = collect($newData)
            ->keyBy(fn($p) => $p['nr_atendimento'] ?? ('empty-' . uniqid()))
            ->toArray();

        $newHashes = array_map(fn($p) => md5(serialize($p)), $newMap);

        $changedIds = [];
        foreach ($newHashes as $id => $hash) {
            if (!isset($this->rawPatientsHashes[$id]) || $this->rawPatientsHashes[$id] !== $hash) {
                $changedIds[] = $id;
            }
        }

        $removedIds = array_diff(array_keys($this->rawPatientsMap), array_keys($newMap));

        if (empty($changedIds) && empty($removedIds)) {
            $this->lastRefresh = now()->format('H:i:s');
            return;
        }

        foreach ($changedIds as $id) {
            $this->rawPatientsMap[$id] = $newMap[$id];
            $this->rawPatientsHashes[$id] = $newHashes[$id];
        }

        foreach ($removedIds as $id) {
            unset($this->rawPatientsMap[$id], $this->rawPatientsHashes[$id]);
        }

        $this->patients = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));

        $this->lastRefresh = now()->format('H:i:s');
        $this->dispatch('sbar:patients-loaded', ['timestamp' => now()->timestamp]);
    }



    public function placeholder()
    {
        return view('livewire.sbar-report-placeholder');
    }

    public function render()
    {
        return view('livewire.sbar-report', [
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'patients' => $this->patients,
            'errorMessage' => $this->errorMessage,
            'loadingMessage' => $this->loadingMessage,
            'mewsFilter' => $this->mewsFilter,
            'surgicalFilter' => $this->surgicalFilter,
            'orderBy' => $this->orderBy,
            'orderDirection' => $this->orderDirection,
            'selectedHospital' => $this->selectedHospital,
            'selectedSector' => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'loading' => $this->loading,
            'lastRefresh' => $this->lastRefresh,
            'hasMore' => $this->hasMore,
        ]);
    }
}
