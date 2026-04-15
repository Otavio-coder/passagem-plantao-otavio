<?php

namespace App\Livewire;

use App\Models\System\Chat\ChatMessage;
use App\Models\System\User;
use App\Repositories\MySQL\Chat\ChatRepository;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatComponent extends Component
{
    public $patientId;

    public $cdPessoaFisica;

    public $bedUnit;

    /** Array plano de itens: ['type'=>'separator',...] ou ['type'=>'message',...] */
    public $messages = [];

    public $newMessage = '';

    public $loadingMessages = false;

    public $sendingMessage = false;

    public $hasOlderMessages = false;

    public $hasArchivedHistory = false;

    public $currentUser;

    public $initialized = false;

    private $userCache = [];

    private $photoCache = [];

    protected $listeners = [
        'initChat' => 'initialize',
        'refreshChat' => 'refreshCurrentMessages',
    ];

    public function mount($patientId = null, $cdPessoaFisica = null, $bedUnit = null)
    {
        $this->patientId = $patientId;
        $this->cdPessoaFisica = $cdPessoaFisica;
        $this->bedUnit = $bedUnit;

        $user = Auth::user();
        $this->currentUser = [
            'id' => $user->id,
            'name' => $user->name,
            'photo' => $this->getUserPhotoBase64($user),
        ];
    }

    public function initialize()
    {
        if ($this->initialized || ! $this->patientId) {
            return;
        }

        try {
            $this->loadingMessages = true;
            $this->loadMessages();
            $this->initialized = true;

            $this->dispatch('chat-initialized', [
                'patientId' => $this->patientId,
                'componentId' => $this->getId(),
            ]);

        } catch (\Throwable $e) {
            Log::error('[Chat] Erro na inicialização:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage(),
            ]);
            // Marca como inicializado mesmo em caso de erro para evitar spinner infinito.
            // O array $messages fica vazio e o blade exibe estado vazio.
            $this->initialized = true;
        } finally {
            $this->loadingMessages = false;
        }
    }

    public function sendMessage($messageContent = null)
    {
        if ($this->sendingMessage) {
            return ['success' => false, 'error' => 'Operação não permitida'];
        }

        $this->sendingMessage = true;

        try {
            $rateLimitKey = 'chat-send:'.$this->currentUser['id'];
            if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
                return ['success' => false, 'error' => 'Muitas mensagens enviadas. Aguarde um momento.'];
            }
            RateLimiter::hit($rateLimitKey, 60);

            $message = trim($messageContent ?? $this->newMessage);
            $this->newMessage = '';

            if (empty($message)) {
                return ['success' => false, 'error' => 'Mensagem vazia'];
            }

            $repo = new ChatRepository;
            $newMessage = $repo->storeMessage([
                'nr_atendimento' => $this->patientId,
                'cd_pessoa_fisica' => $this->cdPessoaFisica,
                'user_id' => $this->currentUser['id'],
                'content' => $message,
            ]);

            if (! $newMessage) {
                throw new \Exception('Falha ao criar mensagem');
            }

            $this->addMessageToLocal([
                'id' => $newMessage->id,
                'content' => $message,
                'user_id' => $this->currentUser['id'],
                'created_at' => now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'updated_at' => null,
                'is_pinned' => false,
                'reactions' => [],
            ]);

            $this->dispatch('scroll-to-bottom');
            $this->dispatch('handover-updated', nr: $this->patientId);

            Log::channel('audit')->info('chat.message.sent', [
                'user_id' => $this->currentUser['id'],
                'user' => $this->currentUser['name'],
                'message_id' => $newMessage->id,
                'patient_id' => $this->patientId,
                'bed' => $this->bedUnit,
                'ip' => request()->ip(),
            ]);

            return ['success' => true, 'message_id' => $newMessage->id];

        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao enviar mensagem', [
                'error' => $e->getMessage(),
                'patient_id' => $this->patientId,
            ]);

            return ['success' => false, 'error' => 'Erro ao enviar mensagem'];
        } finally {
            $this->sendingMessage = false;
        }
    }

    public function toggleMessagePin($messageId)
    {
        if (! $this->patientId || ! $messageId) {
            return ['success' => false, 'error' => 'Operação não permitida'];
        }

        try {
            $repo = new ChatRepository;
            $updatedMessage = $repo->pinMessage((int) $messageId, $this->currentUser['id']);

            $isPinned = $updatedMessage->is_pinned ?? false;
            $this->updateLocalMessagePin($messageId, $isPinned);

            Log::channel('audit')->info($isPinned ? 'chat.message.pinned' : 'chat.message.unpinned', [
                'user_id' => $this->currentUser['id'],
                'user' => $this->currentUser['name'],
                'message_id' => $messageId,
                'patient_id' => $this->patientId,
                'bed' => $this->bedUnit,
                'ip' => request()->ip(),
            ]);

            return ['success' => true, 'is_pinned' => $isPinned];

        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao alterar pin', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Erro ao fixar mensagem'];
        }
    }

    /** Edita uma mensagem — janela de 6h a partir do envio */
    public function editMessage($messageId, $newContent)
    {
        if (! $messageId) {
            return ['success' => false, 'error' => 'ID inválido'];
        }

        $newContent = trim($newContent);
        if (empty($newContent)) {
            return ['success' => false, 'error' => 'Mensagem não pode ser vazia'];
        }

        try {
            $message = ChatMessage::find($messageId);

            if (! $message) {
                return ['success' => false, 'error' => 'Mensagem não encontrada'];
            }

            if ($message->user_id != $this->currentUser['id']) {
                return ['success' => false, 'error' => 'Apenas o autor pode editar'];
            }

            // Janela de 6 horas
            if ($message->created_at && now()->diffInHours($message->created_at) >= 6) {
                return ['success' => false, 'error' => 'Tempo de edição expirado (6h após envio)'];
            }

            $repo = new ChatRepository;
            $repo->updateMessage($messageId, $newContent);

            Log::channel('audit')->info('chat.message.edited', [
                'user_id' => $this->currentUser['id'],
                'user' => $this->currentUser['name'],
                'message_id' => $messageId,
                'patient_id' => $this->patientId,
                'bed' => $this->bedUnit,
                'ip' => request()->ip(),
            ]);

            // Atualiza localmente
            foreach ($this->messages as &$msg) {
                if (($msg['type'] ?? '') !== 'message' || $msg['id'] != $messageId) {
                    continue;
                }
                $msg['content'] = $this->formatMessageText($newContent);
                $msg['content_text'] = $newContent;
                $msg['is_edited'] = true;
                break;
            }

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao editar mensagem', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Erro ao editar mensagem'];
        }
    }

    /** Toggles check reaction for the current user on a message */
    public function toggleReaction($messageId)
    {
        if (! $messageId) {
            return ['success' => false];
        }

        try {
            $repo = new ChatRepository;
            $result = $repo->toggleReaction((int) $messageId, (int) $this->currentUser['id']);

            foreach ($this->messages as &$msg) {
                if (($msg['type'] ?? '') !== 'message' || $msg['id'] != $messageId) {
                    continue;
                }
                $msg['reactions'] = $this->formatReactions($result['reactions']);
                $msg['user_reacted'] = $result['reacted'];
                break;
            }

            return ['success' => true, 'reacted' => $result['reacted']];

        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao reagir', ['error' => $e->getMessage()]);

            return ['success' => false];
        }
    }

    public function refreshCurrentMessages()
    {
        if ($this->loadingMessages) {
            return;
        }

        $this->loadingMessages = true;
        try {
            $this->loadMessages();
        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao atualizar mensagens:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loadingMessages = false;
        }
    }

    // ===================== WebSocket handlers =====================

    #[On('echo-private:chat.{patientId},.ChatMessageSent')]
    public function handleNewMessage($data)
    {
        if (! $this->initialized || ! $data || ! isset($data['id'])) {
            return;
        }

        if ($this->messageExists($data['id'])) {
            return;
        }

        $this->addMessageToLocal($data);
        $this->dispatch('scroll-to-bottom');
    }

    #[On('echo-private:chat.{patientId},.ChatMessagePinned')]
    public function handleMessagePinned($data)
    {
        if (! $this->initialized || ! $data || ! isset($data['id'])) {
            return;
        }

        $this->updateLocalMessagePin($data['id'], $data['is_pinned'] ?? false);
    }

    // ===================== Private helpers =====================

    private function loadMessages()
    {
        if (! $this->patientId) {
            return;
        }

        try {
            $repo = new ChatRepository;
            $allMessages = $repo->getAllMessages($this->patientId, 300);

            $this->hasOlderMessages = $repo->hasOlderMessages($this->patientId);
            $this->hasArchivedHistory = $repo->hasArchivedHistory($this->patientId);

            if ($allMessages->isEmpty()) {
                $this->messages = [];

                return;
            }

            // Pré-carrega usuários
            $userIds = $allMessages->pluck('user_id')->unique()->filter();
            $users = collect();
            if ($userIds->isNotEmpty()) {
                $users = User::whereIn('id', $userIds)
                    ->select(['id', 'name'])
                    ->get()
                    ->keyBy('id');
                foreach ($users as $user) {
                    $this->photoCache[$user->id] = $this->getUserPhotoBase64($user);
                }
            }

            // Monta array plano com separadores por turno/data lógica
            $result = [];
            $lastShiftKey = null;

            foreach ($allMessages as $msg) {
                $msgArray = (array) $msg;
                $shiftKey = $this->getShiftKey($msg->created_at);

                if ($shiftKey !== $lastShiftKey) {
                    $lastShiftKey = $shiftKey;
                    $result[] = $this->buildSeparator($msg->created_at);
                }

                $formatted = $this->formatSingleMessage($msgArray, $users);
                $formatted['type'] = 'message';
                $result[] = $formatted;
            }

            $this->messages = $result;

        } catch (\Exception $e) {
            Log::error('[Chat] Erro ao carregar mensagens:', [
                'patient_id' => $this->patientId,
                'error' => $e->getMessage(),
            ]);
            $this->messages = [];
        }
    }

    /**
     * Returns a string key representing the shift+logical-date for a given timestamp.
     * Used to detect shift boundaries when building separators.
     */
    private function getShiftKey($createdAt): string
    {
        $dt = $this->parseDateTime($createdAt);
        $shiftInfo = ShiftService::getShiftInfo($dt, 30);

        return $shiftInfo['date'].'_'.$shiftInfo['shift'];
    }

    private function buildSeparator($createdAt): array
    {
        $dt = $this->parseDateTime($createdAt);
        $shiftInfo = ShiftService::getShiftInfo($dt, 30);
        $shift = $shiftInfo['shift'];
        $logicalDate = Carbon::parse($shiftInfo['date'])->startOfDay();

        $shiftLabel = match ($shift) {
            'morning' => 'Manhã  07h–13h',
            'afternoon' => 'Tarde  13h–19h',
            'night' => 'Noite  19h–07h',
            default => ucfirst($shift),
        };

        if ($logicalDate->isToday()) {
            $dateLabel = 'Hoje';
        } elseif ($logicalDate->isYesterday()) {
            $dateLabel = 'Ontem';
        } else {
            $dateLabel = $logicalDate->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY');
        }

        return [
            'type' => 'separator',
            'shift' => $shift,
            'shift_label' => $shiftLabel,
            'date_label' => $dateLabel,
            'label' => $shiftLabel.' · '.$dateLabel,
        ];
    }

    private function formatSingleMessage($msg, $users = null)
    {
        $userId = $msg['user_id'] ?? null;
        $user = null;

        // Use pre-loaded values from broadcast payload when available to avoid DB queries
        $photo = $msg['_photo'] ?? null;
        $author = $msg['_author'] ?? null;

        if ($author === null || $photo === null) {
            if ($users && $userId) {
                $user = $users->get($userId);
                $author = $author ?? ($user?->name ?? 'Usuário');
                $photo = $photo ?? ($user ? ($this->photoCache[$userId] ?? $this->getUserPhotoBase64($user)) : '');
            } elseif ($userId) {
                $user = $this->getUserFromCache($userId);
                $author = $author ?? ($user?->name ?? 'Usuário');
                $photo = $photo ?? ($user ? $this->getUserPhotoBase64($user) : '');
            }
        }

        $author = $author ?? 'Usuário';
        $photo = $photo ?? '';

        if ($userId == $this->currentUser['id'] && empty($photo)) {
            $photo = $this->currentUser['photo'] ?? '';
        }

        $rawDate = $msg['created_at'] ?? null;
        $time = '';
        $dtRaw = null;

        if ($parsed = $this->parseDateTime($rawDate)) {
            $time = $parsed->format('H:i');
            $dtRaw = $parsed->toIso8601String();
        }

        $rawReactions = $msg['reactions'] ?? [];

        $content = $msg['content'] ?? '';
        $id = $msg['id'] ?? null;
        $isTemporary = (bool) ($msg['is_temporary'] ?? false);
        $isFailed = (bool) ($msg['failed'] ?? false);
        $isPinned = (bool) ($msg['is_pinned'] ?? false);
        $isOwn = $userId == $this->currentUser['id'];
        $isEdited = ! empty($msg['updated_at']);
        $isReal = ! $isTemporary && ! str_starts_with((string) $id, 'temp-');
        $rawReactions = is_array($rawReactions) ? $rawReactions : [];
        $reactions = $this->formatReactions($rawReactions);
        $userReacted = $this->currentUserReacted($rawReactions);

        return [
            'type' => 'message',
            'id' => $id,
            'msg_id' => is_numeric($id) ? (int) $id : 0,
            'content' => $this->formatMessageText($content),
            'content_text' => $content,
            'user_id' => $userId,
            'author' => $author,
            'photo' => $photo,
            'time' => $time,
            'dt_criacao_raw' => $dtRaw,
            'is_temporary' => $isTemporary,
            'failed' => $isFailed,
            'is_pinned' => $isPinned,
            'is_edited' => $isEdited,
            'is_own' => $isOwn,
            'is_real' => $isReal,
            'reactions' => $reactions,
            'reactions_count' => count($reactions),
            'user_reacted' => $userReacted,
        ];
    }

    private function formatReactions(array $rawReactions): array
    {
        return collect($rawReactions)->map(fn ($r) => [
            'user_id' => is_array($r) ? ($r['user_id'] ?? null) : ($r->user_id ?? null),
            'name' => is_array($r) ? ($r['name'] ?? 'Usuário') : ($r->name ?? 'Usuário'),
            'initial' => strtoupper(substr(is_array($r) ? ($r['name'] ?? 'U') : ($r->name ?? 'U'), 0, 1)),
            'photo' => is_array($r) ? ($r['photo'] ?? '') : ($r->photo ?? ''),
        ])->values()->toArray();
    }

    private function currentUserReacted(array $rawReactions): bool
    {
        return collect($rawReactions)->contains(function ($r) {
            $id = is_array($r) ? ($r['user_id'] ?? null) : ($r->user_id ?? null);

            return $id == $this->currentUser['id'];
        });
    }

    private function messageExists($messageId): bool
    {
        return collect($this->messages)
            ->filter(fn ($m) => ($m['type'] ?? '') === 'message')
            ->contains('id', $messageId);
    }

    private function addMessageToLocal($data)
    {
        $createdAt = $data['created_at'] ?? now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $newShiftKey = $this->getShiftKey($createdAt);

        // Detecta se o turno mudou em relação à última mensagem
        $lastMsgShiftKey = null;
        for ($i = count($this->messages) - 1; $i >= 0; $i--) {
            if (($this->messages[$i]['type'] ?? '') === 'message') {
                $rawDt = $this->messages[$i]['dt_criacao_raw'] ?? null;
                if ($rawDt) {
                    $lastMsgShiftKey = $this->getShiftKey($rawDt);
                }
                break;
            }
        }

        // Insere separador se for a primeira mensagem ou se o turno mudou
        if ($newShiftKey !== $lastMsgShiftKey) {
            $this->messages[] = $this->buildSeparator($createdAt);
        }

        $message = $this->formatSingleMessage([
            'id' => $data['id'],
            'content' => $data['content'] ?? '',
            'user_id' => $data['user_id'] ?? null,
            'created_at' => $createdAt,
            'updated_at' => null,
            'is_pinned' => $data['is_pinned'] ?? false,
            'reactions' => [],
            '_author' => $data['author'] ?? null,
            '_photo' => $data['photo'] ?? null,
        ]);
        $message['type'] = 'message';
        $this->messages[] = $message;
    }

    private function updateLocalMessagePin($messageId, $isPinned)
    {
        foreach ($this->messages as &$message) {
            if (($message['type'] ?? '') !== 'message') {
                continue;
            }

            if ($message['id'] == $messageId) {
                $message['is_pinned'] = $isPinned;
            } elseif ($isPinned && ($message['is_pinned'] ?? false)) {
                $message['is_pinned'] = false;
            }
        }
    }

    private function parseDateTime($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone(config('app.timezone'));
        }

        $formats = [
            'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s',
            'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d', 'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt !== false) {
                    return $dt->setTimezone(config('app.timezone'));
                }
            } catch (\Exception $e) {
                // continua
            }
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatMessageText($text)
    {
        if (empty($text)) {
            return '';
        }

        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
        $text = nl2br($text);
        $text = preg_replace('/^[\-\*]\s(.+)$/m', '• $1', $text);

        return $text;
    }

    private function getUserPhotoBase64($user)
    {
        if (! $user) {
            return '';
        }

        $userId = $user->id ?? null;
        if (! $userId) {
            return '';
        }

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

            if (! $photo) {
                $photoData = DB::table('users')->where('id', $userId)->value('photo');

                if ($photoData) {
                    if (! str_starts_with($photoData, 'data:')) {
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
        if (! $userId) {
            return null;
        }

        if (isset($this->userCache[$userId])) {
            return $this->userCache[$userId];
        }

        try {
            $user = User::select(['id', 'name'])->find($userId);
            $this->userCache[$userId] = $user;

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSessionStats(): array
    {
        $realMessages = collect($this->messages)->filter(fn ($m) => ($m['type'] ?? '') === 'message');

        return [
            'total_messages' => $realMessages->count(),
            'pinned_count' => $realMessages->where('is_pinned', true)->count(),
        ];
    }

    #[Computed]
    public function shiftDisplay(): array
    {
        $shift = ShiftService::getShiftInfo(now(), 30)['shift'];
        $colors = ShiftService::getShiftColors($shift);

        $iconClass = match ($shift) {
            'morning' => 'fa-sun',
            'afternoon' => 'fa-cloud-sun',
            'night' => 'fa-moon',
            default => 'fa-clock',
        };

        $iconPulse = in_array($shift, ['morning', 'night'], true);

        return [
            'shift' => $shift,
            'gradient' => match ($shift) {
                'morning' => 'from-amber-400 via-orange-400 to-red-400',
                'afternoon' => 'from-sky-400 via-blue-400 to-cyan-400',
                'night' => 'from-indigo-500 via-purple-500 to-violet-600',
                default => 'from-gray-400 to-gray-500',
            },
            'gradient_style' => match ($shift) {
                'morning' => 'linear-gradient(90deg, #fbbf24 0%, #fb923c 50%, #f87171 100%)',
                'afternoon' => 'linear-gradient(90deg, #38bdf8 0%, #60a5fa 50%, #22d3ee 100%)',
                'night' => 'linear-gradient(90deg, #6366f1 0%, #8b5cf6 50%, #7c3aed 100%)',
                default => 'linear-gradient(90deg, #9ca3af 0%, #6b7280 100%)',
            },
            'badge' => match ($shift) {
                'morning' => 'MANHÃ',
                'afternoon' => 'TARDE',
                'night' => 'NOITE',
                default => strtoupper($shift),
            },
            'icon' => $iconClass,
            'icon_pulse' => $iconPulse,
            'icon_html' => '<i class="fas '.$iconClass.' text-white text-sm sm:text-base'.($iconPulse ? ' animate-pulse' : '').'"></i>',
            'accent' => $colors['accentColor'],
            'light_accent' => $colors['lightAccent'],
            'dark_accent' => $colors['darkAccent'],
        ];
    }

    #[Computed]
    public function messageStats(): array
    {
        $items = collect($this->messages)->filter(fn ($m) => ($m['type'] ?? '') === 'message');
        $pinned = $items->filter(fn ($m) => (bool) ($m['is_pinned'] ?? false));

        return [
            'count' => $items->count(),
            'has_messages' => $items->isNotEmpty(),
            'pinned_first' => $pinned->first(),
        ];
    }

    public function render()
    {
        return view('chat.chat-component');
    }
}
