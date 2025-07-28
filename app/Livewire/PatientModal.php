<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\EMR\Patient;
use App\Models\EMR\BedUnit;
use App\Models\EMR\Sector;
use App\Models\EMR\Hospital;
use App\Repositories\MySQL\ChatRepository;
use Illuminate\Support\Facades\Log;

class PatientModal extends Component
{
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

    protected $listeners = [
        'openPatientModal' => 'openModal',
        'chatMessageReceived' => 'onChatMessageReceived',
        'chatMessagePinned' => 'onChatMessagePinned',
        'loadSessionMessages' => 'loadSessionMessages',
        'loadPatientDetailsAsync' => 'loadPatientDetailsAsync',
    ];

    public function mount()
    {
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
    }

    
    public function openModal($patient, $currentHospitalName = null)
    {
        $this->showModal = true;
        $this->currentPatient = $patient;
        $this->currentHospitalName = $currentHospitalName;
        $this->loadingPatient = true;
        $this->patientDetails = null;

        $attendanceNumber = $patient['nr_atendimento'] ?? null;
        $this->dispatch('loadPatientDetailsAsync', $attendanceNumber);
        $this->lastLoadedAttendanceNumber = $attendanceNumber;

        $this->openChatSession();
        $this->loadShiftMessages();
        $this->loadAvailableDates();
    }

    
    public function loadPatientDetailsAsync($attendanceNumber)
    {
        $this->loadPatientDetails($attendanceNumber);
        $this->lastLoadedAttendanceNumber = $attendanceNumber;
    }

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

    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
    }

    public function onChatMessageReceived($event = null)
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        // Clear cache for current session
        $cacheKey = $this->currentPatient['nr_atendimento'] . '_' . $this->currentShift;
        unset($this->chatMessagesCache[$cacheKey]);

        if (!$this->viewingHistory) {
            $this->updateChatCache();
        }
        $this->messageLoading = false;
    }

    public function onChatMessagePinned($event = null)
    {
        if ($event && isset($event['id'])) {
            foreach ($this->shiftMessages as &$msg) {
                $user = \App\Models\System\User::find($msg['usuario_id']);
                $msg['author'] = $user ? $user->name : 'Usuário';
                $msg['role'] = $user ? $user->getRoleNames()->first() : '';
                $msg['photo'] = $user ? $user->photo() : '';
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
        $cacheKey = $this->currentPatient['nr_atendimento'] . '_' . $this->currentShift;
        if (isset($this->chatMessagesCache[$cacheKey])) {
            $this->shiftMessages = $this->chatMessagesCache[$cacheKey];
        } else {
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
            $this->shiftMessages = $messages;
            $this->chatMessagesCache[$cacheKey] = $messages;
        }
    }

    public function sendChatMessage()
    {
        if ($this->viewingHistory) {
            // Bloqueia envio de mensagem no histórico
            return;
        }
        if (empty(trim($this->newChatMessage))) {
            $this->messageLoading = false;
            return;
        }
        $this->messageLoading = true;

        // Busca a sessão aberta do turno/data atual
        $sessao = \App\Models\ChatSessao::where([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
            'encerrada' => false,
        ])->first();

        if (!$sessao) {
            // Não deve criar sessão automaticamente aqui, só via openChatSession
            $this->messageLoading = false;
            return;
        }

        $repo = new ChatRepository();
        $msg = $repo->storeMessage([
            'sessao_id' => $sessao->id,
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'usuario_id' => $this->currentUser['id'],
            'mensagem' => $this->newChatMessage,
            'dt_criacao' => now(),
        ]);

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
            \App\Models\ChatMensagem::where('sessao_id', $sessao->id)
                ->where('is_fixed', true)
                ->update(['is_fixed' => false, 'fixed_by' => null, 'fixed_at' => null]);

            $sessao->fim = now();
            $sessao->encerrada = true;
            $sessao->save();
        }
    }

    public function loadHistoryMessages($date, $shift)
    {
        Log::debug('[PatientModal] loadHistoryMessages called', [
            'date' => $date,
            'shift' => $shift,
            'patient' => $this->currentPatient['nr_atendimento'] ?? null,
        ]);
        // Remova o set de $this->loadingMessages aqui, pois já está nos métodos chamadores
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

        Log::debug('[PatientModal] Mensagens carregadas', [
            'count' => count($messages),
            'date' => $date,
            'shift' => $shift,
        ]);

        foreach ($messages as &$msg) {
            $user = \App\Models\System\User::find($msg['usuario_id']);
            $msg['author'] = $user ? $user->name : 'Usuário';
            $msg['role'] = $user ? $user->getRoleNames()->first() : '';
            $msg['photo'] = $user ? $user->photo() : '';
            $msg['time'] = \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i');
        }

        $this->shiftMessages = $messages;
        // Remova o set de $this->loadingMessages aqui
    }

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

    public function showPatientDetails($attendanceNumber)
    {
        // Só carrega se mudou
        if ($attendanceNumber !== $this->lastLoadedAttendanceNumber) {
            $this->loadPatientDetails($attendanceNumber);
            $this->lastLoadedAttendanceNumber = $attendanceNumber;
        }
    }

    public function openChatSession()
    {
        if (!$this->currentPatient || !$this->currentPatient['has_patient']) {
            return;
        }

        // Fecha todas as sessões abertas deste paciente que não sejam do turno/data atual
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

        // Garante que só exista uma sessão aberta para o turno/data atual
        $sessao = \App\Models\ChatSessao::where([
            'nr_atendimento' => $this->currentPatient['nr_atendimento'],
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
            'encerrada' => false,
        ])->first();

        if (!$sessao) {
            $sessao = \App\Models\ChatSessao::create([
                'nr_atendimento' => $this->currentPatient['nr_atendimento'],
                'turno_id' => $this->currentShift,
                'data_sessao' => $this->currentDate,
                'inicio' => now(),
                'encerrada' => false,
            ]);
        }
    }

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

        // Update local state instantly for immediate UI feedback
        foreach ($this->shiftMessages as &$m) {
            $m['is_pinned'] = ($m['id'] == $messageId);
            $m['is_fixed'] = ($m['id'] == $messageId) ? 1 : 0;
        }

        $this->dispatch('scroll-to-message', ['messageId' => $messageId]);
    }

    public function forcePinMessage($messageId)
    {
        $repo = new \App\Repositories\MySQL\ChatRepository();
        $repo->pinMessage($messageId, $this->currentUser['id']);

        // Update local state instantly for immediate UI feedback
        foreach ($this->shiftMessages as &$m) {
            $m['is_pinned'] = ($m['id'] == $messageId);
            $m['is_fixed'] = ($m['id'] == $messageId) ? 1 : 0;
        }

        $this->dispatch('scroll-to-message', ['messageId' => $messageId]);
    }

    public $availableDates = [];

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

        foreach ($sessoes as $sessao) {
            $msgCount = $sessao->mensagens()->count();
            if ($msgCount > 0) {
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

        // Seleciona a sessão atual por padrão
        $currentKey = $this->currentDate . '|' . $this->currentShift;
        $this->selectedSession = collect($this->availableSessions)->firstWhere('key', $currentKey)['key'] ?? null;
    }

    public function updatedSelectedSession($value)
    {
        Log::debug('Dropdown de sessão alterado', ['value' => $value]);
        if (!$value) return;
        [$date, $turno] = explode('|', $value);
        $this->selectedHistoryDate = $date;
        $this->selectedHistoryShift = $turno;
        $this->viewingHistory = true;
        $this->loadingMessages = true; // <-- Adicionado
        $this->loadHistoryMessages($date, $turno);
        $this->loadingMessages = false; // <-- Adicionado
    }

    public function applySessionFilter()
    {
        if (!$this->selectedSession) return;
        $this->loadingMessages = true;
        // Dispara o carregamento real no próximo ciclo
        $this->dispatch('loadSessionMessages');
    }

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

    public function updatedSelectedHistoryDate($value)
    {
        $this->loadingMessages = true; // <-- Adicionado
        $this->loadHistoryMessages($this->selectedHistoryDate, $this->selectedHistoryShift);
        $this->loadingMessages = false; // <-- Adicionado
    }

    public function updatedSelectedHistoryShift($value)
    {
        $this->loadingMessages = true; // <-- Adicionado
        $this->loadHistoryMessages($this->selectedHistoryDate, $this->selectedHistoryShift);
        $this->loadingMessages = false; // <-- Adicionado
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

    public function render()
    {
        return view('livewire.patient-modal', [
            'availableSessions' => $this->availableSessions,
            'selectedSession' => $this->selectedSession,
            'loadingMessages' => $this->loadingMessages,
        ]);
    }
}