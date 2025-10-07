<?php
// =============================================================================
// ARQUIVO: app/Events/ChatMessageSent.php
// AÇÃO: MODIFICAR - Usar PrivateChannel em vez de Channel
// =============================================================================

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel; 
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
    }
    
    public function broadcastOn()
    {
        $channelName = "chat.{$this->message->nr_atendimento}.{$this->message->turno_id}";
        return new PrivateChannel($channelName);
    }
    
    public function broadcastWith()
    {
        $photo = '';
        $author = 'Usuário';
        
        try {
            if (!$this->message->relationLoaded('usuario')) {
                $this->message->load('usuario');
            }
            
            if ($this->message->usuario) {
                $author = $this->message->usuario->name;
                if (method_exists($this->message->usuario, 'getUserPhoto')) {
                    $photo = $this->message->usuario->getUserPhoto();
                }
            } elseif ($this->message->usuario_id) {
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
        return $broadcastData;
    }
    
    public function broadcastAs()
    {
        return 'ChatMessageSent';
    }
    
    public function broadcastWhen()
    {
        $shouldBroadcast = !empty($this->message->nr_atendimento) && !empty($this->message->turno_id);
        
        return $shouldBroadcast;
    }
}