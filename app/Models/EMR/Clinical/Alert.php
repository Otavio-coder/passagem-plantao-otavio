<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Person;
use Carbon\Carbon;

class Alert extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.ALERTA_PACIENTE';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_fim_alerta' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_pessoa_fisica', 'cd_pessoa_fisica');
    }

    /**
     * Scope para alertas ativos
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('ds_alerta')
            ->where('ds_alerta', '!=', '')
            ->where(function($q) {
                $q->whereNull('dt_fim_alerta')
                    ->orWhereDate('dt_fim_alerta', '>', Carbon::now());
            });
    }

    /**
     * Retorna severidade do alerta
     */
    public function getSeverityAttribute(): string
    {
        return 'warning'; // Padrão, pode ser customizado baseado em tipo
    }
}
