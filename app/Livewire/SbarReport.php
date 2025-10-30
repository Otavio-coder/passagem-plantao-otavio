<?php
namespace App\Livewire;

use App\Models\EMR\Core\{Sector, Hospital};
use App\Models\System\SystemConfiguration;
use App\Services\TasyService;
use Livewire\Component;
use Livewire\Attributes\Lazy;
use Illuminate\Support\Facades\Log;

#[Lazy]
class SbarReport extends Component
{
    public $loading = false;
    public $loadingMessage = '';
    public $errorMessage = null;
    public $lastRefresh = null;

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

    // Services
    protected TasyService $tasyService;

    protected $listeners = [
        'refreshData' => 'refreshData',
        'autoRefresh' => 'autoRefresh'
    ];

    public function boot(TasyService $tasyService)
    {
        $this->tasyService = $tasyService;
    }

    public function mount()
    {
        /*Log::info('SbarReport: mount() iniciado');*/
        $this->loading = true;
        $this->loadingMessage = 'Inicializando sistema SBAR...';

        try {
            $this->loadInitialData();
        } catch (\Exception $e) {
            Log::error('SbarReport mount error: ' . $e->getMessage());
            $this->errorMessage = "Erro durante inicialização: " . $e->getMessage();
        } finally {
            $this->loading = false;
            $this->loadingMessage = '';
        }
        /*Log::info('SbarReport: mount() finalizado');*/
    }

