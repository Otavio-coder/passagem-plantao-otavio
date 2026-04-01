<?php

namespace App\Events;

use App\Models\System\Chat\ChatMessage;
use App\Models\System\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ChatMessagePinned implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;

    public $isPinned;

    public function __construct(ChatMessage $message, bool $isPinned)
    {
        $this->message = $message;
        $this->isPinned = $isPinned;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("chat.{$this->message->nr_atendimento}");
    }

    public function broadcastWith()
    {
        $pinnedBy = null;

        try {
            if ($this->isPinned && $this->message->activePin) {
                $user = User::find($this->message->activePin->pinned_by);
                $pinnedBy = $user?->name;
            }
        } catch (\Exception $e) {
            Log::warning('[Chat] Erro ao obter dados do pin: '.$e->getMessage());
        }

        return [
            'id' => $this->message->id,
            'is_pinned' => $this->isPinned,
            'pinned_by' => $pinnedBy,
            'nr_atendimento' => $this->message->nr_atendimento,
        ];
    }

    public function broadcastAs()
    {
        return 'ChatMessagePinned';
    }

    public function broadcastWhen()
    {
        return ! empty($this->message->nr_atendimento);
    }
}
