<?php
// =============================================================================
// ARQUIVO: app/Events/ChatMessageSent.php
// AÇÃO: MODIFICAR - Usar PrivateChannel em vez de Channel
// =============================================================================

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel; // ← MUDANÇA AQUI
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public $message;
    
    public function __construct($message)
    {
        $this->message = $message;        
        // Log para debug
        Log::info('[Chat] Evento ChatMessageSent criado (imediato)', [
            'message_id' => $message->id,
            'patient_id' => $message->nr_atendimento,
            'shift' => $message->turno_id,
            'user_id' => $message->usuario_id
        ]);
    }
    
    public function broadcastOn()
    {
        $channelName = "chat.{$this->message->nr_atendimento}.{$this->message->turno_id}";
        Log::info('[Chat] Broadcasting ChatMessageSent IMEDIATO no PRIVATE channel: ' . $channelName);
        return new PrivateChannel($channelName); // ← MUDANÇA AQUI
    }
    
    public function broadcastWith()
    {
        $photo = '';
        $author = 'Usuário';
        
        try {
            // Garante que o relacionamento usuario está carregado
            if (!$this->message->relationLoaded('usuario')) {
                $this->message->load('usuario');
            }
            
            // Obtém o nome do autor
            if ($this->message->usuario) {
                $author = $this->message->usuario->name;
                // Tenta obter a foto do usuário
                if (method_exists($this->message->usuario, 'getUserPhoto')) {
                    $photo = $this->message->usuario->getUserPhoto();
                }
            } elseif ($this->message->usuario_id) {
                // Busca do banco se não carregado
                $authorDb = \App\Models\System\User::find($this->message->usuario_id);
                if ($authorDb) {
                    $author = $authorDb->name;
                    if (method_exists($authorDb, 'getUserPhoto')) {
                        $photo = $authorDb->getUserPhoto();
                    }
                }
            }
            
            // Fallback para buscar foto diretamente da tabela
            if (!$photo && $this->message->usuario_id) {
                $photoData = DB::table('users')
                    ->where('id', $this->message->usuario_id)
                    ->value('photo');
                    
                if ($photoData) {
                    if (!str_starts_with($photoData, 'data:')) {
                        $photo = $photoData;
                    } elseif (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $matches)) {
                        $photo = $matches[2];
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('[Chat] Erro ao obter dados do autor: ' . $e->getMessage());
        }
        
        $broadcastData = [
            'id' => $this->message->id,
            'mensagem' => $this->message->mensagem,
            'usuario_id' => $this->message->usuario_id,
            'author' => $author,
            'photo' => $photo,
            'created_at' => $this->message->dt_criacao?->toISOString(),
            'is_fixed' => $this->message->is_fixed ?? false,
            'nr_atendimento' => $this->message->nr_atendimento,
            'turno_id' => $this->message->turno_id,
            'sessao_id' => $this->message->sessao_id,
        ];
        
        Log::info('[Chat] Dados do broadcast ChatMessageSent (imediato):', $broadcastData);
        
        return $broadcastData;
    }
    
    public function broadcastAs()
    {
        return 'ChatMessageSent';
    }
    
    public function broadcastWhen()
    {
        $shouldBroadcast = !empty($this->message->nr_atendimento) && !empty($this->message->turno_id);
        
        if (!$shouldBroadcast) {
            Log::warning('[Chat] Broadcast cancelado - dados insuficientes', [
                'nr_atendimento' => $this->message->nr_atendimento,
                'turno_id' => $this->message->turno_id
            ]);
        }
        
        return $shouldBroadcast;
    }
}