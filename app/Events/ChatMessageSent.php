<?php

namespace App\Events;

use App\Models\ChatMensagem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ChatMessageSent implements ShouldBroadcast
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
            'mensagem' => $this->mensagem->mensagem,
            'usuario_id' => $this->mensagem->usuario_id,
            'author' => $this->mensagem->usuario->name ?? '',
            'created_at' => $this->mensagem->dt_criacao,
            'is_fixed' => $this->mensagem->is_fixed,
            'sessao_id' => $this->mensagem->sessao_id,
        ];
    }
}