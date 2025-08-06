<?php

namespace App\Livewire;

use App\Models\EMR\Patient;
use App\Models\EMR\BedUnit;
use App\Models\EMR\Sector;
use App\Models\EMR\Hospital;
use App\Models\System\SystemConfiguration;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SbarReport extends Component
{
    public $loading = true;
    public $loadingMessage = 'Carregando dados...';
    public $errorMessage = null;
    public $patients = [];
    public $sectors = [];
    public $hospitals = [];
    public $beds = [];
    public $selectedHospital = null;
    public $selectedSector = null;
    public $currentHospitalName = 'Carregando...';

    // Filtros
    public $mewsFilter = 'all';
    public $surgicalFilter = 'all';
    public $orderBy = 'leito';
    public $orderDirection = 'asc';

    public function mount()
    {
        try {
            $this->loading = true;
            $this->loadingMessage = 'Inicializando sistema...';
            
            // Cache configuration data aggressively
            $configData = Cache::remember('sbar_config_data', 3600, function() {
                return [
                    'hospitals' => SystemConfiguration::allowedHospitalCodes(),
                    'sectors' => SystemConfiguration::allowedSectorCodes(),
                    'beds' => SystemConfiguration::allowedBedCodes()
                ];
            });

            if (empty($configData['hospitals'])) {
                $this->errorMessage = "Nenhum hospital permitido. Solicite ao gestor a configuração pelo painel de administração.";
                $this->loading = false;
                return;
            }

            // Load structural data once
            $this->loadStructuralData($configData);
            
            // Set initial selections
            $this->selectedHospital = $configData['hospitals'][0];
            $this->setInitialSector();
            
            // Load patient data immediately after setup
            if ($this->selectedSector) {
                $this->loadPatientDataOptimized();
            } else {
                $this->loading = false;
                $this->errorMessage = "Nenhum setor válido encontrado para carregar dados.";
            }
            
        } catch (\Exception $e) {
            $this->loading = false;
            $this->errorMessage = "Erro durante a inicialização: " . $e->getMessage();
            Log::error("SbarReport mount error: " . $e->getMessage());
        }
    }

    private function loadStructuralData($configData)
    {
        $structuralCacheKey = 'sbar_structural_data_' . md5(serialize($configData));
        
        $structural = Cache::remember($structuralCacheKey, 1800, function() use ($configData) {
            $hospitalModel = new Hospital();
            $hospitalsAll = $hospitalModel->getAllHospitalsWithSectors();
            $hospitals = $hospitalsAll
                ->filter(fn($h) => in_array($h->hospital_id, $configData['hospitals']))
                ->values()
                ->toArray();

            $sectorModel = new Sector();
            $sectorsAll = collect($sectorModel->getAllowedSectors());
            $sectors = $sectorsAll
                ->filter(fn($s) => in_array($s->cd_setor_atendimento, $configData['sectors']))
                ->map(fn($s) => (array)$s)
                ->values()
                ->toArray();

            $bedModel = new BedUnit();
            $beds = collect($sectors)
                ->flatMap(fn($sector) => $bedModel->getBedsBySector($sector['cd_setor_atendimento']))
                ->filter(fn($bed) => in_array($bed->cd_unidade_basica . '|' . $bed->cd_setor_atendimento, $configData['beds']))
                ->values()
                ->toArray();

            return compact('hospitals', 'sectors', 'beds');
        });

        $this->hospitals = $structural['hospitals'];
        $this->sectors = $structural['sectors'];
        $this->beds = $structural['beds'];
    }

    private function setInitialSector()
    {
        $sectorsOfHospital = collect($this->sectors)->filter(fn($sector) =>
            $sector['hospital_id'] == $this->selectedHospital
        )->values();
        
        $firstAllowed = $sectorsOfHospital->first();
        $this->selectedSector = $firstAllowed['cd_setor_atendimento'] ?? (
            !empty($this->sectors) ? $this->sectors[0]['cd_setor_atendimento'] : null
        );
    }

    public function render()
    {
        return view('livewire.sbar-report', [
            'patients' => $this->patients,
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'beds' => $this->beds,
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
        ]);
    }

    public function loadPatientDataOptimized()
    {
        try {
            $this->loading = true;
            $this->loadingMessage = "Carregando pacientes...";
            
            // Generate cache key based on all relevant parameters
            $cacheKey = $this->generateCacheKey();
            
            $data = Cache::remember($cacheKey, 300, function() {
                return $this->fetchPatientData();
            });
            
            $this->patients = $data['patients'];
            $this->currentHospitalName = $data['hospitalName'];
            
        } catch (\Exception $e) {
            $this->patients = [];
            $this->errorMessage = "Erro ao carregar dados: " . $e->getMessage();
            Log::error("Error loading patient data: " . $e->getMessage());
        } finally {
            $this->loading = false;
            $this->loadingMessage = '';
        }
    }

    private function generateCacheKey()
    {
        return 'sbar_patients_' . md5(serialize([
            'sector' => $this->selectedSector,
            'mews' => $this->mewsFilter,
            'surgical' => $this->surgicalFilter,
            'order' => $this->orderBy,
            'direction' => $this->orderDirection,
            'date' => date('Y-m-d'), 
        ]));
    }

    private function fetchPatientData()
    {
        // Load hospital name
        $sectorModel = new Sector();
        $sector = collect($sectorModel->getAllowedSectors())
            ->first(fn($s) => $s->cd_setor_atendimento == $this->selectedSector);
        
        $hospitalModel = new Hospital();
        $hospital = $hospitalModel->getAllHospitalsWithSectors()
            ->first(fn($h) => $h->hospital_id == ($sector->hospital_id ?? null));
        
        $hospitalName = $hospital->hospital_name ?? 'Hospital Não Identificado';

        // Load and filter patients
        $patientModel = new Patient();
        $allPatients = $patientModel->getAllPatientsInSector($this->selectedSector);
        $filtered = $this->applyFiltersAndSorting($allPatients);

        return [
            'patients' => $filtered->toArray(),
            'hospitalName' => $hospitalName
        ];
    }

    private function applyFiltersAndSorting($patients)
    {
        $filtered = collect($patients);

        // Apply MEWS filter
        if ($this->mewsFilter !== 'all') {
            $filtered = $filtered->filter(function($p) {
                if (!$p->has_patient) return false;
                $score = $p->mews_score;
                switch ($this->mewsFilter) {
                    case 'critical': return $score !== null && $score >= 5;
                    case 'warning': return $score !== null && $score >= 3 && $score < 5;
                    case 'normal': return $score === null || $score < 3;
                    default: return true;
                }
            });
        }

        // Apply surgical filter
        if ($this->surgicalFilter === 'with_surgery') {
            $filtered = $filtered->filter(fn($p) => $p->has_patient && $p->has_surgery);
        }

        // Apply sorting
        return $filtered->sort(function($a, $b) {
            $dir = $this->orderDirection === 'asc' ? 1 : -1;
            switch ($this->orderBy) {
                case 'leito':
                    return $dir * strcmp((string)($a->cd_unidade_basica ?? ''), (string)($b->cd_unidade_basica ?? ''));
                case 'mews':
                    return $dir * ((int)($b->mews_score ?? 0) <=> (int)($a->mews_score ?? 0));
                case 'name':
                    return $dir * strcmp((string)($a->nm_pessoa_fisica ?? ''), (string)($b->nm_pessoa_fisica ?? ''));
                case 'prontuario':
                    return $dir * strcmp((string)($a->nr_prontuario ?? ''), (string)($b->nr_prontuario ?? ''));
                case 'internment':
                    return $dir * ((int)($a->internment_days ?? 0) <=> (int)($b->internment_days ?? 0));
                case 'age':
                    return $dir * ((int)($a->age ?? 0) <=> (int)($b->age ?? 0));
                default:
                    return 0;
            }
        })->values();
    }

    public function changeHospital($hospitalId)
    {
        $this->loading = true;
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;
        
        // Set new sector
        $this->setInitialSector();
        
        // Clear current data and reload
        $this->patients = [];
        $this->currentHospitalName = '';
        
        if ($this->selectedSector) {
            $this->loadPatientDataOptimized();
        } else {
            $this->loading = false;
        }
    }

    public function changeSelector($sectorId)
    {
        $this->loading = true;
        $this->selectedSector = $sectorId;
        $this->patients = [];
        $this->currentHospitalName = '';
        $this->loadPatientDataOptimized();
    }

    public function applyMewsFilter($filter)
    {
        $this->loading = true;
        $this->mewsFilter = $filter;
        $this->loadPatientDataOptimized();
    }

    public function applySurgicalFilter($filter)
    {
        $this->loading = true;
        $this->surgicalFilter = $filter;
        $this->loadPatientDataOptimized();
    }

    public function applyOrderBy($field)
    {
        $this->loading = true;
        $this->orderBy = $field;
        $this->loadPatientDataOptimized();
    }

   public function toggleOrderDirection()
    {
        $this->loading = true;
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->loadPatientDataOptimized();
    }

    public function refreshData()
    {
        $this->loading = true;
        $this->loadingMessage = "Atualizando dados...";
        $this->errorMessage = null;
        
        // Clear relevant caches
        $cacheKey = $this->generateCacheKey();
        Cache::forget($cacheKey);
        Cache::forget("hospital_name_{$this->selectedSector}");
        
        $this->patients = [];
        $this->currentHospitalName = '';
        $this->loadPatientDataOptimized();
    }

    public function resetFilters()
    {
        $this->loading = true;
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'leito';
        $this->orderDirection = 'asc';
        $this->patients = [];
        
        $this->loadPatientDataOptimized();
        session()->flash('message', 'Filtros resetados com sucesso!');
    }
}