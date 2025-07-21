<?php

namespace App\Livewire;

use App\Repositories\EMR\SbarReport as SbarReportModel;
use App\Models\System\SystemConfiguration;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Repositories\MySQL\ChatRepository;

class SbarReport extends Component
{
    protected $listeners = [
    'chatMessageReceived' => 'onChatMessageReceived',
    'chatMessagePinned' => 'onChatMessagePinned',
    ];

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
    
    // Propriedades do chat
    public $currentShift;
    public $currentDate;
    public $currentUser;
    public $selectedHistoryDate;
    public $selectedHistoryShift;
    public $viewingHistory = false;
    public $shiftMessages = [];
    public $newChatMessage = '';
    public $messageLoading = false;
    public $chatMessagesCache = [];

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

        // Inicializa propriedades do chat
        $user = Auth::user();
        $this->currentUser = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->getRoleNames()->first() ?? '',
            'photo' => $user->photo() ?? '',
        ];

        $hour = now()->hour;
        $this->currentShift = ($hour >= 7 && $hour < 19) ? 'dia' : 'noite';
        $this->currentDate = now()->toDateString();
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        $this->shiftMessages = [];
        $this->newChatMessage = '';
        $this->messageLoading = false;

        // Carrega pacientes do setor inicial
        $this->loadPatientData();
    }

    // Renderiza a view do componente
    public function render()
    {
        return view('livewire.sbar-report', [
            // Modal e chat props
            'showModal'            => $this->showModal,
            'currentPatient'       => $this->currentPatient,
            'patientDetails'       => $this->patientDetails,
            'loadingPatient'       => $this->loadingPatient,
            'currentHospitalName'  => $this->currentHospitalName,
            'showAlertsModal'      => $this->showAlertsModal,
            'patientAlerts'        => $this->patientAlerts,
            'currentShift'         => $this->currentShift,
            'currentUser'          => $this->currentUser,
            'viewingHistory'       => $this->viewingHistory,
            'shiftMessages'        => $this->shiftMessages,
            'newChatMessage'       => $this->newChatMessage,
            'messageLoading'       => $this->messageLoading,
            'selectedHistoryDate'  => $this->selectedHistoryDate,
            'selectedHistoryShift' => $this->selectedHistoryShift,
            // Filtros e listagem
            'patients'             => $this->patients,
            'hospitals'            => $this->hospitals,
            'sectors'              => $this->sectors,
            'beds'                 => $this->beds,
            'errorMessage'         => $this->errorMessage,
            'loadingMessage'       => $this->loadingMessage,
            'mewsFilter'           => $this->mewsFilter,
            'surgicalFilter'       => $this->surgicalFilter,
            'orderBy'              => $this->orderBy,
            'orderDirection'       => $this->orderDirection,
            'selectedHospital'     => $this->selectedHospital,
            'selectedSector'       => $this->selectedSector,
            'pagination'           => [
                'total'        => count($this->patients),
                'current_page' => 1,
                'per_page'     => count($this->patients),
                'last_page'    => 1
            ],
        ]);
    }

    // Carrega hospitais, setores e leitos permitidos
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
        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        }
        $this->loadPatientData();
    }

    public function changeSelector($sectorId)
    {
        $this->selectedSector = $sectorId;
        $this->patientDetailsCache = [];
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
        $this->patientDetailsCache = [];
        $this->loadPatientData();
    }

    public function openModal($attendanceNumber, $personId, $hasPatient)
    {
        $this->showModal = true;
        $this->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'cd_pessoa_fisica' => $personId,
            'has_patient' => $hasPatient,
            'hospital_name' => $this->currentHospitalName ?? 'Hospital não identificado'
        ];
        $this->loadShiftMessages();
        if ($hasPatient) {
            $this->showPatientDetails($attendanceNumber);
        }
    }

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

    private function checkPatientAlerts($attendanceNumber)
    {
        if (!$this->currentPatient || empty($this->currentPatient['cd_pessoa_fisica'])) {
            Log::info("No patient data available for alerts check", [
                'attendance' => $attendanceNumber
            ]);
            return;
        }
        try {
            $clinicalModel = new \App\Repositories\EMR\PatientClinical();
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

    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->currentPatient = null;
        $this->patientDetails = null;
        $this->errorMessage = null;
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
        $this->shiftMessages = [];
        $this->chatMessagesCache = [];
        $this->skipRender(); 
    }

    public function onChatMessageReceived($event = null)
    {
        if ($event && isset($event['id'])) {
            $user = \App\Models\System\User::find($event['usuario_id']);
            $event['author'] = $user ? $user->name : 'Usuário';
            $event['role'] = $user ? $user->getRoleNames()->first() : '';
            $event['photo'] = $user ? $user->photo() : '';
            $event['time'] = \Carbon\Carbon::parse($event['created_at'] ?? $event['dt_criacao'])->format('H:i');
            $event['is_pinned'] = $event['is_fixed'] ?? false;

            // Only add if not already present (avoid duplicates)
            if (!collect($this->shiftMessages)->contains('id', $event['id'])) {
                $this->shiftMessages[] = $event;
                $this->shiftMessages = array_values($this->shiftMessages); 
            }
        }
        $this->messageLoading = false;
    }

    public function onChatMessagePinned($event = null)
    {
        if ($event && isset($event['id'])) {
            // Update the pinned status for the message
            foreach ($this->shiftMessages as &$msg) {
                if ($msg['id'] == $event['id']) {
                    $msg['is_pinned'] = $event['is_fixed'];
                    $msg['fixed_by'] = $event['fixed_by'];
                    $msg['fixed_at'] = $event['fixed_at'];
                } else {
                    $msg['is_pinned'] = false;
                }
            }
        }
    }

    public function loadShiftMessages()
    {
        $this->listenerEvent(); // Ensure listeners are set

        $cacheKey = $this->currentPatient['nr_atendimento'] . '_' . $this->currentShift;
        if (isset($this->chatMessagesCache[$cacheKey])) {
            $this->shiftMessages = $this->chatMessagesCache[$cacheKey];
        } else {
            $repo = new ChatRepository();
            $result = $repo->getMessages(
                $this->currentPatient['nr_atendimento'],
                $this->currentShift,
                $this->currentDate, // <-- FIX: pass date here!
                50
            );
            if (method_exists($result, 'items')) {
                $messages = $result->items();
            } elseif ($result instanceof \Illuminate\Pagination\LengthAwarePaginator || $result instanceof \Illuminate\Pagination\Paginator) {
                $messages = $result->toArray()['data'];
            } else {
                $messages = $result->toArray();
            }
            foreach ($messages as &$msg) {
                $user = \App\Models\System\User::find($msg['usuario_id']);
                $msg['author'] = $user ? $user->name : 'Usuário';
                $msg['role'] = $user ? $user->getRoleNames()->first() : '';
                $msg['photo'] = $user ? $user->photo() : '';
                $msg['time'] = \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i');
            }
            $this->shiftMessages = $messages;
            $this->chatMessagesCache[$cacheKey] = $messages;
        }
    }

    public function sendChatMessage()
    {
        if (empty(trim($this->newChatMessage))) {
            $this->messageLoading = false;
            return;
        }
        $this->messageLoading = true;
        $sessao = \App\Models\ChatSessao::firstOrCreate([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
        ], [
            'inicio' => now(),
            'encerrada' => false,
        ]);

        $repo = new \App\Repositories\MySQL\ChatRepository();
        $msg = $repo->storeMessage([
            'sessao_id' => $sessao->id,
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'usuario_id' => $this->currentUser['id'],
            'mensagem' => $this->newChatMessage,
            'dt_criacao' => now(),
        ]);

        // Optimistically add the message to the UI
        $this->shiftMessages[] = [
            'id' => $msg->id,
            'mensagem' => $msg->mensagem,
            'usuario_id' => $msg->usuario_id,
            'author' => $this->currentUser['name'],
            'photo' => $this->currentUser['photo'],
            'time' => now()->format('H:i'),
            'is_pinned' => false,
        ];

        $this->newChatMessage = '';
        $this->messageLoading = false;
        $this->skipRender();
    }

    public function closeChatSession()
    {
        $sessao = \App\Models\ChatSessao::where([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
            'encerrada' => false,
        ])->first();

        if ($sessao) {
            $sessao->fim = now();
            $sessao->encerrada = true;
            $sessao->save();

            \Log::debug('ChatSessao encerrada', [
                'sessao_id' => $sessao->id,
                'nr_atendimento' => $sessao->nr_atendimento,
                'turno_id' => $sessao->turno_id,
                'data_sessao' => $sessao->data_sessao,
                'fim' => $sessao->fim,
            ]);
        }
    }

    public function loadHistoryMessages($date, $shift)
    {
        // Atualiza propriedades de histórico
        $this->selectedHistoryDate = $date;
        $this->selectedHistoryShift = $shift;
        $this->viewingHistory = true;

        // Busca mensagens do histórico
        $repo = new \App\Repositories\MySQL\ChatRepository();
        $messages = $repo->getMessages(
            $this->currentPatient['nr_atendimento'],
            $shift,
            50
        )->items();

        $this->shiftMessages = $messages;
    }

    public function returnToCurrentShift()
    {
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        $this->loadShiftMessages();
    }

    public function listenerEvent()
    {
        
        return [
            "echo:chat.{$this->currentPatient['nr_atendimento']}.{$this->currentShift}" => 'openChatSession',
        ];
    }

    public function startMessage()
    {
        \Log::debug('Mensagem recebida');
    }

    public function openChatSession()
    {
        if (!$this->currentPatient || !$this->currentPatient['has_patient']) {
            \Log::debug('openChatSession: paciente inválido ou leito vazio', [
                'currentPatient' => $this->currentPatient
            ]);
            return;
        }
        
        $sessao = \App\Models\ChatSessao::firstOrCreate([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
        ], [
            'inicio' => now(),
            'encerrada' => false,
        ]);

        \Log::debug('ChatSessao aberta ao entrar na aba Avaliação', [
            'sessao_id' => $sessao->id,
            'nr_atendimento' => $sessao->nr_atendimento,
            'turno_id' => $sessao->turno_id,
            'data_sessao' => $sessao->data_sessao,
            'encerrada' => $sessao->encerrada,
        ]);
        
        $this->loadShiftMessages();
    }

    public function toggleMessagePin($messageId)
    {
        $sessaoId = \App\Models\ChatMensagem::find($messageId)->sessao_id;
        $fixedMsg = \App\Models\ChatMensagem::where('sessao_id', $sessaoId)->where('is_fixed', true)->first();

        if ($fixedMsg && $fixedMsg->id !== $messageId) {
            $this->dispatch('confirmPinSwap', [
                'currentFixedId' => $fixedMsg->id,
                'newPinId' => $messageId,
                'author' => $fixedMsg->usuario->name ?? '',
                'fixedAt' => $fixedMsg->fixed_at,
            ]);
            return;
        }

        $repo = new \App\Repositories\MySQL\ChatRepository();
        $repo->pinMessage($messageId, $this->currentUser['id']);
        $this->updateChatCache();
    }

    public function updateChatCache()
    {
        $repo = new ChatRepository();
        $result = $repo->getMessages(
            $this->currentPatient['nr_atendimento'],
            $this->currentShift,
            $this->currentDate, 
            50
        );
        if (method_exists($result, 'items')) {
            $messages = $result->items();
        } elseif ($result instanceof \Illuminate\Pagination\LengthAwarePaginator || $result instanceof \Illuminate\Pagination\Paginator) {
            $messages = $result->toArray()['data'];
        } else {
            $messages = $result->toArray();
        }
        foreach ($messages as &$msg) {
            $user = \App\Models\System\User::find($msg['usuario_id']);
            $msg['author'] = $user ? $user->name : 'Usuário';
            $msg['role'] = $user ? $user->getRoleNames()->first() : '';
            $msg['photo'] = $user ? $user->photo() : '';
            $msg['time'] = \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i');
        }
        $cacheKey = $this->currentPatient['nr_atendimento'] . '_' . $this->currentShift;
        $this->shiftMessages = $messages;
        $this->chatMessagesCache[$cacheKey] = $messages;
    }

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