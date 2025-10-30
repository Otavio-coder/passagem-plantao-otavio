<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

class FallRisk extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.ESCALA_MORSE';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_avaliacao' => 'datetime',
        'dt_liberacao' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Scope para avaliações ativas
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('dt_liberacao');
    }

    /**
     * Retorna histórico de queda formatado
     */
    public static function getLatestFallHistory($attendanceNumber): string
    {
        $latest = static::where('nr_atendimento', $attendanceNumber)
            ->active()
            ->orderByDesc('dt_avaliacao')
            ->first();

        if (!$latest) {
            return 'Não avaliado';
        }

        if ($latest->ie_hist_queda === 'S') {
            return Carbon::parse($latest->dt_avaliacao)->format('d/m/Y H:i:s');
        }

        return 'Não';
    }
}
