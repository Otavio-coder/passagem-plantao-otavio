<?php
// =============================================================================
// ARQUIVO: app/Events/ChatMessagePinned.php
// AÇÃO: MODIFICAR - Usar PrivateChannel em vez de Channel
// =============================================================================

namespace App\Events;

use App\Models\ChatMensagem;
use Illuminate\Broadcasting\PrivateChannel; // ← MUDANÇA AQUI
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
        
        // Log para debug
        Log::info('[Chat] Evento ChatMessagePinned criado (imediato)', [
            'message_id' => $mensagem->id,
            'patient_id' => $mensagem->nr_atendimento,
            'shift' => $mensagem->turno_id,
            'is_fixed' => $mensagem->is_fixed,
            'fixed_by' => $mensagem->fixed_by
        ]);
    }
    
    public function broadcastOn()
    {
        $channelName = 'chat.' . $this->mensagem->nr_atendimento . '.' . $this->mensagem->turno_id;
        Log::info('[Chat] Broadcasting ChatMessagePinned IMEDIATO no PRIVATE channel: ' . $channelName);
        return new PrivateChannel($channelName); // ← MUDANÇA AQUI
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
        
        Log::info('[Chat] Dados do broadcast ChatMessagePinned (imediato):', $broadcastData);
        
        return $broadcastData;
    }
    
    public function broadcastAs()
    {
        return 'ChatMessagePinned';
    }
    
    public function broadcastWhen()
    {
        $shouldBroadcast = !empty($this->mensagem->nr_atendimento) && !empty($this->mensagem->turno_id);
        
        if (!$shouldBroadcast) {
            Log::warning('[Chat] Broadcast cancelado - dados insuficientes', [
                'nr_atendimento' => $this->mensagem->nr_atendimento,
                'turno_id' => $this->mensagem->turno_id
            ]);
        }
        
        return $shouldBroadcast;
    }
}