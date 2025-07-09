<?php

namespace App\Livewire;

use App\Models\EMR\SbarReport as SbarReportModel;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
    public $surgicalFilter = 'all';
    public $orderBy = 'leito';
    public $orderDirection = 'asc';
    
    // Modal
    public $showModal = false;
    public $currentPatient = null;
    public $patientDetails = null;
    public $loadingPatient = false;
    
    // Alert properties
    public $patientAlerts = [];
    public $showAlertsModal = false;
    
    // Cache para detalhes
    public $patientDetailsCache = [];
    
    // Setores permitidos
    protected $allowedSectors = [20277, 1228, 9598, 1147, 4855];

    // NEW: Enhanced chat system properties
    public $currentShift;
    public $currentDate;
    public $currentUser;
    public $selectedHistoryDate;
    public $selectedHistoryShift;
    public $viewingHistory = false;
    public $shiftMessages = [];
    public $newChatMessage = '';
    public $messageLoading = false;

    public function mount()
    {
        try {
            $this->loadSectors();
            $this->initializeChatSystem();
            
            // Set default sector and load data immediately
            $this->selectedSector = $this->allowedSectors[0];
            $this->loadPatientData(); // Load data on mount
            
        } catch (\Exception $e) {
            $this->errorMessage = "Erro ao inicializar: " . $e->getMessage();
            $this->loading = false;
        }
    }

    // Simplified lazy loading method - only if really needed
    public function loadDataOnDemand()
    {
        // Only load if we don't have data and not already loading
        if (empty($this->patients) && !$this->loading) {
            $this->loadPatientData();
        }
    }
    
    // Enhanced chat system initialization
    private function initializeChatSystem()
    {
        $now = Carbon::now('America/Sao_Paulo');
        $this->currentDate = $now->format('Y-m-d');
        $this->currentShift = $this->calculateCurrentShift($now);
        
        $this->currentUser = $this->getCurrentUserInfo();
        
        // Initialize to current shift
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        
        Log::info("Chat system initialized", [
            'current_shift' => $this->currentShift,
            'current_date' => $this->currentDate,
            'user' => $this->currentUser['name']
        ]);
    }

    // Accurate shift calculation based on Brazilian hospital standards
    private function calculateCurrentShift($dateTime)
    {
        $hour = $dateTime->hour;
        
        // Day shift: 07:00 to 18:59 | Night shift: 19:00 to 06:59
        if ($hour >= 7 && $hour <= 18) {
            return 'dia';
        } else {
            return 'noite';
        }
    }

    // Get current user information
    private function getCurrentUserInfo()
    {
        if (Auth::check()) {
            $user = Auth::user();
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Usuário Logado',
                'role' => $user->role ?? 'Profissional de Saúde',
                'department' => $user->department ?? 'Enfermagem'
            ];
        }
        
        return [
            'id' => 0,
            'name' => 'Usuário Demonstração',
            'role' => 'Enfermeiro(a)',
            'department' => 'Enfermagem'
        ];
    }

    // Updated history navigation with proper date validation
    public function updatedSelectedHistoryDate()
    {
        $this->updateHistoryView();
    }

    public function updatedSelectedHistoryShift()
    {
        $this->updateHistoryView();
    }

    private function updateHistoryView()
    {
        $selectedDate = Carbon::parse($this->selectedHistoryDate);
        $currentDate = Carbon::parse($this->currentDate);
        
        // Check if viewing history
        $this->viewingHistory = (
            $this->selectedHistoryDate !== $this->currentDate || 
            $this->selectedHistoryShift !== $this->currentShift
        );
        
        // Validate date is not in future
        if ($selectedDate->isFuture()) {
            $this->selectedHistoryDate = $this->currentDate;
            $this->selectedHistoryShift = $this->currentShift;
            $this->viewingHistory = false;
            session()->flash('error', 'Não é possível consultar datas futuras.');
            return;
        }
        
        $this->loadShiftMessages();
        
        Log::info("History view updated", [
            'selected_date' => $this->selectedHistoryDate,
            'selected_shift' => $this->selectedHistoryShift,
            'viewing_history' => $this->viewingHistory
        ]);
    }

    public function returnToCurrentShift()
    {
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        $this->loadShiftMessages();
    }

    // Enhanced message loading with realistic historical data
    private function loadShiftMessages()
    {
        $this->shiftMessages = $this->getShiftMessagesData(
            $this->selectedHistoryDate, 
            $this->selectedHistoryShift
        );
    }

    // Comprehensive demo data structure simulating real chat history
    private function getShiftMessagesData($date, $shift)
    {
        $demoData = [
            '2024-01-15' => [
                'dia' => [
                    [
                        'id' => 1,
                        'author' => 'Enfª Maria Santos',
                        'role' => 'Enfermeira',
                        'user_id' => 101,
                        'time' => '07:30',
                        'message' => 'PASSAGEM DE PLANTÃO: Paciente estável, sinais vitais dentro dos parâmetros. Última medicação 06:00. Familiares orientados sobre horário de visitas.',
                        'is_pinned' => true,
                        'can_edit' => false,
                        'pinned_by' => 'Enfª Chefe Ana Silva',
                        'created_at' => '2024-01-15 07:30:00'
                    ],
                    [
                        'id' => 2,
                        'author' => 'Téc. João Silva',
                        'role' => 'Técnico Enfermagem',
                        'user_id' => 102,
                        'time' => '09:15',
                        'message' => 'Higiene matinal realizada. Paciente colaborativo. Curativo de acesso vascular trocado conforme protocolo. Sem intercorrências.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 09:15:00'
                    ],
                    [
                        'id' => 3,
                        'author' => 'Dr. Carlos Lima',
                        'role' => 'Médico',
                        'user_id' => 103,
                        'time' => '11:45',
                        'message' => 'Reavaliação médica: Evolução satisfatória. Manter conduta atual. Laboratórios solicitados para amanhã. Atenção especial à diurese.',
                        'is_pinned' => false,
                        'can_edit' => false,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 11:45:00'
                    ]
                ],
                'noite' => [
                    [
                        'id' => 4,
                        'author' => 'Enfº Pedro Oliveira',
                        'role' => 'Enfermeiro',
                        'user_id' => 104,
                        'time' => '19:30',
                        'message' => 'Recepção de plantão: Paciente consciente, orientado. PA 120x80, FC 72bpm, Tax 36.2°C. Sem queixas álgicas. Acompanhante presente.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 19:30:00'
                    ],
                    [
                        'id' => 5,
                        'author' => 'Téc. Ana Souza',
                        'role' => 'Técnico Enfermagem',
                        'user_id' => 105,
                        'time' => '22:00',
                        'message' => 'Medicação das 22h administrada conforme prescrição médica. Paciente em repouso. Controles noturnos agendados de 4/4h.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 22:00:00'
                    ]
                ]
            ],
            '2024-01-16' => [
                'dia' => [
                    [
                        'id' => 6,
                        'author' => 'Enfª Maria Santos',
                        'role' => 'Enfermeira',
                        'user_id' => 101,
                        'time' => '07:45',
                        'message' => 'Plantão noturno sem intercorrências. Paciente dormiu bem. Medicações em dia. Laboratórios coletados às 06:00.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-16 07:45:00'
                    ]
                ],
                'noite' => []
            ]
        ];

        // Return messages for specific date/shift or empty array
        return $demoData[$date][$shift] ?? [];
    }

    // Enhanced message sending with validation and security
    public function sendChatMessage()
    {
        $message = trim($this->newChatMessage);
        
        // Validation
        if (empty($message)) {
            session()->flash('error', 'Mensagem não pode estar vazia.');
            return;
        }

        if ($this->viewingHistory) {
            session()->flash('error', 'Não é possível enviar mensagens em turnos anteriores.');
            return;
        }

        // Check if current shift is closed
        if ($this->isCurrentShiftClosed()) {
            session()->flash('error', 'Turno encerrado. Não é possível enviar mensagens fora do horário de plantão.');
            return;
        }

        if (strlen($message) > 1000) {
            session()->flash('error', 'Mensagem muito longa. Máximo de 1000 caracteres.');
            return;
        }

        // Security: Basic sanitization
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $this->messageLoading = true;

        try {
            // Simulate network delay
            usleep(500000); // 0.5 seconds

            $newMessage = [
                'id' => time() + rand(1000, 9999),
                'author' => $this->currentUser['name'],
                'role' => $this->currentUser['role'],
                'user_id' => $this->currentUser['id'],
                'time' => Carbon::now('America/Sao_Paulo')->format('H:i'),
                'message' => $message,
                'is_pinned' => false,
                'can_edit' => true,
                'pinned_by' => null,
                'created_at' => Carbon::now('America/Sao_Paulo')->toDateTimeString()
            ];

            // Add to current messages
            $this->shiftMessages[] = $newMessage;
            
            // Clear input
            $this->newChatMessage = '';
            
            Log::info("Message sent successfully", [
                'user' => $this->currentUser['name'],
                'shift' => $this->currentShift,
                'date' => $this->currentDate,
                'message_length' => strlen($message)
            ]);

            session()->flash('success', 'Mensagem registrada com sucesso.');

        } catch (\Exception $e) {
            Log::error("Error sending message: " . $e->getMessage());
            session()->flash('error', 'Erro ao enviar mensagem. Tente novamente.');
        }

        $this->messageLoading = false;
    }

    // NEW: Check if current shift is closed
    private function isCurrentShiftClosed()
    {
        $now = Carbon::now('America/Sao_Paulo');
        $currentHour = $now->hour;
        
        // Day shift: 07:00-19:00, Night shift: 19:00-07:00
        if ($this->currentShift === 'dia') {
            return ($currentHour < 7 || $currentHour >= 19);
        } else {
            return ($currentHour >= 7 && $currentHour < 19);
        }
    }

    // Enhanced pin toggling with shift validation
    public function toggleMessagePin($messageId)
    {
        if ($this->viewingHistory) {
            session()->flash('error', 'Não é possível alterar mensagens em turnos anteriores.');
            return;
        }

        if ($this->isCurrentShiftClosed()) {
            session()->flash('error', 'Turno encerrado. Não é possível alterar mensagens fora do horário de plantão.');
            return;
        }

        foreach ($this->shiftMessages as $index => $message) {
            if ($message['id'] == $messageId) {
                $wasUnpinning = $message['is_pinned'];
                
                $this->shiftMessages[$index]['is_pinned'] = !$message['is_pinned'];
                $this->shiftMessages[$index]['pinned_by'] = $wasUnpinning ? null : $this->currentUser['name'];
                
                $action = $wasUnpinning ? 'desfixada' : 'fixada';
                session()->flash('success', "Mensagem {$action} com sucesso.");
                
                Log::info("Message pin toggled", [
                    'message_id' => $messageId,
                    'action' => $action,
                    'user' => $this->currentUser['name']
                ]);
                
                break;
            }
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
                
            } catch (\Exception) {
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
            $this->errorMessage = null;
            $this->loadingMessage = "Carregando dados dos pacientes...";
            
            $model = new SbarReportModel();
            
            // Preparar filtros para a query
            $filters = [
                'mews_filter' => $this->mewsFilter,
                'surgical_filter' => $this->surgicalFilter,
                'order_by' => $this->orderBy,
                'order_direction' => $this->orderDirection,
            ];
            
            // Carregar dados com filtros aplicados na query
            $rawPatients = $model->getBasePatientData($this->selectedSector, $filters);
            
            // FIXED: Ensure consistent field naming for internment days
            $this->patients = $rawPatients->map(function($patient) {
                // Standardize internment days field name
                if (isset($patient->internment_days) && !isset($patient->tempo_internacao_dias)) {
                    $patient->tempo_internacao_dias = $patient->internment_days;
                } elseif (!isset($patient->internment_days) && isset($patient->tempo_internacao_dias)) {
                    $patient->internment_days = $patient->tempo_internacao_dias;
                }
                
                return $patient;
            })->toArray();
            
            // Carregar nome do hospital
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
            $this->loading = false; // Always set loading to false
        }
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
     * Aplicar filtro cirúrgico
     */
    public function applySurgicalFilter($filter)
    {
        $this->surgicalFilter = $filter;
        $this->loadPatientData();
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
            'surgical_filter' => $this->surgicalFilter,
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
        
        // Load shift messages when opening modal
        $this->loadShiftMessages();
        
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
                
                // Check for alerts after loading cached details
                $this->checkPatientAlerts($attendanceNumber);
                return;
            }
            
            $model = new SbarReportModel();
            $this->patientDetails = $model->getPatientDetails($attendanceNumber);
            
            if (!empty($this->patientDetails)) {
                $this->patientDetails->cpoe_procedures = $model->getPatientCpoeProcedures($attendanceNumber);
                $this->patientDetailsCache[$attendanceNumber] = $this->patientDetails;
                
                // Check for alerts after loading details
                $this->checkPatientAlerts($attendanceNumber);
            } else {
                $this->errorMessage = "Não foi possível carregar os detalhes do paciente.";
            }
        } catch (\Exception $e) {
            $this->errorMessage = "Erro: " . $e->getMessage();
        }
        
        $this->loadingPatient = false;
    }

    /**
     * NEW: Check for patient alerts with improved logging
     */
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
            
            // Debug: Log alerts found with more details
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
            
            // Show alerts modal if there are alerts
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
            // Silently handle alert errors to not break the main modal
        }
    }

    /**
     * NEW: Close alerts modal
     */
    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
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
        
        // NEW: Also close alerts
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }

    /**
     * Reset all filters to default values
     */
    public function resetFilters()
    {
        // Reset all filter properties to their defaults
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'leito';
        $this->orderDirection = 'asc';
        
        // Clear caches
        $this->patientDetailsCache = [];
        
        // Reload data with default filters
        $this->loadingMessage = "Resetando filtros...";
        $this->loadPatientData();
        
        // Optional: Show feedback message (you can remove this if not needed)
        session()->flash('message', 'Filtros resetados com sucesso!');
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