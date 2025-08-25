<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAuditoria extends Model
{
    protected $table = 'chat_auditoria';

    protected $fillable = [
        'mensagem_id',
        'sessao_id',
        'nr_atendimento',
        'turno_id',
        'acao',
        'usuario_id',
        'dt_acao',
        'ip_address',
        'user_agent',
        'detalhes_acao',
        'estado_anterior',
        'estado_posterior'
    ];

    protected $casts = [
        'dt_acao' => 'datetime',
        'detalhes_acao' => 'array',
        'estado_anterior' => 'array',
        'estado_posterior' => 'array',
    ];

    public function mensagem(): BelongsTo
    {
        return $this->belongsTo(ChatMensagem::class, 'mensagem_id');
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(ChatSessao::class, 'sessao_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\System\User::class, 'usuario_id');
    }

    // Scopes para consultas comuns
    public function scopeForPatient($query, $nrAtendimento)
    {
        return $query->where('nr_atendimento', $nrAtendimento);
    }

    public function scopeByAcao($query, $acao)
    {
        return $query->where('acao', $acao);
    }

    public function scopeByUser($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('dt_acao', [$startDate, $endDate]);
    }
}