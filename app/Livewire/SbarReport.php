<?php

namespace App\Livewire;

use App\Models\EMR\SbarReport as SbarReportModel;
use App\Models\System\SystemConfiguration;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ChatSystem;

class SbarReport extends Component
{
    // Propriedades principais
    protected ChatSystem $chatSystem;
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

    // Modal
    public $showModal = false;
    public $currentPatient = null;
    public $patientDetails = null;
    public $loadingPatient = false;

    // Alertas
    public $patientAlerts = [];
    public $showAlertsModal = false;

    // Cache de detalhes
    public $patientDetailsCache = [];

    // IDs permitidos
    protected $allowedHospitals = [];
    protected $allowedSectors = [];
    protected $allowedBedUnits = [];

    // Propriedades do sistema de chat
    public $currentShift;
    public $currentDate;
    public $currentUser;
    public $selectedHistoryDate;
    public $selectedHistoryShift;
    public $viewingHistory = false;
    public $shiftMessages = [];
    public $newChatMessage = '';
    public $messageLoading = false;

    // Inicializa o componente
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

        // Seleciona o primeiro hospital permitido
        $this->selectedHospital = $this->allowedHospitals[0];

        // Carrega setores/leitos permitidos para o hospital selecionado
        $this->loadAllowed();

        // Filtra setores permitidos do hospital selecionado
        $sectorsOfHospital = collect($this->sectors)->filter(function($sector) {
            $hospitalId = is_array($sector) ? $sector['hospital_id'] ?? null : $sector->hospital_id ?? null;
            return $hospitalId == $this->selectedHospital;
        })->values();

        // Seleciona o primeiro setor permitido do hospital
        $firstAllowed = $sectorsOfHospital->first();
        $this->selectedSector = $firstAllowed['cd_setor_atendimento'] ?? ($firstAllowed->cd_setor_atendimento ?? null);

        // Se não encontrou setor, tenta pegar o primeiro setor permitido globalmente
        if (empty($this->selectedSector) && !empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        }

        // Inicializa chat e carrega pacientes
        $this->chatSystem = new ChatSystem();
        $this->chatSystem->initialize();
        $this->syncChatProperties();

