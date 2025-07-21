<?php

namespace App\Repositories\MySQL;

use App\Models\ChatMensagem;
use App\Models\ChatAuditoria;

class ChatRepository
{
    public function getMessages($nr_atendimento, $turno_id, $date = null, $perPage = 20)
    {
        $date = $date ?? now()->toDateString();
        $sessao = \App\Models\ChatSessao::where('nr_atendimento', $nr_atendimento)
            ->where('turno_id', $turno_id)
            ->where('data_sessao', $date)
            ->first();

        if (!$sessao) return collect();

        return ChatMensagem::where('sessao_id', $sessao->id)
            ->orderBy('dt_criacao', 'asc')
            ->paginate($perPage);
    }

    public function storeMessage(array $data)
    {
        $msg = ChatMensagem::create($data);
        ChatAuditoria::create([
            'mensagem_id' => $msg->id,
            'acao' => 'enviada',
            'usuario_id' => $msg->usuario_id,
            'detalhes' => json_encode(['ip' => request()->ip()])
        ]);
        event(new \App\Events\ChatMessageSent($msg));
        return $msg;
    }

    public function pinMessage($id, $fixed_by)
    {
        $msg = ChatMensagem::findOrFail($id);
        $msg->is_fixed = !$msg->is_fixed;
        $msg->fixed_by = $msg->is_fixed ? $fixed_by : null;
        $msg->fixed_at = $msg->is_fixed ? now() : null;
        $msg->save();
        ChatAuditoria::create([
            'mensagem_id' => $msg->id,
            'acao' => $msg->is_fixed ? 'fixada' : 'desfixada',
            'usuario_id' => $fixed_by,
            'detalhes' => json_encode(['ip' => request()->ip()])
        ]);
        event(new \App\Events\ChatMessagePinned($msg));
        return $msg;
    }
}