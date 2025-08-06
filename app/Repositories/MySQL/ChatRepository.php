<?php

namespace App\Repositories\MySQL;

use App\Models\ChatMensagem;
use App\Models\ChatAuditoria;
use App\Models\ChatSessao;
use App\Services\ChatAuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatRepository
{
    public function getMessages($nr_atendimento, $turno_id, $date = null, $perPage = 50)
    {
        $date = $date ?? now()->toDateString();
        
        $cacheKey = "session_id_{$nr_atendimento}_{$turno_id}_{$date}";
        $sessaoId = Cache::remember($cacheKey, 300, function() use ($nr_atendimento, $turno_id, $date) {
            return ChatSessao::where('nr_atendimento', $nr_atendimento)
                ->where('turno_id', $turno_id)
                ->where('data_sessao', $date)
                ->value('id');
        });

        if (!$sessaoId) return collect();

        return ChatMensagem::select(['id', 'mensagem', 'usuario_id', 'dt_criacao', 'is_fixed'])
            ->where('sessao_id', $sessaoId)
            ->where('is_deleted', false)
            ->orderBy('dt_criacao', 'asc')
            ->limit($perPage)
            ->get();
    }

    public function getMessagesForSession($nr_atendimento, $turno_id, $date, $perPage = 100)
    {
        // Registrar acesso ao histórico
        ChatAuditoriaService::registrarAcessoHistorico($nr_atendimento, $turno_id, $date);
        
        return $this->getMessages($nr_atendimento, $turno_id, $date, $perPage);
    }

    public function storeMessage(array $data)
    {
        $msg = null;
        
        try {
            DB::transaction(function() use ($data, &$msg) {
                $msg = ChatMensagem::create($data);
                
                // Update session message count
                ChatSessao::where('id', $msg->sessao_id)->increment('total_mensagens');
                
                // Registrar auditoria
                ChatAuditoriaService::registrarEnvioMensagem($msg);
            });
            
            $this->clearMessageCaches($msg->nr_atendimento, $msg->turno_id);
            
            if ($msg) {
                $msg->load('usuario');
                event(new \App\Events\ChatMessageSent($msg));
            }
            
        } catch (\Exception $e) {
            Log::error('Error storing message', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
        
        return $msg;
    }

    public function pinMessage($id, $fixed_by)
    {
        $msg = null;
        $mensagemAnterior = null;
        
        try {
            DB::transaction(function() use ($id, $fixed_by, &$msg, &$mensagemAnterior) {
                $msg = ChatMensagem::findOrFail($id);
                
                // Capturar estado anterior
                $mensagemAnterior = $msg->replicate();

                // Unpin other messages in the same session
                $mensagensDesfixadas = ChatMensagem::where('sessao_id', $msg->sessao_id)
                    ->where('id', '!=', $id)
                    ->where('is_fixed', true)
                    ->get();

                foreach ($mensagensDesfixadas as $msgDesfixada) {
                    $msgDesfixada->update([
                        'is_fixed' => false, 
                        'fixed_by' => null, 
                        'fixed_at' => null
                    ]);
                    
                    ChatAuditoriaService::registrarFixacaoMensagem($msgDesfixada, false);
                }

                // Toggle pin state for current message
                $newPinState = !$msg->is_fixed;
                $msg->update([
                    'is_fixed' => $newPinState,
                    'fixed_by' => $newPinState ? $fixed_by : null,
                    'fixed_at' => $newPinState ? now() : null
                ]);

                // Registrar auditoria com estados anterior e posterior
                ChatAuditoriaService::registrarFixacaoMensagem($msg, $newPinState, $mensagemAnterior);
            });
            
            $this->clearMessageCaches($msg->nr_atendimento, $msg->turno_id);
            
            if ($msg) {
                event(new \App\Events\ChatMessagePinned($msg));
            }
            
        } catch (\Exception $e) {
            Log::error('Error pinning message', [
                'error' => $e->getMessage(),
                'message_id' => $id,
                'fixed_by' => $fixed_by
            ]);
            throw $e;
        }
        
        return $msg;
    }

    private function clearMessageCaches($nr_atendimento, $turno_id)
    {
        $today = now()->toDateString();
        $cacheKeys = [
            "chat_messages_{$nr_atendimento}_{$turno_id}_{$today}",
            "session_id_{$nr_atendimento}_{$turno_id}_{$today}",
            "chat_sessions_{$nr_atendimento}",
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}