        // Carrega pacientes do setor inicial
        $this->loadPatientData();
    }

    // Renderiza a view do componente
    public function render()
    {
        return view('livewire.sbar-report', [
            'patients' => $this->patients,
            'hospitals' => $this->hospitals,
            'pagination' => [
                'total' => count($this->patients),
                'current_page' => 1,
                'per_page' => count($this->patients),
                'last_page' => 1
            ]
        ]);
    }

    // Carrega hospitais, setores e leitos permitidos
    private function loadAllowed()
    {
        // Busca os IDs permitidos do banco de configurações
        $this->allowedHospitals = SystemConfiguration::allowedHospitalCodes();
        $this->allowedSectors   = SystemConfiguration::allowedSectorCodes();
        $this->allowedBedUnits  = SystemConfiguration::allowedBedCodes();

        Log::debug('Allowed IDs', [
            'hospitals' => $this->allowedHospitals,
            'sectors' => $this->allowedSectors,
            'beds' => $this->allowedBedUnits,
        ]);

        // 1. Hospitais permitidos
        $hospitalModel = new \App\Models\EMR\Hospital();
        $allHospitals = $hospitalModel->getAllHospitalsWithSectors();
        $filteredHospitals = $allHospitals->filter(function($hospital) {
            return in_array($hospital->hospital_id, $this->allowedHospitals);
        })->values();
        $this->hospitals = $filteredHospitals->toArray();

        // 2. Setores permitidos
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
        // Troque para objeto
        $this->sectors = $sectors->map(function($sector) {
            return (array) $sector;
        })->toArray();

        // 3. Leitos permitidos (BedUnits)
        $bedModel = new \App\Models\EMR\BedUnit();

        if (!empty($this->selectedSector)) {
            $beds = $bedModel->getBedsBySector($this->selectedSector);
        } elseif (!empty($this->sectors)) {
            $beds = collect();
            foreach ($this->sectors as $sector) {
                // Troque para objeto
                $beds = $beds->merge($bedModel->getBedsBySector($sector['cd_setor_atendimento'] ?? $sector->cd_setor_atendimento));
            }
        } elseif (!empty($this->hospitals)) {
            $beds = collect();
            foreach ($this->hospitals as $hospital) {
                // Troque para objeto
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

        // Filtra leitos permitidos se $allowedBedUnits não estiver vazio
        if (empty($this->allowedBedUnits)) {
            $this->beds = $beds->toArray();
        } else {
            $this->beds = $beds->filter(fn($bed) => in_array($bed->cd_unidade_basica, $this->allowedBedUnits))->values()->toArray();
        }
    }

    // Carrega dados dos pacientes do setor selecionado
    public function loadPatientData()
    {
        try {
            $this->loading = true;
            $this->errorMessage = null;
            $this->loadingMessage = "Carregando dados dos pacientes...";
            $model = new SbarReportModel();
            $filters = [
                'mews_filter' => $this->mewsFilter,
                'surgical_filter' => $this->surgicalFilter,
                'order_by' => $this->orderBy,
                'order_direction' => $this->orderDirection,
            ];
            $rawPatients = $model->getBasePatientData($this->selectedSector, $filters);

            Log::debug('Pacientes carregados:', [
                'selectedSector' => $this->selectedSector,
                'filters' => $filters,
                'rawPatientsCount' => $rawPatients->count(),
                'rawPatientsSample' => $rawPatients->take(3)->toArray(),
            ]);

            $this->patients = $rawPatients->map(function($patient) {
                if (isset($patient->internment_days) && !isset($patient->tempo_internacao_dias)) {
                    $patient->tempo_internacao_dias = $patient->internment_days;
                } elseif (!isset($patient->internment_days) && isset($patient->tempo_internacao_dias)) {
                    $patient->internment_days = $patient->tempo_internacao_dias;
                }
                return $patient;
            })->toArray();
            $this->currentHospitalName = $model->getHospitalName($this->selectedSector);
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

    // Carrega dados dos pacientes sob demanda
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
        // Se não houver setor permitido para o hospital, seleciona o primeiro disponível
        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        }
        $this->loadPatientData();
    }

    // Troca o setor selecionado
    public function changeSelector($sectorId)
    {
        $this->selectedSector = $sectorId;
        $this->patientDetailsCache = [];
        $this->loadPatientData();
    }

    // Aplica filtro MEWS
    public function applyMewsFilter($filter)
    {
        $this->mewsFilter = $filter;
        $this->loadPatientData();
    }

    // Aplica filtro cirúrgico
    public function applySurgicalFilter($filter)
    {
        $this->surgicalFilter = $filter;
        $this->loadPatientData();
    }

    // Aplica ordenação
    public function applyOrderBy($field)
    {
        $this->orderBy = $field;
        $this->loadPatientData();
    }

    // Alterna direção da ordenação
    public function toggleOrderDirection()
    {
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->loadPatientData();
    }

    // Atualiza dados dos pacientes
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
        $this->patientDetailsCache = [];
        $this->loadPatientData();
    }

    // Abre modal do paciente
    public function openModal($attendanceNumber, $personId, $hasPatient)
    {
        $this->showModal = true;
        $this->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'cd_pessoa_fisica' => $personId,
            'has_patient' => $hasPatient,
            'hospital_name' => $this->currentHospitalName ?? 'Hospital não identificado'
        ];
        $this->ensureChatSystem();
        $this->loadShiftMessages();
        if ($hasPatient) {
            $this->showPatientDetails($attendanceNumber);
        }
    }

    // Mostra detalhes do paciente (com cache)
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
                $this->checkPatientAlerts($attendanceNumber);
                return;
            }
            $model = new SbarReportModel();
            $this->patientDetails = $model->getPatientDetails($attendanceNumber);
            if (!empty($this->patientDetails)) {
                $this->patientDetails->cpoe_procedures = $model->getPatientCpoeProcedures($attendanceNumber);
                $this->patientDetailsCache[$attendanceNumber] = $this->patientDetails;
                $this->checkPatientAlerts($attendanceNumber);
            } else {
                $this->errorMessage = "Não foi possível carregar os detalhes do paciente.";
            }
        } catch (\Exception $e) {
            $this->errorMessage = "Erro: " . $e->getMessage();
        }
        $this->loadingPatient = false;
    }

    // Verifica alertas ativos do paciente
    private function checkPatientAlerts($attendanceNumber)
    {
        if (!$this->currentPatient || empty($this->currentPatient['cd_pessoa_fisica'])) {
            Log::info("No patient data available for alerts check", [
                'attendance' => $attendanceNumber
            ]);
            return;
        }
        try {
            $clinicalModel = new \App\Models\EMR\PatientClinical();
            $this->patientAlerts = $clinicalModel->getPatientActiveAlerts(
                $attendanceNumber,
                $this->currentPatient['cd_pessoa_fisica']
            );
            Log::info("Alerts check completed for patient {$attendanceNumber}:", [
                'person_id' => $this->currentPatient['cd_pessoa_fisica'],
                'alerts_count' => count($this->patientAlerts),
                'alerts_summary' => collect($this->patientAlerts)->map(function($alert) {
                    return [
                        'type' => $alert['type'],
                        'message_length' => strlen($alert['message']),
                        'has_end_date' => !empty($alert['end_date'])
                    ];
                })->toArray()
            ]);
            if (!empty($this->patientAlerts)) {
                $this->showAlertsModal = true;
                Log::info("Displaying alerts modal for patient {$attendanceNumber} with " . count($this->patientAlerts) . " alerts");
            } else {
                Log::info("No active alerts found for patient {$attendanceNumber}");
            }
        } catch (\Exception $e) {
            Log::error("Error checking patient alerts for {$attendanceNumber}: " . $e->getMessage(), [
                'exception' => $e,
                'person_id' => $this->currentPatient['cd_pessoa_fisica'] ?? 'unknown'
            ]);
        }
    }

    // Fecha modal de alertas
    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }

    // Fecha modal do paciente
    public function closeModal()
    {
        $this->showModal = false;
        $this->currentPatient = null;
        $this->patientDetails = null;
        $this->errorMessage = null;
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }

    // Sincroniza propriedades do ChatSystem para o componente
    private function syncChatProperties()
    {
        $this->currentShift = $this->chatSystem->currentShift;
        $this->currentDate = $this->chatSystem->currentDate;
        $this->currentUser = $this->chatSystem->currentUser; // Se for array simples, ok
        $this->selectedHistoryDate = $this->chatSystem->selectedHistoryDate;
        $this->selectedHistoryShift = $this->chatSystem->selectedHistoryShift;
        $this->viewingHistory = $this->chatSystem->viewingHistory;
        $this->shiftMessages = $this->chatSystem->shiftMessages;
        $this->newChatMessage = $this->chatSystem->newChatMessage;
        $this->messageLoading = $this->chatSystem->messageLoading;
    }

    // Atualiza data do histórico
    public function updatedSelectedHistoryDate()
    {
        $this->chatSystem->selectedHistoryDate = $this->selectedHistoryDate;
        $this->chatSystem->updatedSelectedHistoryDate();
        $this->syncChatProperties();
    }

    // Atualiza turno do histórico
    public function updatedSelectedHistoryShift()
    {
        $this->chatSystem->selectedHistoryShift = $this->selectedHistoryShift;
        $this->chatSystem->updatedSelectedHistoryShift();
        $this->syncChatProperties();
    }

    // Volta para o turno atual
    public function returnToCurrentShift()
    {
        $this->chatSystem->returnToCurrentShift();
        $this->syncChatProperties();
    }

    private function ensureChatSystem()
    {
        if (!isset($this->chatSystem)) {
            $this->chatSystem = new \App\Services\ChatSystem();
            $this->chatSystem->initialize();
        }
    }

    // Carrega mensagens do turno
    public function loadShiftMessages()
    {
        $this->ensureChatSystem();
        $this->chatSystem->loadShiftMessages();
        $this->syncChatProperties();
    }

    // Envia mensagem do chat
    public function sendChatMessage()
    {
        $this->chatSystem->sendChatMessage(
            $this->chatSystem->currentUser,
            $this->chatSystem->currentShift,
            $this->chatSystem->currentDate,
            $this->chatSystem->viewingHistory,
            $this->newChatMessage,
            $this->chatSystem->shiftMessages,
            $this->chatSystem->messageLoading
        );
        $this->newChatMessage = '';
        $this->syncChatProperties();
    }

    // Alterna fixação de mensagem
    public function toggleMessagePin($messageId)
    {
        $this->chatSystem->toggleMessagePin(
            $messageId,
            $this->chatSystem->shiftMessages,
            $this->chatSystem->viewingHistory,
            $this->chatSystem->currentShift,
            $this->chatSystem->currentUser
        );
        $this->syncChatProperties();
    }

    // Reseta filtros para valores padrão
    public function resetFilters()
    {
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'leito';
        $this->orderDirection = 'asc';
        $this->patientDetailsCache = [];
        $this->loadingMessage = "Resetando filtros...";
        $this->loadPatientData();
        session()->flash('message', 'Filtros resetados com sucesso!');
    }
}
