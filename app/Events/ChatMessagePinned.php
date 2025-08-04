<?php

namespace App\Events;

use App\Models\ChatMensagem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ChatMessagePinned implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $mensagem;

    public function __construct(ChatMensagem $mensagem)
    {
        $this->mensagem = $mensagem;
    }

    public function broadcastOn()
    {
        return new Channel('chat.' . $this->mensagem->nr_atendimento . '.' . $this->mensagem->turno_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->mensagem->id,
            'is_fixed' => $this->mensagem->is_fixed,
            'fixed_by' => $this->mensagem->fixed_by,
            'fixed_at' => $this->mensagem->fixed_at?->toISOString(),
            'sessao_id' => $this->mensagem->sessao_id,
            'nr_atendimento' => $this->mensagem->nr_atendimento,
            'turno_id' => $this->mensagem->turno_id,
        ];
    }

    public function broadcastAs()
    {
        return 'ChatMessagePinned';
    }
}