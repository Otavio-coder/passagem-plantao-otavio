<?php

namespace App\Repositories\MySQL;

use App\Models\ChatMensagem;
use App\Models\ChatAuditoria;
use App\Models\ChatSessao;
use App\Services\ChatAuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ChatRepository
{
    public function getMessages($nr_atendimento, $turno_id, $date = null, $perPage = 50)
    {
        try {
            $date = $date ?? now()->toDateString();
            
            $cacheKey = "session_id_{$nr_atendimento}_{$turno_id}_{$date}";
            $sessaoId = Cache::remember($cacheKey, 300, function() use ($nr_atendimento, $turno_id, $date) {
                return ChatSessao::where('nr_atendimento', $nr_atendimento)
                    ->where('turno_id', $turno_id)
                    ->where('data_sessao', $date)
                    ->value('id');
            });

            if (!$sessaoId) {
                return collect();
            }

            $messages = ChatMensagem::select(['id', 'mensagem', 'usuario_id', 'dt_criacao', 'is_fixed'])
                ->where('sessao_id', $sessaoId)
                ->where('is_deleted', false)
                ->orderBy('dt_criacao', 'asc')
                ->limit($perPage)
                ->get();

            return $messages;
        } catch (\Exception $e) {
            return collect();
        }
    }

    public function getMessagesForSession($nr_atendimento, $turno_id, $date, $perPage = 100)
    {
        ChatAuditoriaService::registrarAcessoHistorico($nr_atendimento, $turno_id, $date);
        return $this->getMessages($nr_atendimento, $turno_id, $date, $perPage);
    }

    public function storeMessage(array $data, $nr_atendimento = null, $turno_id = null)
    {
        $msg = null;
        
        try {
            DB::transaction(function() use ($data, &$msg) {
                // Cria a mensagem
                $msg = ChatMensagem::create($data);
                
                // Atualiza contador da sessão
                ChatSessao::where('id', $msg->sessao_id)->increment('total_mensagens');
                
                // Registra auditoria
                ChatAuditoriaService::registrarEnvioMensagem($msg);
            });
            
            // Limpa caches relacionados
            $this->clearMessageCaches($msg->nr_atendimento, $msg->turno_id);
            
            if ($msg) {
                // Carrega o relacionamento usuario antes do broadcast
                $msg->load('usuario');
                
                // Garante que os campos de broadcast estejam preenchidos
                $msg->nr_atendimento = $msg->nr_atendimento ?? $nr_atendimento;
                $msg->turno_id = $msg->turno_id ?? $turno_id;
                
                // Dispara o evento de broadcast APÓS o commit da transação
                event(new \App\Events\ChatMessageSent($msg));
            }
            
        } catch (\Exception $e) {
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
                $mensagemAnterior = $msg->replicate();

                // Busca e desfixa outras mensagens da mesma sessão
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

                // Alterna o estado de fixação da mensagem atual
                $newPinState = !$msg->is_fixed;
                $msg->update([
                    'is_fixed' => $newPinState,
                    'fixed_by' => $newPinState ? $fixed_by : null,
                    'fixed_at' => $newPinState ? now() : null
                ]);

                ChatAuditoriaService::registrarFixacaoMensagem($msg, $newPinState, $mensagemAnterior);
            });
            
            // Limpa caches relacionados
            $this->clearMessageCaches($msg->nr_atendimento, $msg->turno_id);
            
            if ($msg) {
                // Carrega o relacionamento usuario antes do broadcast
                $msg->load('usuario');
                
                // Dispara o evento de broadcast APÓS o commit da transação
                event(new \App\Events\ChatMessagePinned($msg));
            }
            
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $msg;
    }

    public function updateMessage($id, $newContent)
    {
        $msg = ChatMensagem::findOrFail($id);
        $estadoAnterior = $msg->replicate();
        $msg->update([
            'mensagem' => $newContent,
            'dt_edicao' => now(),
        ]);
        \App\Services\ChatAuditoriaService::registrarEdicaoMensagem($msg, $estadoAnterior);
        return $msg;
    }

    public function deleteMessage($id)
    {
        $msg = ChatMensagem::findOrFail($id);
        \App\Services\ChatAuditoriaService::registrarDelecaoMensagem($msg);
        $msg->update(['is_deleted' => true]);
        return $msg;
    }

    private function clearMessageCaches($nr_atendimento, $turno_id)
    {
        try {
            $today = now()->toDateString();
            $cacheKeys = [
                "chat_messages_{$nr_atendimento}_{$turno_id}_{$today}",
                "session_id_{$nr_atendimento}_{$turno_id}_{$today}",
                "chat_sessions_{$nr_atendimento}_v4",
            ];
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}