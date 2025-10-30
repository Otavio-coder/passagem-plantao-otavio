<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingDiagnosis extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PE_PRESCR_DIAG';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(NursingPrescription::class, 'nr_seq_prescr', 'nr_sequencia');
    }

    /**
     * Retorna descrição do diagnóstico via função TASY
     */
    public function getDescriptionAttribute(): string
    {
        return \DB::connection('tasy')->selectOne(
            "SELECT TASY.PE_obter_desc_diag(:seq, 'DI') AS descricao FROM dual",
            ['seq' => $this->nr_seq_diag]
        )->descricao ?? '';
    }

    /**
     * Busca diagnósticos da última prescrição de um atendimento
     */
    public static function getLatestForAttendance($attendanceNumber, $limit = 3): string
    {
        $latestPrescriptionDate = NursingPrescription::where('nr_atendimento', $attendanceNumber)
            ->max('dt_prescricao');

        if (!$latestPrescriptionDate) {
            return 'Sem diagnósticos SAE';
        }

        $diagnoses = static::whereHas('prescription', function($q) use ($attendanceNumber, $latestPrescriptionDate) {
            $q->where('nr_atendimento', $attendanceNumber)
                ->where('dt_prescricao', $latestPrescriptionDate);
        })
            ->limit($limit)
            ->get();

        if ($diagnoses->isEmpty()) {
            return 'Sem diagnósticos SAE';
        }

        return $diagnoses->map(fn($d) => substr($d->description, 0, 100))
            ->implode(' | ');
    }
}
