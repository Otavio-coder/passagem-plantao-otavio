<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\EMR\Patient;
use App\Repositories\MySQL\ChatRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PatientModal extends Component
{
    // Propriedades públicas para controle do modal e dados do paciente
    public $showModal = false;
    public $currentPatient = null;
    public $currentHospitalName = null;
    public $patientDetails = null;
    public $loadingPatient = false;
    public $lastLoadedAttendanceNumber = null;

    public $patientAlerts = [];
    public $showAlertsModal = false;

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
    public $availableSessions = [];
    public $selectedSession = null;
    public $loadingMessages = false;

    public $availableDates = [];

    // Eventos escutados pelo Livewire
    protected $listeners = [
        'openPatientModal' => 'openModal',
        'chatMessageReceived' => 'onChatMessageReceived',
        'chatMessagePinned' => 'onChatMessagePinned',
        'loadSessionMessages' => 'loadSessionMessages',
        'loadPatientDetailsAsync' => 'loadPatientDetailsAsync',
    ];

    // Inicializa variáveis do usuário e turno
    public function mount()
    {
        $user = Auth::user();
        $this->currentUser = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() ?? '' : '',
            'photo' => method_exists($user, 'photo') ? $user->photo() ?? '' : '',
        ];
        $hour = now()->hour;
        $this->currentShift = ($hour >= 7 && $hour < 19) ? 'dia' : 'noite';
        $this->currentDate = now()->toDateString();
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
    }

    // Abre o modal do paciente e carrega dados iniciais
    public function openModal($patient, $currentHospitalName = null)
    {
        $this->showModal = true;
        $this->currentPatient = $patient;
        $this->currentHospitalName = $currentHospitalName;
        $this->loadingPatient = true;
        $this->patientDetails = null;

        $attendanceNumber = $patient['nr_atendimento'] ?? null;
        $this->lastLoadedAttendanceNumber = $attendanceNumber;

        // Carrega tudo de uma vez, sem dispatch redundante
        $this->loadAllPatientData($attendanceNumber);
    }

    private function loadAllPatientData($attendanceNumber)
    {
        $start = microtime(true);
        $this->loadPatientDetails($attendanceNumber);
        Log::debug('Tempo loadPatientDetails', ['t' => microtime(true) - $start]);
        $this->openChatSession();
        Log::debug('Tempo openChatSession', ['t' => microtime(true) - $start]);
        $this->loadShiftMessages();
        Log::debug('Tempo loadShiftMessages', ['t' => microtime(true) - $start]);
        $this->loadAvailableDates();
        Log::debug('Tempo loadAvailableDates', ['t' => microtime(true) - $start]);
    }

    // Fecha o modal e limpa variáveis
    public function closeModal()
    {
        $this->showModal = false;
        $this->currentPatient = null;
        $this->patientDetails = null;
        $this->shiftMessages = [];
        $this->chatMessagesCache = [];
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
        $this->lastLoadedAttendanceNumber = null; // <-- Limpa o último atendimento
    }

    public function loadPatientDetails($attendanceNumber)
    {
        $this->loadingPatient = true;
        $model = new Patient();
        $this->patientDetails = $model->getFullPatientData($attendanceNumber);
        $this->loadingPatient = false;
        $this->checkPatientAlerts($attendanceNumber);
    }

    // Verifica alertas do paciente (alergia, isolamento)
    private function checkPatientAlerts($attendanceNumber)
    {
        $this->patientAlerts = [];
        if (!empty($this->patientDetails)) {
            if (!empty($this->patientDetails->has_allergy)) {
                $this->patientAlerts[] = [
                    'type' => 'ALERGIA',
                    'message' => 'Paciente com alergia registrada',
                ];
            }
            if (!empty($this->patientDetails->has_isolation)) {
                $this->patientAlerts[] = [
                    'type' => 'ISOLAMENTO',
                    'message' => 'Paciente em isolamento',
                ];
            }
        }
        $this->showAlertsModal = !empty($this->patientAlerts);
    }

    // Fecha o modal de alertas
    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }

    // Manipula mensagens recebidas em tempo real (Echo/Pusher)
    public function onChatMessageReceived($event = null)
    {
        Log::debug('onChatMessageReceived chamado', ['event' => $event]);
        if (is_object($event)) $event = (array) $event;
        if (!$event || !isset($event['id']) || !isset($event['usuario_id'])) return;
        if ($this->viewingHistory) return;

        // Evita duplicidade
        foreach ($this->shiftMessages as $msg) {
            if (isset($msg['id']) && $msg['id'] == $event['id']) return;
        }

        $this->shiftMessages[] = [
            'id' => $event['id'],
            'mensagem' => $event['mensagem'] ?? '',
            'usuario_id' => $event['usuario_id'],
            'author' => $event['author'] ?? 'Usuário',
            'photo' => $event['photo'] ?? '',
            'time' => isset($event['created_at']) ? \Carbon\Carbon::parse($event['created_at'])->format('H:i') : '',
            'is_pinned' => $event['is_fixed'] ?? false,
            'is_fixed' => $event['is_fixed'] ?? false,
        ];
        $this->messageLoading = false;
    }

    // Atualiza status de mensagem fixada
    public function onChatMessagePinned($event = null)
    {
        if ($event && isset($event['id'])) {
            foreach ($this->shiftMessages as &$msg) {
                if ($msg['id'] == $event['id']) {
                    $msg['is_pinned'] = $event['is_fixed'];
                    $msg['is_fixed'] = $event['is_fixed'];
                    $msg['fixed_by'] = $event['fixed_by'];
                    $msg['fixed_at'] = $event['fixed_at'];
                } else {
                    $msg['is_pinned'] = false;
                    $msg['is_fixed'] = false;
                }
            }
        }
    }

    public function cachedPhoto($size = "64x64")
    {
        $cacheKey = "user_photo_{$this->username}_{$size}";
        return cache()->remember($cacheKey, 60 * 24, function () use ($size) {
            return $this->photo($size);
        });
    }

    // Carrega mensagens do turno atual
    public function loadShiftMessages()
    {
        $cacheKey = 'chat_messages_' . $this->currentPatient['nr_atendimento'] . '_' . $this->currentShift;
        $messages = Cache::remember($cacheKey, 60, function () {
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
            // Use os dados já carregados pelo with('usuario.roles')
            foreach ($messages as &$msg) {
                if (isset($msg['usuario']) && $msg['usuario']) {
                    $user = (object) $msg['usuario'];
                    $msg['author'] = $user->name ?? 'Usuário';
                    $msg['role'] = $user->roles[0]['name'] ?? '';
                    $msg['photo'] = method_exists($user, 'cachedPhoto') ? $user->cachedPhoto() : '';
                } else {
                    $user = \App\Models\System\User::find($msg['usuario_id']);
                    $msg['author'] = $user ? $user->name : 'Usuário';
                    $msg['role'] = $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() : '';
                    $msg['photo'] = $user && method_exists($user, 'cachedPhoto') ? $user->cachedPhoto() : '';
                }
                $msg['time'] = \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i');
            }
            return $messages;
        });
        $this->shiftMessages = $messages;
        $this->chatMessagesCache[$cacheKey] = $messages;
    }

    // Envia nova mensagem no chat
    public function sendChatMessage()
    {
        if ($this->viewingHistory) return;
        $msg = trim($this->newChatMessage);
        if (empty($msg)) {
            $this->messageLoading = false;
            return;
        }
        $this->messageLoading = true;

        $sessao = \App\Models\ChatSessao::where([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
            'encerrada' => false,
        ])->first();

        if (!$sessao) {
            $this->messageLoading = false;
            return;
        }

        $repo = new ChatRepository();
        $repo->storeMessage([
            'sessao_id' => $sessao->id,
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'usuario_id' => $this->currentUser['id'],
            'mensagem' => $msg,
            'dt_criacao' => now(),
        ]);

        $this->newChatMessage = '';
        $this->messageLoading = false;
        $this->skipRender();
    }

    // Encerra a sessão de chat do turno
    public function closeChatSession()
    {
        $sessao = \App\Models\ChatSessao::where([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
            'encerrada' => false,
        ])->first();

        if ($sessao) {
            \App\Models\ChatMensagem::where('sessao_id', $sessao->id)
                ->where('is_fixed', true)
                ->update(['is_fixed' => false, 'fixed_by' => null, 'fixed_at' => null]);

            $sessao->fim = now();
            $sessao->encerrada = true;
            $sessao->save();
        }
    }

    // Carrega mensagens históricas de outro turno/data
    public function loadHistoryMessages($date, $shift)
    {
        Log::debug('[PatientModal] loadHistoryMessages called', [
            'date' => $date,
            'shift' => $shift,
            'patient' => $this->currentPatient['nr_atendimento'] ?? null,
        ]);
        $repo = new ChatRepository();
        $result = $repo->getMessages(
            $this->currentPatient['nr_atendimento'],
            $shift,
            $date,
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
            if (isset($msg['usuario']) && $msg['usuario']) {
                $user = (object) $msg['usuario'];
                $msg['author'] = $user->name ?? 'Usuário';
                $msg['role'] = $user->roles[0]['name'] ?? '';
                $msg['photo'] = method_exists($user, 'cachedPhoto') ? $user->cachedPhoto() : '';
            } else {
                $user = \App\Models\System\User::find($msg['usuario_id']);
                $msg['author'] = $user ? $user->name : 'Usuário';
                $msg['role'] = $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() : '';
                $msg['photo'] = $user && method_exists($user, 'cachedPhoto') ? $user->cachedPhoto() : '';
            }
            $msg['time'] = \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i');
        }

        $this->shiftMessages = $messages;
    }

    // Retorna para o turno atual após visualizar histórico
    public function returnToCurrentShift()
    {
        $this->loadingMessages = true;
        $this->selectedSession = $this->currentDate . '|' . $this->currentShift;
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        $this->loadShiftMessages();
        $this->loadingMessages = false;
    }

    // Mostra detalhes do paciente se necessário
    public function showPatientDetails($attendanceNumber)
        {
            if ($attendanceNumber !== $this->lastLoadedAttendanceNumber) {
                $this->loadPatientDetails($attendanceNumber);
                $this->lastLoadedAttendanceNumber = $attendanceNumber;
            }
        }

        // Abre ou cria sessão de chat para o paciente/turno
        public function openChatSession()
    {
        if (
            !$this->currentPatient ||
            !$this->currentPatient['has_patient'] ||
            empty($this->currentPatient['nr_atendimento']) ||
            empty($this->currentShift) ||
            empty($this->currentDate)
        ) {
            Log::error('openChatSession: Parâmetros obrigatórios ausentes', [
                'nr_atendimento' => $this->currentPatient['nr_atendimento'] ?? null,
                'turno_id' => $this->currentShift ?? null,
                'data_sessao' => $this->currentDate ?? null,
            ]);
            return;
        }

        // Fecha sessões antigas
        \App\Models\ChatSessao::where('nr_atendimento', $this->currentPatient['nr_atendimento'])
            ->where('encerrada', false)
            ->where(function($query) {
                $query->where('turno_id', '!=', $this->currentShift)
                    ->orWhere('data_sessao', '!=', $this->currentDate);
            })
            ->get()
            ->each(function($sessao) {
                $sessao->fecharSessao();
            });

        // ATÔMICO: cria ou retorna a sessão, tolerando concorrência
        try {
            \App\Models\ChatSessao::firstOrCreate(
                [
                    'nr_atendimento' => $this->currentPatient['nr_atendimento'],
                    'turno_id' => $this->currentShift,
                    'data_sessao' => $this->currentDate,
                    'encerrada' => false,
                ],
                [
                    'inicio' => now(),
                    'encerrada' => false,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Se for erro de duplicidade, apenas busca a sessão existente
            if ($e->getCode() == 23000) {
                \App\Models\ChatSessao::where([
                    'nr_atendimento' => $this->currentPatient['nr_atendimento'],
                    'turno_id' => $this->currentShift,
                    'data_sessao' => $this->currentDate,
                    'encerrada' => false,
                ])->first();
            } else {
                throw $e;
            }
        }
    }

    // Alterna fixação de mensagem no chat
    public function toggleMessagePin($messageId)
    {
        $msg = \App\Models\ChatMensagem::find($messageId);
        if (!$msg) return;

        $sessaoId = $msg->sessao_id;
        $fixedMsg = \App\Models\ChatMensagem::where('sessao_id', $sessaoId)->where('is_fixed', true)->first();

        if ($fixedMsg && $fixedMsg->id !== $messageId) {
            $this->dispatch('show-pin-confirm', [
                'newPinId' => $messageId,
                'currentFixedId' => $fixedMsg->id,
            ]);
            return;
        }

        $repo = new \App\Repositories\MySQL\ChatRepository();
        $repo->pinMessage($messageId, $this->currentUser['id']);
    }

    // Força fixação de mensagem (após confirmação)
    public function forcePinMessage($messageId)
    {
        $repo = new \App\Repositories\MySQL\ChatRepository();
        $repo->pinMessage($messageId, $this->currentUser['id']);
    }

    // Carrega datas/sessões disponíveis para histórico
    public function loadAvailableDates()
    {
        $this->availableSessions = [];
        if (!$this->currentPatient || empty($this->currentPatient['nr_atendimento'])) {
            return;
        }

        $sessoes = \App\Models\ChatSessao::where('nr_atendimento', $this->currentPatient['nr_atendimento'])
            ->orderBy('data_sessao', 'desc')
            ->orderBy('turno_id', 'desc')
            ->get();

        // --- OTIMIZAÇÃO: Busca contagem de mensagens em lote ---
        $sessaoIds = $sessoes->pluck('id')->all();
        $msgCounts = \App\Models\ChatMensagem::whereIn('sessao_id', $sessaoIds)
            ->selectRaw('sessao_id, count(*) as total')
            ->groupBy('sessao_id')
            ->pluck('total', 'sessao_id');

        foreach ($sessoes as $sessao) {
            $msgCount = $msgCounts[$sessao->id] ?? 0;
            $isCurrent = $sessao->data_sessao === $this->currentDate && $sessao->turno_id === $this->currentShift;
            if ($msgCount > 0 && !$isCurrent) {
                $label = \Carbon\Carbon::parse($sessao->data_sessao)->format('d/m') . ' - ' . ucfirst($sessao->turno_id) . " ({$msgCount} msg)";
                $this->availableSessions[] = [
                    'key' => $sessao->data_sessao . '|' . $sessao->turno_id,
                    'date' => $sessao->data_sessao,
                    'turno' => $sessao->turno_id,
                    'label' => $label,
                    'count' => $msgCount,
                ];
            }
        }
        $this->selectedSession = null;
        $currentKey = $this->currentDate . '|' . $this->currentShift;
        $this->selectedSession = collect($this->availableSessions)->firstWhere('key', $currentKey)['key'] ?? null;
    }

    // Atualiza mensagens ao trocar sessão no dropdown
    public function updatedSelectedSession($value)
    {
        Log::debug('Dropdown de sessão alterado', ['value' => $value]);
        if (!$value) return;
        [$date, $turno] = explode('|', $value);
        $this->selectedHistoryDate = $date;
        $this->selectedHistoryShift = $turno;
        $this->viewingHistory = true;
        $this->loadingMessages = true;
        $this->loadHistoryMessages($date, $turno);
        $this->loadingMessages = false;
    }

    // Aplica filtro de sessão selecionada
    public function applySessionFilter()
    {
        if (!$this->selectedSession) return;
        $this->loadingMessages = true;
        $this->dispatch('loadSessionMessages');
    }

    // Carrega mensagens da sessão selecionada
    public function loadSessionMessages()
    {
        if (!$this->selectedSession) return;
        [$date, $turno] = explode('|', $this->selectedSession);
        $this->selectedHistoryDate = $date;
        $this->selectedHistoryShift = $turno;
        $this->viewingHistory = true;
        $this->loadHistoryMessages($date, $turno);
        $this->loadingMessages = false;
    }

    // Atualiza mensagens ao trocar data do histórico
    public function updatedSelectedHistoryDate($value)
    {
        $this->loadingMessages = true;
        $this->loadHistoryMessages($this->selectedHistoryDate, $this->selectedHistoryShift);
        $this->loadingMessages = false;
    }

    // Atualiza mensagens ao trocar turno do histórico
    public function updatedSelectedHistoryShift($value)
    {
        $this->loadingMessages = true;
        $this->loadHistoryMessages($this->selectedHistoryDate, $this->selectedHistoryShift);
        $this->loadingMessages = false;
    }

    // Atualiza cache de mensagens do chat
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
            if (isset($msg['usuario']) && $msg['usuario']) {
                $user = (object) $msg['usuario'];
                $msg['author'] = $user->name ?? 'Usuário';
                $msg['role'] = $user->roles[0]['name'] ?? '';
                $msg['photo'] = method_exists($user, 'cachedPhoto') ? $user->cachedPhoto() : '';
            } else {
                $user = \App\Models\System\User::find($msg['usuario_id']);
                $msg['author'] = $user ? $user->name : 'Usuário';
                $msg['role'] = $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() : '';
                $msg['photo'] = $user && method_exists($user, 'cachedPhoto') ? $user->cachedPhoto() : '';
            }
            $msg['time'] = \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i');
        }
        $cacheKey = $this->currentPatient['nr_atendimento'] . '_' . $this->currentShift;
        $this->shiftMessages = $messages;
        $this->chatMessagesCache[$cacheKey] = $messages;
    }

    // Renderiza o componente Livewire
    public function render()
    {
        return view('livewire.patient-modal', [
            'availableSessions' => $this->availableSessions,
            'selectedSession' => $this->selectedSession,
            'loadingMessages' => $this->loadingMessages,
            'currentDate' => $this->currentDate, 
            'currentShift' => $this->currentShift, 
        ]);
    }
}