    protected function loadInitialData()
    {
        $allowedHospitals = SystemConfiguration::allowedHospitalCodes();
        $allowedSectors = SystemConfiguration::allowedSectorCodes();

        if (empty($allowedHospitals)) {
            $this->errorMessage = "Nenhum hospital configurado.";
            return;
        }

        $this->loadHospitals($allowedHospitals);

        if (empty($this->hospitals)) {
            $this->errorMessage = "Nenhum hospital disponível.";
            return;
        }

        $this->selectedHospital = $this->hospitals[0]['hospital_id'];
        $this->currentHospitalName = $this->hospitals[0]['hospital_name'];

        $this->loadSectorsForHospital($this->selectedHospital, $allowedSectors);

        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'];
            $this->loadPatients();
        } else {
            $this->errorMessage = "Nenhum setor válido encontrado para o hospital selecionado.";
            $this->patients = [];
        }
    }

    protected function loadHospitals($allowedHospitals)
    {
        try {
            $hospitals = Hospital::whereIn('nr_sequencia', $allowedHospitals)
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
        /*Log::info('SbarReport: loadPatients() iniciado', [
            'sector' => $this->selectedSector
        ]);*/

        if (!$this->selectedSector) {
            $this->patients = [];
            return;
        }

        $this->loading = true;
        $this->errorMessage = null;

        try {
            $startTime = microtime(true);

            // Busca dados brutos do service (já processados)
            $patientsData = $this->tasyService->getSectorPatientsForSbar($this->selectedSector);

            $fetchTime = microtime(true) - $startTime;
            /*Log::info('SbarReport: Dados buscados em ' . round($fetchTime, 2) . 's', [
                'count' => count($patientsData)
            ]);*/

            // ✅ Armazena mapa raw para detecção de mudanças
            $this->rawPatientsMap = collect($patientsData)
                ->keyBy(fn($p) => $p['nr_atendimento'] ?? ('empty-' . uniqid()))
                ->toArray();

            // ✅ Hashes para detecção de mudanças
            $this->rawPatientsHashes = array_map(fn($p) => md5(serialize($p)), $this->rawPatientsMap);

            /*Log::info('SbarReport: rawPatientsMap armazenado', [
                'count' => count($this->rawPatientsMap),
                'keys' => array_keys($this->rawPatientsMap)
            ]);*/

            // Aplica filtros e ordenação
            $this->patients = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));

            $this->lastRefresh = now()->format('H:i:s');
            $this->dispatch('sbar:patients-loaded', ['timestamp' => now()->timestamp]);

           /* Log::info('SbarReport: loadPatients() concluído', [
                'total_pacientes' => count($this->patients),
                'tempo_total' => round(microtime(true) - $startTime, 2) . 's'
            ]);*/
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

    protected function applyFiltersAndSort($data)
    {
        $startTime = microtime(true);
        $filtered = collect($data);
        $originalCount = $filtered->count();

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

        $afterMews = $filtered->count();

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

        $afterSurgery = $filtered->count();

        // Ordenação
        $filtered = $this->sortPatients($filtered);

        $result = $filtered->values()->toArray();

       /* Log::info('SbarReport: Filtros aplicados', [
            'original' => $originalCount,
            'after_mews' => $afterMews,
            'after_surgery' => $afterSurgery,
            'final' => count($result),
            'mews_filter' => $this->mewsFilter,
            'surgical_filter' => $this->surgicalFilter,
            'order_by' => $this->orderBy,
            'order_direction' => $this->orderDirection,
            'tempo' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ]);*/

        return $result;
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
       /* Log::info('SbarReport: changeHospital() chamado', ['hospital_id' => $hospitalId]);*/

        $this->loading = true;
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        $this->rawPatientsMap = [];
        $this->rawPatientsHashes = [];

        $hospital = collect($this->hospitals)->firstWhere('hospital_id', $hospitalId);
        $this->currentHospitalName = $hospital['hospital_name'] ?? 'Hospital';

        $allowedSectors = SystemConfiguration::allowedSectorCodes();
        $this->loadSectorsForHospital($hospitalId, $allowedSectors);

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
        /*Log::info('SbarReport: changeSector() chamado', ['sector_id' => $sectorId]);*/
        $this->selectedSector = $sectorId;

        $this->rawPatientsMap = [];
        $this->rawPatientsHashes = [];

        $this->loadPatients();
    }

    public function updatedMewsFilter($value)
    {
        /*Log::info('SbarReport: updatedMewsFilter() chamado', [
            'new_value' => $value,
            'raw_count' => count($this->rawPatientsMap)
        ]);*/
        $this->refilterFromRaw();
    }

    public function updatedSurgicalFilter($value)
    {
        /*Log::info('SbarReport: updatedSurgicalFilter() chamado', [
            'new_value' => $value,
            'raw_count' => count($this->rawPatientsMap)
        ]);*/
        $this->refilterFromRaw();
    }

    public function updatedOrderBy($value)
    {
       /* Log::info('SbarReport: updatedOrderBy() chamado', [
            'new_value' => $value,
            'raw_count' => count($this->rawPatientsMap)
        ]);*/
        $this->refilterFromRaw();
    }

    public function toggleOrderDirection()
    {
        /*Log::info('SbarReport: toggleOrderDirection() chamado', [
            'current' => $this->orderDirection,
            'raw_count' => count($this->rawPatientsMap)
        ]);*/
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->refilterFromRaw();
    }

    public function resetFilters()
    {
        /*Log::info('SbarReport: resetFilters() chamado', [
            'raw_count' => count($this->rawPatientsMap)
        ]);*/
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'bed';
        $this->orderDirection = 'asc';
        $this->refilterFromRaw();
    }

    protected function refilterFromRaw()
    {
       /* Log::info('SbarReport: refilterFromRaw() chamado', [
            'raw_patients_count' => count($this->rawPatientsMap),
            'has_data' => !empty($this->rawPatientsMap)
        ]);*/

        if (empty($this->rawPatientsMap)) {
/*            Log::warning('SbarReport: rawPatientsMap vazio, recarregando dados');*/
            $this->loadPatients();
            return;
        }

        $this->patients = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));
    }

    public function refreshData()
    {
        /*Log::info('SbarReport: refreshData() chamado');*/

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

        // Detecta mudanças
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

        // Atualiza dados alterados
        foreach ($changedIds as $id) {
            $this->rawPatientsMap[$id] = $newMap[$id];
            $this->rawPatientsHashes[$id] = $newHashes[$id];
        }

        // Remove pacientes que saíram
        foreach ($removedIds as $id) {
            unset($this->rawPatientsMap[$id], $this->rawPatientsHashes[$id]);
        }

        // Reaplica filtros
        $this->patients = $this->applyFiltersAndSort(array_values($this->rawPatientsMap));

        $this->lastRefresh = now()->format('H:i:s');
        $this->dispatch('sbar:patients-loaded', ['timestamp' => now()->timestamp]);
    }

    public function autoRefresh(): void
    {
        if ($this->selectedSector) {
            $this->tasyService->clearSectorCache($this->selectedSector);
        }
        $this->loadPatients();
        $this->dispatch('sbar:patients-loaded', ['timestamp' => now()->timestamp]);
    }

    public function placeholder()
    {
        return view('livewire.sbar-report-placeholder');
    }

    public function render()
    {
        $data = [
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
        ];

        return view('livewire.sbar-report', $data);
    }
}
