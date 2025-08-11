<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatSessao;
use App\Models\ChatMensagem;
use App\Repositories\MySQL\ChatRepository;
use App\Services\ChatAuditoriaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChatComponent extends Component
{
    // Component properties
    public $patientId;
    public $bedUnit;
    public $currentShift;
    public $currentDate;
    
    // Chat state
    public $messages = [];
    public $newMessage = '';
    public $messageLoading = false;
    public $viewingHistory = false;
    public $availableSessions = [];
    public $selectedSession = null;
    public $loadingMessages = false;
    
    // User data
    public $currentUser;
    public $isShiftClosed = false;
    
    // Performance flags
    public $initialized = false;
    private $messageProcessing = false;
    
    private $sessionStartTime;

    protected $listeners = [
        'onChatMessageReceived' => 'handleNewMessage',
        'onChatMessagePinned' => 'handleMessagePinned',
        'initChat' => 'initialize',
    ];

    public function mount($patientId = null, $bedUnit = null)
    {
        // FIXED: Handle null patientId gracefully
        $this->patientId = $patientId;
        $this->bedUnit = $bedUnit;
        
        // Cache user data once
        $user = Auth::user();
        $this->currentUser = [
            'id' => $user->id,
            'name' => $user->name,
            'photo' => method_exists($user, 'getUserPhoto') ? $user->getUserPhoto() : '',
        ];
        
        // Set shift data - NOVA LÓGICA 3 TURNOS
        $hour = now()->hour;
        if ($hour >= 7 && $hour < 13) {
            $this->currentShift = 'manha';
        } elseif ($hour >= 13 && $hour < 19) {
            $this->currentShift = 'tarde';
        } else {
            $this->currentShift = 'noite';
        }
        
        $this->currentDate = now()->toDateString();
        
        // Check if shift is closed
        $this->isShiftClosed = $this->checkIfShiftClosed();

        $this->sessionStartTime = now();
    }

    public function initialize()
    {
        if ($this->initialized || !$this->patientId) return;
        
        try {
            $this->loadingMessages = true;
            
            $this->openChatSession();
            $this->loadMessages();
            $this->loadAvailableSessions();
            $this->initialized = true;
            
            $this->dispatch('chat-initialized', [
                'patientId' => $this->patientId,
                'shift' => $this->currentShift,
                'componentId' => $this->getId()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Chat initialization error', ['error' => $e->getMessage()]);
        } finally {
            $this->loadingMessages = false;
        }
    }

    public function sendMessage()
    {
        if ($this->messageProcessing || $this->viewingHistory || $this->isShiftClosed || empty(trim($this->newMessage)) || !$this->patientId) {
            return;
        }

        $this->messageProcessing = true;
        $this->messageLoading = true;
        $message = trim($this->newMessage);
        $this->newMessage = '';
        
        try {
            $sessaoId = $this->getCurrentSessionId();
            if (!$sessaoId) {
                throw new \Exception('No session found');
            }

            $repo = new ChatRepository();
            $newMessage = $repo->storeMessage([
                'sessao_id' => $sessaoId,
                'nr_atendimento' => $this->patientId,
                'turno_id' => $this->currentShift,
                'usuario_id' => $this->currentUser['id'],
                'mensagem' => $message,
                'dt_criacao' => now(),
            ]);

            // Registrar auditoria
            ChatAuditoriaService::registrarEnvioMensagem($newMessage);

            $this->addMessageToLocal($newMessage);
            $this->dispatch('scroll-to-bottom');
            $this->dispatch('message-sent');
            
        } catch (\Exception $e) {
            Log::error('Error sending message', ['error' => $e->getMessage()]);
            $this->newMessage = $message;
        } finally {
            $this->messageProcessing = false;
            $this->messageLoading = false;
        }
    }

    public function toggleMessagePin($messageId)
    {
        if ($this->viewingHistory || $this->isShiftClosed || !$this->patientId) return;
        
        try {
            $message = ChatMensagem::find($messageId);
            if (!$message) return;

            $wasFixed = $message->is_fixed;
            $this->updateMessagePinState($messageId);
            
            $repo = new ChatRepository();
            $repo->pinMessage($messageId, $this->currentUser['id']);
            
            // Registrar auditoria
            ChatAuditoriaService::registrarFixacaoMensagem($message, !$wasFixed);
            
        } catch (\Exception $e) {
            Log::error('Error pinning message', ['error' => $e->getMessage()]);
            $this->updateMessagePinState($messageId);
        }
    }

    public function loadSessionHistory()
    {
        if (!$this->selectedSession || $this->loadingMessages || !$this->patientId) {
            return;
        }

        try {
            // Parse the selected session key
            $parts = explode('|', $this->selectedSession);
            if (count($parts) !== 2) {
                Log::warning('Invalid session key format', ['key' => $this->selectedSession]);
                return;
            }
            
            [$date, $shift] = $parts;
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                Log::warning('Invalid date format in session key', ['date' => $date]);
                return;
            }
            
            $this->loadingMessages = true;
            $this->viewingHistory = true;
            
            // Registrar acesso ao histórico
            ChatAuditoriaService::registrarAcessoHistorico($this->patientId, $shift, $date);
            
            $repo = new ChatRepository();
            $messages = $repo->getMessagesForSession($this->patientId, $shift, $date, 100);

            $this->messages = $this->formatMessages($messages->toArray());
            
            // Dispatch event to notify about history loading
            $this->dispatch('history-loaded', [
                'date' => $date,
                'shift' => $shift,
                'messageCount' => count($this->messages)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error loading session messages', [
                'error' => $e->getMessage(),
                'selectedSession' => $this->selectedSession,
                'patientId' => $this->patientId
            ]);
            $this->messages = [];
            
            // Show user-friendly error message
            $this->dispatch('history-error', [
                'message' => 'Erro ao carregar histórico. Tente novamente.'
            ]);
        } finally {
            $this->loadingMessages = false;
        }
    }

    public function clearSessionSelection()
    {
        $this->selectedSession = null;
        $this->returnToCurrentShift();
    }

    public function returnToCurrentShift()
    {
        if (!$this->viewingHistory) {
            return;
        }
        
        $this->viewingHistory = false;
        $this->selectedSession = null;
        
        // Reload current messages with cache refresh
        $this->refreshCurrentMessages();
        
        $this->dispatch('returned-to-current', [
            'shift' => $this->currentShift,
            'date' => $this->currentDate
        ]);
    }

    private function refreshCurrentMessages()
    {
        try {
            $this->loadingMessages = true;
            
            // Clear cache for current session
            $cacheKey = "chat_messages_{$this->patientId}_{$this->currentShift}_{$this->currentDate}";
            Cache::forget($cacheKey);
            
            $this->loadMessages();
            
        } catch (\Exception $e) {
            Log::error('Error refreshing current messages', ['error' => $e->getMessage()]);
        } finally {
            $this->loadingMessages = false;
        }
    }

    public function handleNewMessage($event)
    {
        if (!$this->initialized || $this->viewingHistory || !$event || $this->messageProcessing || !$this->patientId) {
            return;
        }

        // Skip if it's from current user
        if (($event['usuario_id'] ?? null) == $this->currentUser['id']) {
            return;
        }

        // Add message directly and scroll immediately
        $this->addMessageFromEvent($event);
        $this->dispatch('scroll-to-bottom');
    }

    public function handleMessagePinned($event)
    {
        if (!$this->initialized || !$event || !isset($event['id']) || !$this->patientId) {
            return;
        }

        $this->updateMessagePinState($event['id'], $event['is_fixed'] ?? false);
    }

    private function loadMessages()
    {
        if (!$this->patientId) return;

        try {
            $this->loadingMessages = true;
            
            $repo = new ChatRepository();
            $messages = $repo->getMessages($this->patientId, $this->currentShift, $this->currentDate, 50);

            $this->messages = $this->formatMessages($messages->toArray());
            
        } catch (\Exception $e) {
            Log::error('Error loading messages', ['error' => $e->getMessage()]);
            $this->messages = [];
        } finally {
            $this->loadingMessages = false;
        }
    }

    private function loadAvailableSessions()
    {
        if (!$this->patientId) return;
        
        try {
            $cacheKey = "chat_sessions_{$this->patientId}";
            
            $this->availableSessions = Cache::remember($cacheKey, 300, function() {
                // SOLUÇÃO MAIS SIMPLES: Carregar todas as sessões e processar em PHP
                $sessions = ChatSessao::where('nr_atendimento', $this->patientId)
                    ->whereHas('messages', function($query) {
                        $query->where('is_deleted', false);
                    })
                    ->orderBy('data_sessao', 'desc')
                    ->orderBy('turno_id', 'desc')
                    ->limit(30) // Aumentar limite para compensar duplicatas
                    ->get()
                    ->unique(function($session) {
                        return $session->data_sessao . '|' . $session->turno_id;
                    })
                    ->take(15); // Limitar após remover duplicatas

                return $sessions->map(function($session) {
                    $date = \Carbon\Carbon::parse($session->data_sessao);
                    $key = $session->data_sessao . '|' . $session->turno_id;
                    
                    // Contar mensagens reais para essa sessão
                    $messageCount = ChatMensagem::where('sessao_id', $session->id)
                        ->where('is_deleted', false)
                        ->count();
                    
                    // Enhanced label with message count and status
                    $label = $date->format('d/m/Y') . ' - ' . ucfirst($session->turno_id);
                    
                    if ($messageCount > 0) {
                        $label .= " ({$messageCount} msgs)";
                    }
                    
                    return [
                        'key' => $key,
                        'date' => $session->data_sessao,
                        'turno' => $session->turno_id,
                        'label' => $label,
                        'messageCount' => $messageCount,
                        'isCompleted' => $session->fim !== null,
                    ];
                })->values()->toArray();
            });
            
            // Always include current session
            $currentKey = $this->currentDate . '|' . $this->currentShift;
            $hasCurrentSession = collect($this->availableSessions)->contains('key', $currentKey);
            
            if (!$hasCurrentSession) {
                $currentSession = [
                    'key' => $currentKey,
                    'date' => $this->currentDate,
                    'turno' => $this->currentShift,
                    'label' => now()->format('d/m/Y') . ' - ' . ucfirst($this->currentShift) . ' (Atual)',
                    'messageCount' => count($this->messages),
                    'isCompleted' => false,
                ];
                
                array_unshift($this->availableSessions, $currentSession);
            }
            
        } catch (\Exception $e) {
            Log::error('Error loading sessions', [
                'error' => $e->getMessage(),
                'patientId' => $this->patientId
            ]);
            $this->availableSessions = [];
        }
    }

    public function getSessionStats($sessionKey)
    {
        if (!$sessionKey || !$this->patientId) return null;
        
        try {
            [$date, $shift] = explode('|', $sessionKey, 2);
            
            $stats = Cache::remember("session_stats_{$this->patientId}_{$shift}_{$date}", 600, function() use ($date, $shift) {
                $sessao = ChatSessao::where([
                    'nr_atendimento' => $this->patientId,
                    'turno_id' => $shift,
                    'data_sessao' => $date,
                ])->first();
                
                if (!$sessao) return null;
                
                // CORRIGIDO: Usar count() direto em vez de distinct em aggregation
                $messageCount = ChatMensagem::where('sessao_id', $sessao->id)
                    ->where('is_deleted', false)
                    ->count();
                    
                $pinnedCount = ChatMensagem::where('sessao_id', $sessao->id)
                    ->where('is_fixed', true)
                    ->where('is_deleted', false)
                    ->count();
                    
                $userCount = ChatMensagem::where('sessao_id', $sessao->id)
                    ->where('is_deleted', false)
                    ->distinct('usuario_id')
                    ->count('usuario_id');
                
                return [
                    'totalMessages' => $messageCount,
                    'pinnedMessages' => $pinnedCount,
                    'uniqueUsers' => $userCount,
                    'duration' => $sessao->inicio && $sessao->fim 
                        ? $sessao->inicio->diffForHumans($sessao->fim, true)
                        : 'Em andamento',
                    'isActive' => !$sessao->encerrada,
                ];
            });
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Error getting session stats', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function formatMessages($messages)
    {
        // Get all unique user IDs
        $userIds = collect($messages)->pluck('usuario_id')->unique()->filter();
        
        // Cache user data to avoid repeated queries
        $cacheKey = "chat_users_" . md5(implode(',', $userIds->toArray()));
        $users = Cache::remember($cacheKey, 600, function() use ($userIds) {
            return \App\Models\System\User::whereIn('id', $userIds)->get()->keyBy('id');
        });
        
        return collect($messages)->map(function($msg) use ($users) {
            $user = $users->get($msg['usuario_id'] ?? null);
            
            return [
                'id' => $msg['id'] ?? null,
                'mensagem' => $this->formatMessageText($msg['mensagem'] ?? ''),
                'usuario_id' => $msg['usuario_id'] ?? null,
                'author' => $user ? $user->name : 'Usuário',
                'photo' => $user && $user->photo() ? $user->photo() : '',
                'time' => isset($msg['dt_criacao']) ? \Carbon\Carbon::parse($msg['dt_criacao'])->format('H:i') : '',
                'is_pinned' => $msg['is_fixed'] ?? false,
                'is_fixed' => $msg['is_fixed'] ?? false,
                'is_own' => ($msg['usuario_id'] ?? null) == $this->currentUser['id'],
            ];
        })->toArray();
    }

    private function formatMessageText($text)
    {
        // Simple markdown-like formatting
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Bold: **text** or __text__
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
        
        // Italic: *text* or _text_
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
        
        // Line breaks
        $text = nl2br($text);
        
        // Bullet points: - item or * item
        $text = preg_replace('/^[\-\*]\s(.+)$/m', '• $1', $text);
        
        return $text;
    }

    private function addMessageToLocal($message)
    {
        $formattedMessage = [
            'id' => $message->id,
            'mensagem' => $this->formatMessageText($message->mensagem),
            'usuario_id' => $message->usuario_id,
            'author' => $this->currentUser['name'],
            'photo' => $this->currentUser['photo'],
            'time' => $message->dt_criacao->format('H:i'),
            'is_pinned' => false,
            'is_fixed' => false,
            'is_own' => true,
        ];

        $this->messages[] = $formattedMessage;
    }

    private function addMessageFromEvent($event)
    {
        $formattedMessage = [
            'id' => $event['id'] ?? null,
            'mensagem' => $this->formatMessageText($event['mensagem'] ?? ''),
            'usuario_id' => $event['usuario_id'] ?? null,
            'author' => $event['author'] ?? 'Usuário',
            'photo' => $event['photo'] ?? '',
            'time' => isset($event['created_at']) ? \Carbon\Carbon::parse($event['created_at'])->format('H:i') : now()->format('H:i'),
            'is_pinned' => $event['is_fixed'] ?? false,
            'is_fixed' => $event['is_fixed'] ?? false,
            'is_own' => false,
        ];

        $this->messages[] = $formattedMessage;
    }

    private function updateMessagePinState($messageId, $isPinned = null)
    {
        foreach ($this->messages as &$message) {
            if ($message['id'] == $messageId) {
                if ($isPinned !== null) {
                    $message['is_pinned'] = $isPinned;
                    $message['is_fixed'] = $isPinned;
                } else {
                    $message['is_pinned'] = !$message['is_pinned'];
                    $message['is_fixed'] = !$message['is_fixed'];
                }
                break;
            }
        }
    }

    private function openChatSession()
    {
        if (!$this->patientId) return;

        try {
            ChatSessao::firstOrCreate([
                'nr_atendimento' => $this->patientId,
                'turno_id' => $this->currentShift,
                'data_sessao' => $this->currentDate,
                'encerrada' => false,
            ], [
                'inicio' => now(),
                'encerrada' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Error opening chat session', ['error' => $e->getMessage()]);
        }
    }

    private function getCurrentSessionId()
    {
        if (!$this->patientId) return null;
        
        $cacheKey = "session_id_{$this->patientId}_{$this->currentShift}_{$this->currentDate}";
        
        return Cache::remember($cacheKey, 300, function() {
            return ChatSessao::where([
                'nr_atendimento' => $this->patientId,
                'turno_id' => $this->currentShift,
                'data_sessao' => $this->currentDate,
                'encerrada' => false,
            ])->value('id');
        });
    }

    private function checkIfShiftClosed()
    {
        $hour = now()->hour;
        
        // Check if current time is outside the current shift window
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