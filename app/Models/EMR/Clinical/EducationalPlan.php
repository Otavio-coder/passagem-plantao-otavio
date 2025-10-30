<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

class EducationalPlan extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.MED_AVALIACAO_PACIENTE';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_avaliacao' => 'datetime',
        'dt_liberacao' => 'datetime',
        'dt_inativacao' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Scope para plano educacional (tipo 793)
     */
    public function scopeEducationalType($query)
    {
        return $query->where('nr_seq_tipo_avaliacao', 793);
    }

    /**
     * Scope para planos ativos
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');
    }

    /**
     * Retorna última data do plano educacional
     */
    public static function getLatestFormatted($attendanceNumber): string
    {
        $latest = static::where('nr_atendimento', $attendanceNumber)
            ->educationalType()
            ->active()
            ->orderByDesc('dt_avaliacao')
            ->first();

        return $latest
            ? Carbon::parse($latest->dt_avaliacao)->format('d/m/Y')
            : 'Não realizado';
    }
}
