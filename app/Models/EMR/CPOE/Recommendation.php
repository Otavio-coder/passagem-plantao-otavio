<?php

namespace App\Models\EMR\CPOE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\{Patient, Person};
use Carbon\Carbon;

class Recommendation extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.CPOE_RECOMENDACAO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_inicio' => 'date',
        'dt_fim' => 'date',
        'dt_liberacao' => 'datetime',
        'dt_suspensao' => 'datetime',
    ];

    // ========== RELAÇÕES ==========

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Prescritor da recomendação (pode ser médico, enfermeiro, etc)
     */
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_medico', 'cd_pessoa_fisica');
    }
}
