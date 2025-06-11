<?php

namespace App\Livewire;

use App\Models\EMR\SbarReport as SbarReportModel;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SbarReport extends Component
{
    // Core properties
    public $loading = true;
    public $loadingMessage = 'Carregando dados...';
    public $errorMessage = null;
    public $patients = [];
    public $sectors = [];
    public $selectedSector = null;
    public $currentHospitalName = 'Carregando...';
    
    // Filters
    public $mewsFilter = 'all';
    public $orderBy = 'leito';
    public $orderDirection = 'asc';
    
    // Modal
    public $showModal = false;
    public $currentPatient = null;
    public $patientDetails = null;
    public $loadingPatient = false;
    
    // Cache para detalhes
    public $patientDetailsCache = [];
    
    // Setores permitidos
    protected $allowedSectors = [20277, 1228, 9598, 1147, 4855];

    public function mount()
    {
        try {
            $this->loadSectors();
            
            if (empty($this->sectors)) {
                throw new \Exception("Nenhum setor foi carregado");
            }
            
            $this->selectedSector = $this->allowedSectors[0];
            $this->loadPatientData();
            
        } catch (\Exception $e) {
            $this->errorMessage = "Erro ao inicializar: " . $e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Carregamento de setores usando Sector model
     */
    private function loadSectors()
    {
        $cacheKey = "sbar_sectors_list";
        
        $this->sectors = Cache::remember($cacheKey, 1800, function() {
            try {
                $sectorModel = new \App\Models\EMR\Sector();
                $sectors = $sectorModel->getAllowedSectors();
                
                return $sectors->map(function($sector) {
                    return [
                        'cd_setor_atendimento' => $sector->cd_setor_atendimento,
                        'ds_setor_atendimento' => $sector->ds_setor_atendimento,
                        'hospital_name' => $sector->hospital_name
                    ];
                })->toArray();
                
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Carregamento de dados - agora com filtros aplicados na query
     */
    public function loadPatientData()
    {
        try {
            $this->loading = true;
            $this->loadingMessage = "Carregando dados dos pacientes...";
            
            $model = new SbarReportModel();
            
            // Preparar filtros para a query
            $filters = [
                'mews_filter' => $this->mewsFilter,
                'order_by' => $this->orderBy,
                'order_direction' => $this->orderDirection,
            ];
            
            // Carregar dados com filtros aplicados na query
            $this->patients = $model->getBasePatientData($this->selectedSector, $filters)->toArray();
            
            // Carregar nome do hospital
            $this->currentHospitalName = $model->getHospitalName($this->selectedSector);
            
        } catch (\Exception $e) {
            $this->patients = [];
            $this->errorMessage = "Erro ao carregar dados: " . $e->getMessage();
        }
        
        $this->loading = false;
    }

    /**
     * Mudança de setor
     */
    public function changeSelector($sectorId)
    {
        $this->selectedSector = $sectorId;
        $this->patientDetailsCache = [];
        $this->loadPatientData();
    }

    /**
     * Aplicar filtro MEWS
     */
    public function applyMewsFilter($filter)
    {
        $this->mewsFilter = $filter;
        $this->loadPatientData(); // Recarrega com filtro aplicado na query
    }

    /**
     * Aplicar ordenação
     */
    public function applyOrderBy($field)
    {
        $this->orderBy = $field;
        $this->loadPatientData();
    }

    /**
     * Alternar direção da ordenação
     */
    public function toggleOrderDirection()
    {
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->loadPatientData();
    }

    /**
     * Refresh dados
     */
    public function refreshData()
    {
        $this->loadingMessage = "Atualizando dados...";
        $this->errorMessage = null;
        
        // Limpar caches específicos
        Cache::forget("sbar_data_{$this->selectedSector}_" . md5(serialize([
            'mews_filter' => $this->mewsFilter,
            'order_by' => $this->orderBy,
            'order_direction' => $this->orderDirection,
        ])));
        Cache::forget("hospital_name_{$this->selectedSector}");
        
        $this->patientDetailsCache = [];
        $this->loadPatientData();
    }

    /**
     * Modal do paciente
     */
    public function openModal($attendanceNumber, $personId, $hasPatient)
    {
        $this->showModal = true;
        
        $this->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'cd_pessoa_fisica' => $personId,
            'has_patient' => $hasPatient,
            'hospital_name' => $this->currentHospitalName ?? 'Hospital não identificado'
        ];
        
        if ($hasPatient) {
            $this->showPatientDetails($attendanceNumber);
        }
    }

    /**
     * Detalhes do paciente com cache
     */
    public function showPatientDetails($attendanceNumber)
    {
        if (empty($attendanceNumber)) {
            $this->errorMessage = "Número de atendimento vazio";
            return;
        }
        
        $this->loadingPatient = true;
        $this->errorMessage = null;
        
        try {
            if (isset($this->patientDetailsCache[$attendanceNumber])) {
                $this->patientDetails = $this->patientDetailsCache[$attendanceNumber];
                $this->loadingPatient = false;
                return;
            }
            
            $model = new SbarReportModel();
            $this->patientDetails = $model->getPatientDetails($attendanceNumber);
            
            if (!empty($this->patientDetails)) {
                $this->patientDetails->cpoe_procedures = $model->getPatientCpoeProcedures($attendanceNumber);
                $this->patientDetailsCache[$attendanceNumber] = $this->patientDetails;
            } else {
                $this->errorMessage = "Não foi possível carregar os detalhes do paciente.";
            }
        } catch (\Exception $e) {
            $this->errorMessage = "Erro: " . $e->getMessage();
        }
        
        $this->loadingPatient = false;
    }

    /**
     * Fechar modal
     */
    public function closeModal()
    {
        $this->showModal = false;
        $this->currentPatient = null;
        $this->patientDetails = null;
        $this->errorMessage = null;
    }

    /**
     * Render simplificado
     */
    public function render()
    {
        return view('livewire.sbar-report', [
            'patients' => $this->patients,
            'pagination' => [
                'total' => count($this->patients),
                'current_page' => 1,
                'per_page' => count($this->patients),
                'last_page' => 1
            ]
        ]);
    }
}