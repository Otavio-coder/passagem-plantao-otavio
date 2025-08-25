<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatSessao;
use App\Models\ChatMensagem;
use App\Repositories\MySQL\ChatRepository;
use App\Services\ChatAuditoriaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatComponent extends Component
{
    public $patientId;
    public $bedUnit;
    public $currentShift;
    public $currentDate;
    
    public $messages = [];
    public $newMessage = '';
    public $viewingHistory = false;
    public $availableSessions = [];
    public $groupedSessions = [];
    public $selectedSession = null;
    
    // Paginação do histórico
    public $currentHistoryPage = 0;
    public $totalHistoryPages = 1;
    public $sessionsPerPage = 10; // Dias por página (controla quantos dias aparecem por vez)
    
    public $loadingMessages = false;
    public $sendingMessage = false;
    public $loadingHistory = false;
    
    public $currentUser;
    public $isShiftClosed = false;
    
    public $initialized = false;
    private $currentSessionId = null;
    private $userCache = [];
    private $photoCache = [];

    protected $listeners = [
        'onChatMessageReceived' => 'handleNewMessage',
        'onChatMessagePinned' => 'handleMessagePinned',
        'initChat' => 'initialize',
        'refreshChat' => 'refreshCurrentMessages',
    ];

    // Propriedade computada para Alpine.js
    public function getTotalHistoryPagesProperty()
    {
        return $this->totalHistoryPages;
    }

    public function mount($patientId = null, $bedUnit = null)
    {
        $this->patientId = $patientId;
        $this->bedUnit = $bedUnit;
        
        $user = Auth::user();
        $this->currentUser = [
            'id' => $user->id,
            'name' => $user->name,
            'photo' => $this->getUserPhotoBase64($user),
        ];
        
        $this->setCurrentShift();
        $this->currentDate = now()->toDateString();
        $this->isShiftClosed = $this->checkIfShiftClosed();
    }

    public function initialize()
    {
        if ($this->initialized || !$this->patientId) return;
        
        try {
            $this->loadingMessages = true;
            
            $this->openChatSession();
            $this->loadMessages();
            $this->loadAvailableSessions(); // Carrega primeira página automaticamente
            
            $this->initialized = true;
            
            $this->dispatch('chat-initialized', [
                'patientId' => $this->patientId,
                'shift' => $this->currentShift,
                'componentId' => $this->getId(),
                'totalHistoryPages' => $this->totalHistoryPages
            ]);
            
            Log::info('[Chat] Inicializado', [
                'patient_id' => $this->patientId,
                'shift' => $this->currentShift,
                'history_pages' => $this->totalHistoryPages
            ]);
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro na inicialização:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->loadingMessages = false;
        }
    }

    // Método sendMessage simplificado e otimizado
    public function sendMessage($messageContent = null)
    {
        if ($this->viewingHistory || $this->isShiftClosed) {
            return ['success' => false, 'error' => 'Operação não permitida'];
        }
        
        if ($this->sendingMessage) {
            return ['success' => false, 'error' => 'Aguarde o envio anterior'];
        }
        
        $this->sendingMessage = true;
        
        try {
            $message = $messageContent ?? trim($this->newMessage);
            $this->newMessage = '';
            
            if (empty($message)) {
                return ['success' => false, 'error' => 'Mensagem vazia'];
            }

            // Garante que temos uma sessão
            $sessaoId = $this->getCurrentSessionId();
            if (!$sessaoId) {
                $this->openChatSession();
                $sessaoId = $this->getCurrentSessionId();
            }

            // Cria a mensagem via repositório
            $repo = new ChatRepository();
            $newMessage = $repo->storeMessage([
                'sessao_id' => $sessaoId,
                'nr_atendimento' => $this->patientId,
                'turno_id' => $this->currentShift,
                'usuario_id' => $this->currentUser['id'],
                'mensagem' => $message,
                'dt_criacao' => now(),
            ], $this->patientId, $this->currentShift);

            if (!$newMessage) {
                throw new \Exception('Falha ao criar mensagem');
            }

            // Adiciona localmente a mensagem (otimização de UI)
            $this->addMessageToLocal([
                'id' => $newMessage->id,
                'mensagem' => $message,
                'usuario_id' => $this->currentUser['id'],
                'dt_criacao' => now()->toISOString(),
                'is_fixed' => false,
            ]);
            
            $this->dispatch('scroll-to-bottom');

            Log::info('[Chat] Mensagem enviada', [
                'id' => $newMessage->id,
                'user' => $this->currentUser['name']
            ]);

            return [
                'success' => true,
                'message_id' => $newMessage->id
            ];
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao enviar mensagem', [
                'error' => $e->getMessage(),
                'patient_id' => $this->patientId
            ]);
            
            return ['success' => false, 'error' => 'Erro ao enviar mensagem'];
        } finally {
            $this->sendingMessage = false;
        }
    }

    // Método toggleMessagePin simplificado e mais robusto
    public function toggleMessagePin($messageId)
    {
        if ($this->viewingHistory || $this->isShiftClosed || !$this->patientId || !$messageId) {
            return ['success' => false, 'error' => 'Operação não permitida'];
        }
        
        try {
            $repo = new ChatRepository();
            $updatedMessage = $repo->pinMessage($messageId, $this->currentUser['id']);
            
            if (!$updatedMessage) {
                return ['success' => false, 'error' => 'Mensagem não encontrada'];
            }
            
            // Atualiza localmente (otimização de UI)
            $this->updateLocalMessagePin($messageId, $updatedMessage->is_fixed);
            
            Log::info('[Chat] Pin alterado', [
                'message_id' => $messageId,
                'fixed' => $updatedMessage->is_fixed,
                'user' => $this->currentUser['name']
            ]);
            
            return ['success' => true, 'is_fixed' => $updatedMessage->is_fixed];
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao alterar pin', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => 'Erro ao fixar mensagem'];
        }
    }

    // Método loadSessionHistory otimizado
    public function loadSessionHistory()
    {
        if (!$this->selectedSession || $this->loadingHistory || !$this->patientId) {
            return ['success' => false, 'error' => 'Parâmetros inválidos'];
        }
        
        $this->loadingHistory = true;
        
        try {
            $parts = explode('|', $this->selectedSession);
            if (count($parts) !== 2) {
                throw new \InvalidArgumentException('Formato de sessão inválido');
            }
            
            [$date, $shift] = $parts;
            
            $this->viewingHistory = true;
            
            $session = ChatSessao::where([
                'nr_atendimento' => $this->patientId,
                'data_sessao' => $date,
                'turno_id' => $shift
            ])->first();
            
            if (!$session) {
                throw new \Exception('Sessão não encontrada');
            }
            
            $messages = ChatMensagem::where('sessao_id', $session->id)
                ->where('is_deleted', false)
                ->orderBy('dt_criacao', 'asc')
                ->limit(100)
                ->get();

            $this->messages = $this->formatMessages($messages->toArray());
            
            // Registra auditoria
            ChatAuditoriaService::registrarAcessoHistorico($this->patientId, $shift, $date);
            
            $this->dispatch('history-loaded', [
                'date' => $date,
                'shift' => $shift,
                'messageCount' => count($this->messages)
            ]);
            
            Log::info('[Chat] Histórico carregado', [
                'patient_id' => $this->patientId,
                'date' => $date,
                'shift' => $shift,
                'messages' => count($this->messages)
            ]);
            
            return ['success' => true, 'messageCount' => count($this->messages)];
            
        } catch (\Exception $e) {
            $this->messages = [];
            $this->viewingHistory = false;
            
            $this->dispatch('history-error', [
                'message' => 'Erro ao carregar histórico'
            ]);
            
            Log::error('[Chat] Erro ao carregar histórico', [
                'patient_id' => $this->patientId,
                'session' => $this->selectedSession,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => 'Erro ao carregar histórico'];
        } finally {
            $this->loadingHistory = false;
        }
    }

    public function clearSessionSelection()
    {
        $this->selectedSession = null;
        $this->returnToCurrentShift();
    }

    public function returnToCurrentShift()
    {
        if (!$this->viewingHistory) return;
        
        $this->viewingHistory = false;
        $this->selectedSession = null;
        $this->refreshCurrentMessages();
        
        $this->dispatch('returned-to-current', [
            'shift' => $this->currentShift,
            'date' => $this->currentDate
        ]);
        
        Log::info('[Chat] Retornou ao turno atual', [
            'patient_id' => $this->patientId,
            'shift' => $this->currentShift
        ]);
    }

    public function refreshCurrentMessages()
    {
        if ($this->loadingMessages || $this->viewingHistory) return;
        
        $this->loadingMessages = true;
        try {
            $this->loadMessages();
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao atualizar mensagens:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->loadingMessages = false;
        }
    }

    public function loadHistoryPage($page = 0)
    {
        if (!$this->patientId) return;
        
        $this->currentHistoryPage = max(0, $page);
        $this->loadingHistory = true;
        
        try {
            $this->loadAvailableSessions();
            
            Log::info('[Chat] Página do histórico carregada', [
                'patient_id' => $this->patientId,
                'page' => $this->currentHistoryPage,
                'total_pages' => $this->totalHistoryPages
            ]);
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao carregar página do histórico', [
                'patient_id' => $this->patientId,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->loadingHistory = false;
        }
    }

    // Handler otimizado para mensagens WebSocket
    public function handleNewMessage($data)
    {
        // Validações básicas
        if (!$this->initialized || $this->viewingHistory || !$data || !isset($data['id'])) {
            return;
        }
        
        // Verifica se é para o contexto atual
        if (($data['nr_atendimento'] ?? null) != $this->patientId || 
            ($data['turno_id'] ?? null) != $this->currentShift) {
            return;
        }
        
        // Verifica se a mensagem já existe
        if ($this->messageExists($data['id'])) {
            return;
        }
        
        // Adiciona a mensagem
        $this->addMessageToLocal($data);
        
        Log::info('[Chat] Mensagem recebida via broadcast', [
            'message_id' => $data['id']
        ]);
        
        $this->dispatch('scroll-to-bottom');
    }

    // Handler otimizado para PINs WebSocket
    public function handleMessagePinned($data)
    {
        // Validações básicas
        if (!$this->initialized || !$data || !isset($data['id'])) {
            return;
        }
        
        // Verifica se é para o contexto atual
        if (($data['nr_atendimento'] ?? null) != $this->patientId || 
            ($data['turno_id'] ?? null) != $this->currentShift) {
            return;
        }
        
        $messageId = $data['id'];
        $isPinned = $data['is_fixed'] ?? false;
        
        // Atualiza o estado local das mensagens
        $this->updateLocalMessagePin($messageId, $isPinned);
        
        Log::info('[Chat] Pin alterado via broadcast', [
            'message_id' => $messageId,
            'is_fixed' => $isPinned
        ]);
    }

    // Método auxiliar para verificar se mensagem existe
    private function messageExists($messageId)
    {
        return collect($this->messages)->contains('id', $messageId);
    }

    // Método auxiliar para adicionar mensagem localmente
    private function addMessageToLocal($data)
    {
        $message = $this->formatSingleMessage([
            'id' => $data['id'],
            'mensagem' => $data['mensagem'] ?? '',
            'usuario_id' => $data['usuario_id'] ?? null,
            'dt_criacao' => $data['created_at'] ?? $data['dt_criacao'] ?? now()->toISOString(),
            'is_fixed' => $data['is_fixed'] ?? false,
        ]);
        
        $this->messages[] = $message;
    }

    private function loadMessages()
    {
        if (!$this->patientId) return;

        try {
            $session = ChatSessao::where([
                'nr_atendimento' => $this->patientId,
                'turno_id' => $this->currentShift,
                'data_sessao' => $this->currentDate
            ])->first();
            
            if (!$session) {
                $this->messages = [];
                return;
            }
            
            $messages = ChatMensagem::where('sessao_id', $session->id)
                ->where('is_deleted', false)
                ->orderBy('dt_criacao', 'asc')
                ->limit(50)
                ->get();

            $this->messages = $this->formatMessages($messages->toArray());
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao carregar mensagens:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage()
            ]);
            $this->messages = [];
        }
    }

    private function loadAvailableSessions()
    {
        if (!$this->patientId) return;
        
        try {
            // Busca todas as sessões (sem cache para paginação dinâmica)
            $allSessions = ChatSessao::select([
                'id',
                'data_sessao',
                'turno_id',
                'total_mensagens',
                'fim'
            ])
            ->where('nr_atendimento', $this->patientId)
            ->where('total_mensagens', '>', 0)
            ->where(function($query) {
                $query->where('data_sessao', '!=', $this->currentDate)
                    ->orWhere(function($q) {
                        $q->where('data_sessao', $this->currentDate)
                            ->where('turno_id', '!=', $this->currentShift);
                    });
            })
            ->orderBy('data_sessao', 'desc')
            ->orderBy('turno_id', 'desc')
            ->get();

            // Agrupa por data
            $sessionsByDate = $allSessions->groupBy('data_sessao')->map(function($sessions, $date) {
                return $sessions->map(function($session) use ($date) {
                    $carbonDate = \Carbon\Carbon::parse($session->data_sessao);
                    $key = $session->data_sessao . '|' . $session->turno_id;
                    
                    $shiftLabel = match($session->turno_id) {
                        'manha' => 'Manhã',
                        'tarde' => 'Tarde', 
                        'noite' => 'Noite',
                        default => ucfirst($session->turno_id)
                    };
                    
                    return [
                        'key' => $key,
                        'date' => $session->data_sessao,
                        'turno' => $session->turno_id,
                        'shift_label' => $shiftLabel,
                        'messageCount' => $session->total_mensagens,
                        'isCompleted' => $session->fim !== null,
                    ];
                })->toArray();
            });

            // Calcula paginação (por datas)
            $totalDates = $sessionsByDate->count();
            $this->totalHistoryPages = max(1, ceil($totalDates / $this->sessionsPerPage));
            
            // Garante que a página atual é válida
            $this->currentHistoryPage = min($this->currentHistoryPage, $this->totalHistoryPages - 1);
            $this->currentHistoryPage = max(0, $this->currentHistoryPage);
            
            // Pega apenas as datas da página atual
            $offset = $this->currentHistoryPage * $this->sessionsPerPage;
            $pagedDates = $sessionsByDate->slice($offset, $this->sessionsPerPage);
            
            // Formata para o frontend com labels corretos
            $this->groupedSessions = [];
            $pagedDates->each(function($sessions, $date) {
                $carbonDate = \Carbon\Carbon::parse($date);
                $dateLabel = $carbonDate->format('d/m/Y');
                
                // Adiciona dia da semana se for recente
                if ($carbonDate->isToday()) {
                    $dateLabel .= ' (Hoje)';
                } elseif ($carbonDate->isYesterday()) {
                    $dateLabel .= ' (Ontem)';
                } elseif ($carbonDate->diffInDays(now()) <= 7) {
                    $dateLabel .= ' (' . $carbonDate->dayName . ')';
                }
                
                $this->groupedSessions[$dateLabel] = $sessions;
            });
            
            // Mantém compatibilidade com o código existente
            $this->availableSessions = collect($this->groupedSessions)->flatten(1)->toArray();
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao carregar sessões:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage()
            ]);
            $this->availableSessions = [];
            $this->groupedSessions = [];
            $this->totalHistoryPages = 1;
        }
    }

    private function formatMessages($messages)
    {
        if (empty($messages)) return [];
        
        $userIds = collect($messages)->pluck('usuario_id')->unique()->filter();
        
        $users = collect();
        if ($userIds->isNotEmpty()) {
            try {
                $users = \App\Models\System\User::whereIn('id', $userIds)
                    ->select(['id', 'name'])
                    ->get()
                    ->keyBy('id');
                    
                foreach ($users as $user) {
                    $this->photoCache[$user->id] = $this->getUserPhotoBase64($user);
                }
            } catch (\Exception $e) {
                Log::error('[Chat] Erro ao carregar usuários:', ['error' => $e->getMessage()]);
            }
        }
        
        return collect($messages)->map(function($msg) use ($users) {
            return $this->formatSingleMessage($msg, $users);
        })->toArray();
    }

    public function setMessages($messages)
    {
        $this->messages = $this->formatMessages($messages);
    }

    private function formatSingleMessage($msg, $users = null)
    {
        $userId = $msg['usuario_id'] ?? null;
        $user = null;
        $photo = '';
        
        if ($users && $userId) {
            $user = $users->get($userId);
            $photo = $user ? ($this->photoCache[$userId] ?? $this->getUserPhotoBase64($user)) : '';
        } elseif ($userId) {
            $user = $this->getUserFromCache($userId);
            $photo = $user ? $this->getUserPhotoBase64($user) : '';
        }
        
        // Garante foto do usuário atual se for própria
        if ($userId == $this->currentUser['id'] && empty($photo)) {
            $photo = $this->currentUser['photo'] ?? '';
        }
        
        return [
            'id' => $msg['id'] ?? null,
            'mensagem' => $this->formatMessageText($msg['mensagem'] ?? ''),
            'usuario_id' => $userId,
            'author' => $user ? $user->name : 'Usuário',
            'photo' => $photo,
            'time' => isset($msg['dt_criacao']) ? 
                \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i') : '',
            'is_pinned' => $msg['is_fixed'] ?? false,
            'is_fixed' => $msg['is_fixed'] ?? false,
            'is_own' => $userId == $this->currentUser['id'],
        ];
    }

    private function getUserPhotoBase64($user)
    {
        if (!$user) return '';
        
        $userId = $user->id ?? null;
        if (!$userId) return '';
        
        if (isset($this->photoCache[$userId])) {
            return $this->photoCache[$userId];
        }
        
        try {
            $photo = '';
            
            if (method_exists($user, 'getUserPhoto')) {
                $photo = $user->getUserPhoto();
            } elseif (isset($user->photo)) {
                $photo = $user->photo;
            }
            
            if (!$photo) {
                $photoData = DB::table('users')
                    ->where('id', $userId)
                    ->value('photo');
                    
                if ($photoData) {
                    if (!str_starts_with($photoData, 'data:')) {
                        $photo = $photoData;
                    } elseif (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $matches)) {
                        $photo = $matches[2];
                    }
                }
            }
            
            $this->photoCache[$userId] = $photo;
            return $photo;
            
        } catch (\Exception $e) {
            return '';
        }
    }

    private function getUserFromCache($userId)
    {
        if (!$userId) return null;
        
        if (isset($this->userCache[$userId])) {
            return $this->userCache[$userId];
        }
        
        try {
            $user = \App\Models\System\User::select(['id', 'name'])
                ->find($userId);
                
            $this->userCache[$userId] = $user;
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function updateLocalMessagePin($messageId, $isPinned)
    {
        foreach ($this->messages as &$message) {
            if ($message['id'] == $messageId) {
                $message['is_pinned'] = $isPinned;
                $message['is_fixed'] = $isPinned;
            } elseif ($isPinned && ($message['is_pinned'] || $message['is_fixed'])) {
                // Desfixar outras mensagens se esta foi fixada
                $message['is_pinned'] = false;
                $message['is_fixed'] = false;
            }
        }
    }

    private function formatMessageText($text)
    {
        if (empty($text)) return '';
        
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Formatação markdown simples
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
        $text = nl2br($text);
        $text = preg_replace('/^[\-\*]\s(.+)$/m', '• $1', $text);
        
        return $text;
    }

    private function openChatSession()
    {
        if (!$this->patientId) return;

        try {
            $session = ChatSessao::firstOrCreate([
                'nr_atendimento' => $this->patientId,
                'turno_id' => $this->currentShift,
                'data_sessao' => $this->currentDate,
                'encerrada' => false,
            ], [
                'inicio' => now(),
                'encerrada' => false,
            ]);
            
            $this->currentSessionId = $session->id;
            
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao abrir sessão:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getCurrentSessionId()
    {
        if ($this->currentSessionId) {
            return $this->currentSessionId;
        }
        
        if (!$this->patientId) return null;
        
        $session = ChatSessao::where([
            'nr_atendimento' => $this->patientId,
            'turno_id' => $this->currentShift,
            'data_sessao' => $this->currentDate,
            'encerrada' => false,
        ])->first();
        
        $this->currentSessionId = $session?->id;
        return $this->currentSessionId;
    }

    private function setCurrentShift()
    {
        $hour = now()->hour;
        if ($hour >= 7 && $hour < 13) {
            $this->currentShift = 'manha';
        } elseif ($hour >= 13 && $hour < 19) {
            $this->currentShift = 'tarde';
        } else {
            $this->currentShift = 'noite';
        }
    }

    private function checkIfShiftClosed()
    {
        $hour = now()->hour;
        
        switch ($this->currentShift) {
            case 'manha':
                return $hour < 7 || $hour >= 13;
            case 'tarde':
                return $hour < 13 || $hour >= 19;
            case 'noite':
                return $hour >= 7 && $hour < 19;
            default:
                return false;
        }
    }

    public function render()
    {
        return view('livewire.chat-component');
    }
}