<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

class NursingPrescription extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PE_PRESCRICAO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_prescricao' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Retorna última data da PE
     */
    public static function getLatestFormatted($attendanceNumber): string
    {
        $latest = static::where('nr_atendimento', $attendanceNumber)
            ->orderByDesc('dt_prescricao')
            ->first();

        return $latest
            ? Carbon::parse($latest->dt_prescricao)->format('d/m/Y')
            : 'Não realizado';
    }
}
