<?php

namespace App\Services;

use App\Models\ChatMensagem;
use App\Models\ChatSessao;
use App\Models\ChatAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChatAuditoriaService
{
    /**
     * Registra criação de sessão
     */
    public static function registrarCriacaoSessao(ChatSessao $sessao): void
    {
        self::registrarAuditoria([
            'sessao_id' => $sessao->id,
            'nr_atendimento' => $sessao->nr_atendimento,
            'turno_id' => $sessao->turno_id,
            'acao' => 'sessao_criada',
            'detalhes_acao' => [
                'data_sessao' => $sessao->data_sessao,
                'inicio' => $sessao->inicio?->toISOString(),
            ]
        ]);
    }

    /**
     * Registra encerramento de sessão
     */
    public static function registrarEncerramentoSessao(ChatSessao $sessao): void
    {
        self::registrarAuditoria([
            'sessao_id' => $sessao->id,
            'nr_atendimento' => $sessao->nr_atendimento,
            'turno_id' => $sessao->turno_id,
            'acao' => 'sessao_encerrada',
            'detalhes_acao' => [
                'data_sessao' => $sessao->data_sessao,
                'inicio' => $sessao->inicio?->toISOString(),
                'fim' => $sessao->fim?->toISOString(),
                'total_mensagens' => $sessao->total_mensagens,
            ]
        ]);
    }

    /**
     * Registra envio de mensagem
     */
    public static function registrarEnvioMensagem(ChatMensagem $mensagem): void
    {
        self::registrarAuditoria([
            'mensagem_id' => $mensagem->id,
            'sessao_id' => $mensagem->sessao_id,
            'nr_atendimento' => $mensagem->nr_atendimento,
            'turno_id' => $mensagem->turno_id,
            'acao' => 'mensagem_enviada',
            'detalhes_acao' => [
                'tamanho_mensagem' => strlen($mensagem->mensagem),
                'dt_criacao' => $mensagem->dt_criacao->toISOString(),
            ]
        ]);
    }

    /**
     * Registra fixação/desfixação de mensagem
     */
    public static function registrarFixacaoMensagem(ChatMensagem $mensagem, bool $fixada, ?ChatMensagem $mensagemAnterior = null): void
    {
        $estadoAnterior = null;
        $estadoPosterior = null;

        if ($mensagemAnterior) {
            $estadoAnterior = [
                'is_fixed' => $mensagemAnterior->is_fixed,
                'fixed_by' => $mensagemAnterior->fixed_by,
                'fixed_at' => $mensagemAnterior->fixed_at?->toISOString(),
            ];
        }

        $estadoPosterior = [
            'is_fixed' => $mensagem->is_fixed,
            'fixed_by' => $mensagem->fixed_by,
            'fixed_at' => $mensagem->fixed_at?->toISOString(),
        ];

        self::registrarAuditoria([
            'mensagem_id' => $mensagem->id,
            'sessao_id' => $mensagem->sessao_id,
            'nr_atendimento' => $mensagem->nr_atendimento,
            'turno_id' => $mensagem->turno_id,
            'acao' => $fixada ? 'mensagem_fixada' : 'mensagem_desfixada',
            'estado_anterior' => $estadoAnterior,
            'estado_posterior' => $estadoPosterior,
            'detalhes_acao' => [
                'mensagem_id_anterior' => $mensagemAnterior?->id,
            ]
        ]);
    }

    /**
     * Registra acesso ao histórico
     */
    public static function registrarAcessoHistorico(string $nrAtendimento, string $turno, string $data): void
    {
        self::registrarAuditoria([
            'nr_atendimento' => $nrAtendimento,
            'turno_id' => $turno,
            'acao' => 'historico_acessado',
            'detalhes_acao' => [
                'data_consultada' => $data,
                'turno_consultado' => $turno,
            ]
        ]);
    }

    /**
     * Registra entrada do usuário no chat
     */
    public static function registrarEntradaUsuario(string $nrAtendimento, string $turno, ?int $sessaoId = null): void
    {
        self::registrarAuditoria([
            'sessao_id' => $sessaoId,
            'nr_atendimento' => $nrAtendimento,
            'turno_id' => $turno,
            'acao' => 'usuario_entrou',
            'detalhes_acao' => [
                'timestamp_entrada' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Método base para registrar auditoria
     */
    private static function registrarAuditoria(array $dados): void
    {
        try {
            // Garantir campos obrigatórios
            $dados = array_merge([
                'usuario_id' => Auth::id() ?? 0,
                'dt_acao' => now(),
                'ip_address' => Request::ip(),
                'user_agent' => substr(Request::userAgent() ?? '', 0, 500),
            ], $dados);

            // Usar transação para garantir consistência
            DB::transaction(function() use ($dados) {
                ChatAuditoria::create($dados);
            }, 3); // 3 tentativas

        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria do chat', [
                'dados' => $dados,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Em produção, pode enviar para serviço de monitoramento
            // throw $e; // Descomente se quiser que falhas de auditoria quebrem o fluxo
        }
    }

    /**
     * Descriptografa mensagem de forma segura
     */
    public static function safeDecryptMessage(?string $encryptedMessage): string
    {
        if (empty($encryptedMessage)) {
            return '[Mensagem vazia]';
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encryptedMessage);
        } catch (\Exception $e) {
            Log::warning('Falha ao descriptografar mensagem', [
                'error' => $e->getMessage()
            ]);
            return '[Mensagem não pode ser descriptografada]';
        }
    }

    /**
     * Obtém estatísticas de auditoria para dashboard
     */
    public static function obterEstatisticas(string $nrAtendimento, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $query = ChatAuditoria::forPatient($nrAtendimento);
        
        if ($dataInicio && $dataFim) {
            $query->inDateRange($dataInicio, $dataFim);
        }

        return [
            'total_acoes' => $query->count(),
            'usuarios_unicos' => $query->distinct('usuario_id')->count(),
            'mensagens_enviadas' => $query->byAction('mensagem_enviada')->count(),
            'mensagens_fixadas' => $query->byAction('mensagem_fixada')->count(),
            'acessos_historico' => $query->byAction('historico_acessado')->count(),
            'primeira_atividade' => $query->min('dt_acao'),
            'ultima_atividade' => $query->max('dt_acao'),
        ];
    }
}