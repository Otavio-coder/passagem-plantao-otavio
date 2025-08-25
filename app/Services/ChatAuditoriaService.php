<?php

namespace App\Services;

use App\Models\ChatAuditoria;
use App\Models\ChatMensagem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

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

    public static function registrarFalhaEnvioMensagem($mensagem, $erro = null)
    {
        try {
            self::registrarAcao([
                'mensagem_id' => $mensagem?->id,
                'sessao_id' => $mensagem?->sessao_id,
                'nr_atendimento' => $mensagem?->nr_atendimento,
                'turno_id' => $mensagem?->turno_id,
                'acao' => 'falha_envio',
                'detalhes_acao' => [
                    'erro' => $erro,
                    'timestamp' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao registrar auditoria de falha de envio', ['error' => $e->getMessage()]);
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
            // Não re-lançar exceção para não quebrar o fluxo principal
        }
    }

    public static function safeDecryptMessage(string $mensagem): string
    {
        // Se a mensagem não estiver criptografada, retorna como está
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($mensagem);
        } catch (\Exception $e) {
            return $mensagem; // Retorna a mensagem original se não conseguir descriptografar
        }
    }

    /**
     * Verifica se uma mensagem já foi auditada
     */
    public static function mensagemJaAuditada($mensagemId): bool
    {
        try {
            return ChatAuditoria::where('mensagem_id', $mensagemId)
                ->where('acao', 'mensagem_enviada')
                ->exists();
        } catch (\Exception $e) {
            Log::warning('Erro ao verificar auditoria da mensagem', [
                'mensagem_id' => $mensagemId,
                'error' => $e->getMessage()
            ]);
            return false; // Em caso de erro, assume que não foi auditada
        }
    }

    /**
     * Verifica se uma ação específica já foi registrada para uma mensagem
     */
    public static function acaoJaRegistrada($mensagemId, $acao): bool
    {
        try {
            return ChatAuditoria::where('mensagem_id', $mensagemId)
                ->where('acao', $acao)
                ->exists();
        } catch (\Exception $e) {
            Log::warning('Erro ao verificar ação da auditoria', [
                'mensagem_id' => $mensagemId,
                'acao' => $acao,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Remove duplicatas de auditoria (limpeza)
     */
    public static function limparDuplicatasAuditoria(): int
    {
        try {
            // Remove registros duplicados baseado em mensagem_id + acao + usuario_id
            $duplicates = ChatAuditoria::select('mensagem_id', 'acao', 'usuario_id')
                ->whereNotNull('mensagem_id')
                ->groupBy('mensagem_id', 'acao', 'usuario_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $removidos = 0;
            foreach ($duplicates as $duplicate) {
                // Mantém apenas o primeiro registro, remove os demais
                $registros = ChatAuditoria::where('mensagem_id', $duplicate->mensagem_id)
                    ->where('acao', $duplicate->acao)
                    ->where('usuario_id', $duplicate->usuario_id)
                    ->orderBy('dt_acao', 'asc')
                    ->get();

                // Remove todos exceto o primeiro
                foreach ($registros->slice(1) as $registro) {
                    $registro->delete();
                    $removidos++;
                }
            }

            if ($removidos > 0) {
                Log::info("Limpeza de auditoria concluída", ['registros_removidos' => $removidos]);
            }

            return $removidos;
        } catch (\Exception $e) {
            Log::error('Erro na limpeza de duplicatas de auditoria', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}