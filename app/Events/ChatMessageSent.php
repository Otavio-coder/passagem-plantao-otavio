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
        // Enhanced payload with user data for immediate UI update
        $user = $this->mensagem->usuario;
        
        return [
            'id' => $this->mensagem->id,
            'mensagem' => $this->mensagem->mensagem,
            'usuario_id' => $this->mensagem->usuario_id,
            'author' => $user ? $user->name : 'Usuário',
            'photo' => $user && method_exists($user, 'photo') ? $user->photo() : '',
            'created_at' => $this->mensagem->dt_criacao->toISOString(),
            'is_fixed' => $this->mensagem->is_fixed,
            'sessao_id' => $this->mensagem->sessao_id,
            'nr_atendimento' => $this->mensagem->nr_atendimento,
            'turno_id' => $this->mensagem->turno_id,
        ];
    }

    public function broadcastAs()
    {
        return 'ChatMessageSent';
    }
}