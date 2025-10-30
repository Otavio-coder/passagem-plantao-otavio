<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;

class Device extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.ATEND_PAC_DISPOSITIVO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_insercao' => 'datetime',
        'dt_retirada' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Scope para dispositivos ativos (não retirados)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('dt_retirada');
    }

    /**
     * Retorna descrição do dispositivo via função TASY
     */
    public function getDescriptionAttribute(): string
    {
        return \DB::connection('tasy')->selectOne(
            "SELECT TASY.obter_descricao_padrao('DISPOSITIVO', 'DS_DISPOSITIVO', :seq) AS descricao FROM dual",
            ['seq' => $this->nr_seq_dispositivo]
        )->descricao ?? 'Dispositivo não identificado';
    }
}
