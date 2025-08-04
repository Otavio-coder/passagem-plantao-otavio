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
use Illuminate\Support\Facades\DB;

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
            // Carregue uma vez e reutilize
            $allowedHospitals = \App\Models\System\SystemConfiguration::allowedHospitalCodes();
            $allowedSectors   = \App\Models\System\SystemConfiguration::allowedSectorCodes();
            $allowedBedUnits  = \App\Models\System\SystemConfiguration::allowedBedCodes();

            if (empty($allowedHospitals)) {
                $this->errorMessage = "Nenhum hospital permitido. Solicite ao gestor a configuração pelo painel de administração.";
                $this->loading = false;
                return;
            }

            $hospitalModel = new \App\Models\EMR\Hospital();
            $hospitalsAll = $hospitalModel->getAllHospitalsWithSectors();
            $this->hospitals = $hospitalsAll
                ->filter(fn($h) => in_array($h->hospital_id, $allowedHospitals))
                ->values()
                ->toArray();

            $sectorModel = new \App\Models\EMR\Sector();
            $sectorsAll = collect($sectorModel->getAllowedSectors());
            // Use $allowedSectors já carregado
            $this->sectors = $sectorsAll
                ->filter(fn($s) => in_array($s->cd_setor_atendimento, $allowedSectors))
                ->map(fn($s) => (array)$s)
                ->values()
                ->toArray();

            $bedModel = new \App\Models\EMR\BedUnit();
            // Use $allowedBedUnits já carregado
            $this->beds = collect($this->sectors)
                ->flatMap(fn($sector) => $bedModel->getBedsBySector($sector['cd_setor_atendimento']))
                ->filter(fn($bed) => in_array($bed->cd_unidade_basica . '|' . $bed->cd_setor_atendimento, $allowedBedUnits))
                ->values()
                ->toArray();

            $this->selectedHospital = $allowedHospitals[0];
            $sectorsOfHospital = collect($this->sectors)->filter(fn($sector) =>
                $sector['hospital_id'] == $this->selectedHospital
            )->values();
            $firstAllowed = $sectorsOfHospital->first();
            $this->selectedSector = $firstAllowed['cd_setor_atendimento'] ?? null;
            if (empty($this->selectedSector) && !empty($this->sectors)) {
                $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
            }

            // FIXED: Automatically load patient data after mount
            if ($this->selectedSector) {
                $this->loadPatientData();
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

    public function render()
    {
        return view('livewire.sbar-report', [
            'patients'         => $this->patients,
            'hospitals'        => $this->hospitals,
            'sectors'          => $this->sectors,
            'beds'             => $this->beds,
            'errorMessage'     => $this->errorMessage,
            'loadingMessage'   => $this->loadingMessage,
            'mewsFilter'       => $this->mewsFilter,
            'surgicalFilter'   => $this->surgicalFilter,
            'orderBy'          => $this->orderBy,
            'orderDirection'   => $this->orderDirection,
            'selectedHospital' => $this->selectedHospital,
            'selectedSector'   => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'loading'          => $this->loading,
        ]);
    }

    public function openPatientModal($patient, $hospital = '')
    {
        try {
            Log::info('🔄 SbarReport: openPatientModal called', [
                'patient_id' => $patient['nr_atendimento'] ?? 'N/A',
                'hospital' => $hospital
            ]);
            
            // Dispatch event with proper data structure
            $this->dispatch('openPatientModal', [
                'patient' => $patient,
                'hospital' => $hospital
            ]);
            
            Log::info('✅ SbarReport: Event dispatched successfully');
            
        } catch (\Exception $e) {
            Log::error('❌ SbarReport: Error dispatching modal event', [
                'error' => $e->getMessage(),
                'patient' => $patient ?? 'null'
            ]);
        }
    }

    public function loadPatientData()
    {
        try {
            $this->loading = true;
            $this->errorMessage = null;
            $this->loadingMessage = "Carregando dados dos pacientes...";

            // FIXED: Hospital name loading - moved to beginning and ensured it's always set
            $sectorModel = new \App\Models\EMR\Sector();
            $sector = collect($sectorModel->getAllowedSectors())
                ->first(fn($s) => $s->cd_setor_atendimento == $this->selectedSector);
            
            $hospitalModel = new \App\Models\EMR\Hospital();
            $hospital = $hospitalModel->getAllHospitalsWithSectors()
                ->first(fn($h) => $h->hospital_id == ($sector->hospital_id ?? null));
            
            // Ensure hospital name is always set
            $this->currentHospitalName = $hospital->hospital_name ?? 'Hospital Não Identificado';

            // Load patient data
            $patientModel = new \App\Models\EMR\Patient();
            $allPatients = $patientModel->getAllPatientsInSector($this->selectedSector);

            $filtered = collect($allPatients);

            // Apply filters...
            if ($this->mewsFilter !== 'all') {
                $filtered = $filtered->filter(function($p) {
                    if (!$p->has_patient) return false;
                    $score = $p->mews_score;
                    if ($this->mewsFilter === 'critical') return $score !== null && $score >= 5;
                    if ($this->mewsFilter === 'warning') return $score !== null && $score >= 3 && $score < 5;
                    if ($this->mewsFilter === 'normal') return $score === null || $score < 3;
                    return true;
                });
            }

            if ($this->surgicalFilter === 'with_surgery') {
                $filtered = $filtered->filter(function($p) {
                    return $p->has_patient && $p->has_surgery;
                });
            }

            // Apply sorting...
            $filtered = $filtered->sort(function($a, $b) {
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

            $this->patients = $filtered->toArray();

            Log::info("Patient data loaded successfully", [
                'sector' => $this->selectedSector,
                'hospital' => $this->currentHospitalName,
                'patient_count' => count($this->patients)
            ]);
            
        } catch (\Exception $e) {
            $this->patients = [];
            $this->errorMessage = "Erro ao carregar dados: " . $e->getMessage();
            Log::error("Error loading patient data: " . $e->getMessage());
        } finally {
            // FIXED: Always set loading to false when done
            $this->loading = false;
        }
    }

    public function loadDataOnDemand()
    {
        if (empty($this->patients) && !$this->loading) {
            $this->loadPatientData();
        }
    }

    public function changeHospital($hospitalId)
    {
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;
        // Use setores já carregados
        $sectorsOfHospital = collect($this->sectors)->filter(fn($sector) =>
            $sector['hospital_id'] == $this->selectedHospital
        )->values();
        $firstAllowed = $sectorsOfHospital->first();
        $this->selectedSector = $firstAllowed['cd_setor_atendimento'] ?? null;
        $this->loadPatientData();
    }

    public function changeSelector($sectorId)
    {
        $this->selectedSector = $sectorId;
        $this->loadPatientData();
    }

    public function applyMewsFilter($filter)
    {
        $this->mewsFilter = $filter;
        $this->loadPatientData();
    }

    public function applySurgicalFilter($filter)
    {
        $this->surgicalFilter = $filter;
        $this->loadPatientData();
    }

    public function applyOrderBy($field)
    {
        $this->orderBy = $field;
        $this->loadPatientData();
    }

    public function toggleOrderDirection()
    {
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->loadPatientData();
    }

     public function refreshData()
    {
        $this->loadingMessage = "Atualizando dados...";
        $this->errorMessage = null;
        Cache::forget("sbar_data_{$this->selectedSector}_" . md5(serialize([
            'mews_filter' => $this->mewsFilter,
            'surgical_filter' => $this->surgicalFilter,
            'order_by' => $this->orderBy,
            'order_direction' => $this->orderDirection,
        ])));
        Cache::forget("hospital_name_{$this->selectedSector}");
        $this->loadPatientData();
    }

    public function resetFilters()
    {
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'leito';
        $this->orderDirection = 'asc';
        $this->loadingMessage = "Resetando filtros...";
        $this->loadPatientData();
        session()->flash('message', 'Filtros resetados com sucesso!');
    }
}