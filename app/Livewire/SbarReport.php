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

    // IDs permitidos
    protected $allowedHospitals = [];
    protected $allowedSectors = [];
    protected $allowedBedUnits = [];

    // PatientModal integration
    public $showPatientModal = false;
    public $currentPatient = null;        
    public $loadingPatient = false;       

    // Alerts for modal
    public $showAlertsModal = false;
    public $patientAlerts = [];

    protected $listeners = [
        'closePatientModal' => 'closePatientModal',
    ];

    public function mount()
    {
        $this->allowedHospitals = SystemConfiguration::allowedHospitalCodes();
        $this->allowedSectors   = SystemConfiguration::allowedSectorCodes();
        $this->allowedBedUnits  = SystemConfiguration::allowedBedCodes();

        if (empty($this->allowedHospitals)) {
            $this->errorMessage = "Nenhum hospital permitido. Solicite ao gestor a configuração pelo painel de administração.";
            $this->loading = false;
            return;
        }

        $this->selectedHospital = $this->allowedHospitals[0];
        $this->loadAllowed();

        $sectorsOfHospital = collect($this->sectors)->filter(function($sector) {
            $hospitalId = is_array($sector) ? $sector['hospital_id'] ?? null : $sector->hospital_id ?? null;
            return $hospitalId == $this->selectedHospital;
        })->values();

        $firstAllowed = $sectorsOfHospital->first();
        $this->selectedSector = $firstAllowed['cd_setor_atendimento'] ?? ($firstAllowed->cd_setor_atendimento ?? null);

        if (empty($this->selectedSector) && !empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        }

        $this->loadPatientData();
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
            // Modal props
            'showModal'        => $this->showPatientModal,
            'currentPatient'   => $this->currentPatient,
            'loadingPatient'   => $this->loadingPatient,
            'showAlertsModal'  => $this->showAlertsModal,
            'patientAlerts'    => $this->patientAlerts,
        ]);
    }

    private function loadAllowed()
    {
        $this->allowedHospitals = SystemConfiguration::allowedHospitalCodes();
        $this->allowedSectors   = SystemConfiguration::allowedSectorCodes();
        $this->allowedBedUnits  = SystemConfiguration::allowedBedCodes();

        $hospitalModel = new \App\Models\EMR\Hospital();
        $allHospitals = $hospitalModel->getAllHospitalsWithSectors();
        $filteredHospitals = $allHospitals->filter(function($hospital) {
            return in_array($hospital->hospital_id, $this->allowedHospitals);
        })->values();
        $this->hospitals = $filteredHospitals->toArray();

        $sectorModel = new \App\Models\EMR\Sector();

        if ($this->selectedHospital && empty($this->selectedSector)) {
            $sectors = $sectorModel->getSectorsByHospital($this->selectedHospital)
                ->filter(fn($sector) => in_array($sector->cd_setor_atendimento, $this->allowedSectors))->values();
        } elseif ($this->selectedSector) {
            $sectors = $sectorModel->getAllowedSectors()
                ->filter(fn($sector) => $sector->cd_setor_atendimento == $this->selectedSector && in_array($sector->cd_setor_atendimento, $this->allowedSectors))->values();
        } else {
            $sectors = $sectorModel->getAllowedSectors()
                ->filter(fn($sector) => in_array($sector->cd_setor_atendimento, $this->allowedSectors))->values();
        }
        $this->sectors = $sectors->map(function($sector) {
            return (array) $sector;
        })->toArray();

        $bedModel = new \App\Models\EMR\BedUnit();

        if (!empty($this->selectedSector)) {
            $beds = $bedModel->getBedsBySector($this->selectedSector);
        } elseif (!empty($this->sectors)) {
            $beds = collect();
            foreach ($this->sectors as $sector) {
                $beds = $beds->merge($bedModel->getBedsBySector($sector['cd_setor_atendimento'] ?? $sector->cd_setor_atendimento));
            }
        } elseif (!empty($this->hospitals)) {
            $beds = collect();
            foreach ($this->hospitals as $hospital) {
                $hospitalId = $hospital['hospital_id'] ?? $hospital->hospital_id;
                $hospitalSectors = $sectorModel->getSectorsByHospital($hospitalId);
                foreach ($hospitalSectors as $sector) {
                    if (in_array($sector->cd_setor_atendimento, $this->allowedSectors)) {
                        $beds = $beds->merge($bedModel->getBedsBySector($sector->cd_setor_atendimento));
                    }
                }
            }
        } else {
            $beds = collect();
            foreach ($sectorModel->getAllowedSectors() as $sector) {
                if (in_array($sector->cd_setor_atendimento, $this->allowedSectors)) {
                    $beds = $beds->merge($bedModel->getBedsBySector($sector->cd_setor_atendimento));
                }
            }
        }

        if (empty($this->allowedBedUnits)) {
            $this->beds = $beds->toArray();
        } else {
            $this->beds = $beds->filter(fn($bed) => in_array($bed->cd_unidade_basica, $this->allowedBedUnits))->values()->toArray();
        }
    }

    public function loadPatientData()
    {
        try {
            $this->loading = true;
            $this->errorMessage = null;
            $this->loadingMessage = "Carregando dados dos pacientes...";

            $cacheKey = "sbar_data_{$this->selectedSector}_" . md5(serialize([
                'mews_filter' => $this->mewsFilter,
                'surgical_filter' => $this->surgicalFilter,
                'order_by' => $this->orderBy,
                'order_direction' => $this->orderDirection,
            ]));

            // Tenta buscar do cache primeiro
            $patients = Cache::get($cacheKey);

            if (!$patients) {
                $patientModel = new Patient();
                // Busca todos os pacientes/leitos do setor (dados completos)
                $allPatients = $patientModel->getAllPatientsInSector($this->selectedSector);

                // Filtros em memória
                $filtered = collect($allPatients);

                // Filtro MEWS
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

                // Filtro cirurgia
                if ($this->surgicalFilter === 'with_surgery') {
                    $filtered = $filtered->filter(function($p) {
                        return $p->has_patient && $p->has_surgery;
                    });
                }

                // Ordenação em memória
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

                $patients = $filtered->toArray();
                // Salva no cache para próximas chamadas
                Cache::put($cacheKey, $patients, 60); // 1 min
            }

            $this->patients = $patients;

            // Hospital name pelo setor (cache separado)
            $sectorModel = new Sector();
            $sector = collect($sectorModel->getAllowedSectors())
                ->first(fn($s) => $s->cd_setor_atendimento == $this->selectedSector);
            $hospitalModel = new Hospital();
            $hospital = $hospitalModel->getAllHospitalsWithSectors()
                ->first(fn($h) => $h->hospital_id == ($sector->hospital_id ?? null));
            $this->currentHospitalName = $hospital->hospital_name ?? 'Hospital';

            Log::info("Patient data loaded successfully", [
                'sector' => $this->selectedSector,
                'patient_count' => count($this->patients)
            ]);
        } catch (\Exception $e) {
            $this->patients = [];
            $this->errorMessage = "Erro ao carregar dados: " . $e->getMessage();
            Log::error("Error loading patient data: " . $e->getMessage());
        } finally {
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
        $this->loadAllowed();
        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        }
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

    // --- PatientModal Integration ---

    public function openModal($nr_atendimento, $cd_pessoa_fisica, $has_patient = true)
    {
        $patient = collect($this->patients)->first(function ($p) use ($nr_atendimento) {
            return isset($p->nr_atendimento) && $p->nr_atendimento == $nr_atendimento;
        });

        if (!$patient) {
            $patient = [
                'nr_atendimento' => $nr_atendimento,
                'cd_pessoa_fisica' => $cd_pessoa_fisica,
                'has_patient' => $has_patient,
            ];
        } else {
            $patient = (array) $patient;
            $patient['has_patient'] = $has_patient;
        }

        $this->showPatientModal = true;
        $this->currentPatient = $patient;
        $this->loadingPatient = false;

        $this->dispatch('openPatientModal', $patient, $this->currentHospitalName);
    }

    public function closePatientModal()
    {
        $this->showPatientModal = false;
        $this->currentPatient = null;
        $this->loadingPatient = false;
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }
}