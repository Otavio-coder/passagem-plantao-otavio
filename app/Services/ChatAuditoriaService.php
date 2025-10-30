<?php

namespace App\Services;

use App\Models\System\Chat\ChatAuditoria;
use App\Models\System\Chat\ChatMensagem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ChatAuditoriaService
{
    public static function registrarEnvioMensagem(ChatMensagem $mensagem)
    {
        try {
            self::registrarAcao([
                'mensagem_id' => $mensagem->id,
                'sessao_id' => $mensagem->sessao_id,
                'nr_atendimento' => $mensagem->nr_atendimento,
                'turno_id' => $mensagem->turno_id,
                'acao' => 'mensagem_enviada',
                'detalhes_acao' => [
                    'tamanho_mensagem' => strlen($mensagem->mensagem),
                    'timestamp' => $mensagem->dt_criacao->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao registrar auditoria de envio', ['error' => $e->getMessage()]);
        }
    }

    public static function registrarFixacaoMensagem(ChatMensagem $mensagem, bool $fixada, ?ChatMensagem $estadoAnterior = null)
    {
        try {
            self::registrarAcao([
                'mensagem_id' => $mensagem->id,
                'sessao_id' => $mensagem->sessao_id,
                'nr_atendimento' => $mensagem->nr_atendimento,
                'turno_id' => $mensagem->turno_id,
                'acao' => $fixada ? 'mensagem_fixada' : 'mensagem_desfixada',
                'detalhes_acao' => [
                    'estado_fixacao' => $fixada,
                    'fixed_by' => $mensagem->fixed_by,
                    'fixed_at' => $mensagem->fixed_at?->toISOString(),
                ],
                'estado_anterior' => $estadoAnterior ? [
                    'is_fixed' => $estadoAnterior->is_fixed,
                    'fixed_by' => $estadoAnterior->fixed_by,
                    'fixed_at' => $estadoAnterior->fixed_at?->toISOString(),
                ] : null,
                'estado_posterior' => [
                    'is_fixed' => $mensagem->is_fixed,
                    'fixed_by' => $mensagem->fixed_by,
                    'fixed_at' => $mensagem->fixed_at?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao registrar auditoria de fixação', ['error' => $e->getMessage()]);
        }
    }

    public static function registrarAcessoHistorico(string $nrAtendimento, string $turnoId, string $data)
    {
        try {
            self::registrarAcao([
                'nr_atendimento' => $nrAtendimento,
                'turno_id' => $turnoId,
                'acao' => 'acesso_historico',
                'detalhes_acao' => [
                    'data_consultada' => $data,
                    'timestamp_acesso' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao registrar auditoria de acesso', ['error' => $e->getMessage()]);
        }
    }

    public static function registrarEdicaoMensagem(ChatMensagem $mensagem, $estadoAnterior = null)
    {
        try {
            self::registrarAcao([
                'mensagem_id' => $mensagem->id,
                'sessao_id' => $mensagem->sessao_id,
                'nr_atendimento' => $mensagem->nr_atendimento,
                'turno_id' => $mensagem->turno_id,
                'acao' => 'mensagem_editada',
                'detalhes_acao' => [
                    'timestamp' => now()->toISOString(),
                ],
                'estado_anterior' => $estadoAnterior ? [
                    'mensagem' => $estadoAnterior->mensagem,
                    'dt_edicao' => $estadoAnterior->dt_edicao?->toISOString(),
                ] : null,
                'estado_posterior' => [
                    'mensagem' => $mensagem->mensagem,
                    'dt_edicao' => $mensagem->dt_edicao?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao registrar auditoria de edição', ['error' => $e->getMessage()]);
        }
    }

    public static function registrarDelecaoMensagem(ChatMensagem $mensagem)
    {
        try {
            self::registrarAcao([
                'mensagem_id' => $mensagem->id,
                'sessao_id' => $mensagem->sessao_id,
                'nr_atendimento' => $mensagem->nr_atendimento,
                'turno_id' => $mensagem->turno_id,
                'acao' => 'mensagem_deletada',
                'detalhes_acao' => [
                    'timestamp' => now()->toISOString(),
                ],
                'estado_anterior' => [
                    'mensagem' => $mensagem->mensagem,
                    'dt_criacao' => $mensagem->dt_criacao?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao registrar auditoria de deleção', ['error' => $e->getMessage()]);
        }
    }

    private static function registrarAcao(array $dados)
    {
        try {
            $user = Auth::user();

            ChatAuditoria::create(array_merge($dados, [
                'usuario_id' => $user?->id ?? 0,
                'dt_acao' => now(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]));
        } catch (\Exception $e) {
            Log::error('Erro crítico na auditoria', [
                'error' => $e->getMessage(),
                'dados' => $dados
            ]);

        }
    }

    public static function safeDecryptMessage(string $mensagem): string
    {
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($mensagem);
        } catch (\Exception $e) {
            return $mensagem; // Retorna a mensagem original se não conseguir descriptografar
        }
    }

}
