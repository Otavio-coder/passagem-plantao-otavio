<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

class NursingAssessment extends Model
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
     * Scope para avaliações de enfermagem (tipo 7781)
     */
    public function scopeNursingType($query)
    {
        return $query->where('nr_seq_tipo_avaliacao', 7781);
    }

    /**
     * Scope para avaliações ativas
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');
    }

    /**
     * Retorna última avaliação formatada
     */
    public static function getLatestFormatted($attendanceNumber): string
    {
        $latest = static::where('nr_atendimento', $attendanceNumber)
            ->nursingType()
            ->active()
            ->orderByDesc('dt_avaliacao')
            ->first();

        return $latest
            ? Carbon::parse($latest->dt_avaliacao)->format('d/m/Y H:i')
            : 'Não realizada';
    }
}
