<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Repositories\EMR\SbarReport as SbarReportModel;
use App\Repositories\MySQL\ChatRepository;

class PatientModal extends Component
{
    public $showModal = false;
    public $currentPatient = null;
    public $currentHospitalName = null;
    public $patientDetails = null;
    public $loadingPatient = false;

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

    protected $listeners = [
        'openPatientModal' => 'openModal',
        'chatMessageReceived' => 'onChatMessageReceived',
        'chatMessagePinned' => 'onChatMessagePinned',
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
        $this->loadPatientDetails($patient['nr_atendimento']);
        $this->loadShiftMessages();
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
    }

    public function loadPatientDetails($attendanceNumber)
    {
        $this->loadingPatient = true;
        $model = new SbarReportModel();
        $this->patientDetails = $model->getPatientDetails($attendanceNumber);
        $this->loadingPatient = false;
        $this->checkPatientAlerts($attendanceNumber);
    }

    private function checkPatientAlerts($attendanceNumber)
    {
        $clinicalModel = new \App\Repositories\EMR\PatientClinical();
        $this->patientAlerts = $clinicalModel->getPatientActiveAlerts(
            $attendanceNumber,
            $this->currentPatient['cd_pessoa_fisica']
        );
        $this->showAlertsModal = !empty($this->patientAlerts);
    }

    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        $this->patientAlerts = [];
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
            $sessao->fim = now();
            $sessao->encerrada = true;
            $sessao->save();
        }
    }

    public function loadHistoryMessages($date, $shift)
    {
        $this->selectedHistoryDate = $date;
        $this->selectedHistoryShift = $shift;
        $this->viewingHistory = true;

        $repo = new ChatRepository();
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

    public function showPatientDetails($attendanceNumber)
    {
        $this->loadPatientDetails($attendanceNumber);
    }

    public function openChatSession()
    {
        if (!$this->currentPatient || !$this->currentPatient['has_patient']) {
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

        $repo = new ChatRepository();
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

    public function render()
    {
        return view('livewire.patient-modal');
    }
}