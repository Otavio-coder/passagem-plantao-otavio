<?php

// File: `app/Models/System/Chat/ChatMensagem.php`
namespace App\Models\System\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMensagem extends Model
{
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

    public function fixedByUser()
    {
        return $this->belongsTo(\App\Models\System\User::class, 'fixed_by');
    }

    public function sessao()
    {
        return $this->belongsTo(ChatSessao::class, 'sessao_id');
    }

    // New relation: all audit records for this message
    public function auditorias(): HasMany
    {
        return $this->hasMany(ChatAuditoria::class, 'mensagem_id');
    }

    protected $casts = [
        'dt_criacao' => 'datetime',
        'dt_edicao' => 'datetime',
        'fixed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'expiracao' => 'datetime',
        'is_fixed' => 'boolean',
    ];

    public function getDecryptedMessageAttribute()
    {
        return \App\Services\ChatAuditoriaService::safeDecryptMessage($this->mensagem);
    }

    public function canDecrypt()
    {
        try {
            \Illuminate\Support\Facades\Crypt::decryptString($this->mensagem);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
