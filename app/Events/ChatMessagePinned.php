<?php
// =============================================================================
// ARQUIVO: app/Events/ChatMessagePinned.php
// AÇÃO: MODIFICAR - Usar PrivateChannel em vez de Channel
// =============================================================================

namespace App\Events;

use App\Models\ChatMensagem;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Support\Facades\Log;

class ChatMessagePinned implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;
    
    public $mensagem;
    
    public function __construct(ChatMensagem $mensagem)
    {
        $this->mensagem = $mensagem;
        
    }
    
    public function broadcastOn()
    {
        $channelName = 'chat.' . $this->mensagem->nr_atendimento . '.' . $this->mensagem->turno_id;
        return new PrivateChannel($channelName); 
    }
    
    public function broadcastWith()
    {
        $author = 'Usuário';
        
        try {
            // Obtém o nome do usuário que fixou
            if ($this->mensagem->fixed_by) {
                $user = \App\Models\System\User::find($this->mensagem->fixed_by);
                if ($user) {
                    $author = $user->name;
                }
            }
        } catch (\Exception $e) {
            Log::warning('[Chat] Erro ao obter dados do usuário que fixou: ' . $e->getMessage());
        }
        
        $broadcastData = [
            'id' => $this->mensagem->id,
            'is_fixed' => $this->mensagem->is_fixed,
            'fixed_by' => $this->mensagem->fixed_by,
            'fixed_at' => $this->mensagem->fixed_at?->toISOString(),
            'sessao_id' => $this->mensagem->sessao_id,
            'nr_atendimento' => $this->mensagem->nr_atendimento,
            'turno_id' => $this->mensagem->turno_id,
            'author' => $author,
        ];        
        return $broadcastData;
    }
    
    public function broadcastAs()
    {
        return 'ChatMessagePinned';
    }
    
    public function broadcastWhen()
    {
        $shouldBroadcast = !empty($this->mensagem->nr_atendimento) && !empty($this->mensagem->turno_id);
               
        return $shouldBroadcast;
    }
}