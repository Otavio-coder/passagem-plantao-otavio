<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMensagem extends Model
{
    use SoftDeletes;

    protected $table = 'chat_mensagens';

    protected $fillable = [
        'sessao_id',
        'nr_atendimento',
        'turno_id',
        'usuario_id',
        'mensagem',
        'dt_criacao',
        'dt_edicao',
        'is_fixed',
        'fixed_by',
        'fixed_at',
        'resolved_at',
        'expiracao'
    ];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\System\User::class, 'usuario_id');
    }

    public $timestamps = false;

    protected $casts = [
        'dt_criacao'   => 'datetime',
        'dt_edicao'    => 'datetime',
        'fixed_at'     => 'datetime',
        'resolved_at'  => 'datetime',
        'expiracao'    => 'datetime',
        'is_fixed'     => 'boolean',
    ];
}
