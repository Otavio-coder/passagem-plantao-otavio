<?php

namespace App\Services;

use App\Models\ChatMensagem;
use App\Models\ChatAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class ChatAuditoriaService
{
    /**
     * Registra envio de mensagem
     */
    public static function registrarEnvioMensagem(ChatMensagem $mensagem)
    {
        self::registrarAcao($mensagem->id, 'envio_mensagem', [
            'nr_atendimento' => $mensagem->nr_atendimento,
            'turno' => $mensagem->turno_id,
            'sessao_id' => $mensagem->sessao_id,
        ]);
    }

    /**
     * Registra fixação/desfixação de mensagem
     */
    public static function registrarFixacaoMensagem(ChatMensagem $mensagem, $fixada)
    {
        self::registrarAcao($mensagem->id, $fixada ? 'fixar_mensagem' : 'desfixar_mensagem', [
            'nr_atendimento' => $mensagem->nr_atendimento,
            'turno' => $mensagem->turno_id,
            'sessao_id' => $mensagem->sessao_id,
        ]);
    }

    /**
     * Registra carregamento de histórico
     */
    public static function registrarCarregamentoHistorico($nrAtendimento, $turno, $data)
    {
        self::registrarAcao(null, 'carregamento_historico', [
            'nr_atendimento' => $nrAtendimento,
            'turno' => $turno,
            'data' => $data,
        ]);
    }

    /**
     * Método base para registrar ações
     */
    private static function registrarAcao($mensagemId, $acao, $detalhes)
    {
        try {
            ChatAuditoria::create([
                'mensagem_id' => $mensagemId,
                'acao' => $acao,
                'usuario_id' => Auth::id(),
                'dt_acao' => now(),
                'detalhes' => json_encode(array_merge($detalhes, [
                    'ip' => Request::ip(),
                ])),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria', [
                'acao' => $acao,
                'detalhes' => $detalhes,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Descriptografa mensagem de forma segura
     */
    public static function safeDecryptMessage($encryptedMessage)
    {
        if (empty($encryptedMessage)) {
            return '[Mensagem vazia]';
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encryptedMessage);
        } catch (\Exception $e) {
            return '[Mensagem não pode ser descriptografada]';
        }
    }
}