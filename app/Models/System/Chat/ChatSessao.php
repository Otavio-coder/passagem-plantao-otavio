<?php

namespace App\Models\System\Chat;

use Illuminate\Database\Eloquent\Model;

class ChatSessao extends Model
{
    protected $table = 'chat_sessoes';

    protected $fillable = [
        'nr_atendimento',
        'turno_id',
        'data_sessao',
        'inicio',
        'fim',
        'encerrada',
        'usuarios_participantes',
        'total_mensagens'
    ];

    public function messages()
    {
        return $this->hasMany(ChatMensagem::class, 'sessao_id');
    }

    public function auditoria()
    {
        return $this->hasManyThrough(ChatAuditoria::class, ChatMensagem::class, 'sessao_id', 'mensagem_id');
    }

    protected $casts = [
        'data_sessao' => 'date',
        'inicio' => 'datetime',
        'fim' => 'datetime',
        'encerrada' => 'boolean',
        'usuarios_participantes' => 'array',
        'total_mensagens' => 'integer',
    ];
}
