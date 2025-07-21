<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAuditoria extends Model
{
    protected $table = 'chat_auditoria';

    protected $fillable = [
        'mensagem_id',
        'acao',
        'usuario_id',
        'dt_acao',
        'detalhes'
    ];

    public function mensagem()
    {
        return $this->belongsTo(\App\Models\ChatMensagem::class, 'mensagem_id');
    }
    public function usuario()
    {
        return $this->belongsTo(\App\Models\System\User::class, 'usuario_id');
    }

    public $timestamps = false;
}