<?php

namespace App\Models\Huddle;

use App\Enums\Huddle\DayColor;
use App\Models\System\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Estado diário de um paciente no Huddle de Gestão de Altas.
 *
 * Uma linha por internação (nr_atendimento) por dia (huddle_date). A cor do dia
 * segue a metodologia Red2Green (inicia vermelho). Os motivos de "dia vermelho"
 * ficam em huddle_red_reasons (relação redReasons).
 */
class HuddlePatientDay extends Model
{
    protected $table = 'huddle_patient_days';

    protected $fillable = [
        'nr_atendimento',
        'sector_id',
        'huddle_date',
        'color',
        'status',
        'expected_discharge_date',
        'clinical_criteria',
        'comments',
        'finalized_at',
        'senior_reviewed_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'color' => DayColor::class,
            'huddle_date' => 'date',
            'expected_discharge_date' => 'date',
            'finalized_at' => 'datetime',
            'senior_reviewed_at' => 'datetime',
        ];
    }

    /**
     * O checklist do dia foi finalizado e não pode ser alterado.
     */
    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function redReasons(): HasMany
    {
        return $this->hasMany(HuddleRedReason::class);
    }

    public function checklistAnswers(): HasMany
    {
        return $this->hasMany(HuddleChecklistAnswer::class);
    }

    /**
     * Cor do dia derivada do checklist: Red se qualquer item respondido estiver Red;
     * Green somente quando há respostas e nenhuma é Red. Sem respostas, mantém Red
     * (regra Red2Green: o dia começa vermelho).
     */
    public function deriveColorFromChecklist(): DayColor
    {
        $answers = $this->relationLoaded('checklistAnswers')
            ? $this->checklistAnswers
            : $this->checklistAnswers()->get();

        if ($answers->isEmpty()) {
            return DayColor::Red;
        }

        $anyRed = $answers->contains(fn ($answer) => $answer->signal === DayColor::Red);

        return $anyRed ? DayColor::Red : DayColor::Green;
    }

    /**
     * Sequência de dias consecutivos na mesma cor, a partir do dia mais recente da
     * internação para trás. Ex.: ['color' => 'red', 'days' => 3] = 3 dias seguidos red.
     *
     * "Consecutivos" considera os dias de huddle registrados em datas sucessivas
     * (a rotina não roda em fins de semana/feriados, então a sequência acompanha os
     * dias úteis registrados). Retorna null quando não há registro.
     *
     * @return array{color: string, days: int}|null
     */
    public static function consecutiveStreak(int $nrAtendimento): ?array
    {
        $days = static::query()
            ->select('huddle_date', 'color')
            ->where('nr_atendimento', $nrAtendimento)
            ->orderByDesc('huddle_date')
            ->get();

        if ($days->isEmpty()) {
            return null;
        }

        $streakColor = $days->first()->color instanceof DayColor
            ? $days->first()->color->value
            : (string) $days->first()->color;

        $count = 0;

        foreach ($days as $day) {
            $color = $day->color instanceof DayColor ? $day->color->value : (string) $day->color;

            if ($color !== $streakColor) {
                break;
            }

            $count++;
        }

        return ['color' => $streakColor, 'days' => $count];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeForSector(Builder $query, int $sectorId): Builder
    {
        return $query->where('sector_id', $sectorId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('huddle_date', $date);
    }

    public function scopeForPatient(Builder $query, int $nrAtendimento): Builder
    {
        return $query->where('nr_atendimento', $nrAtendimento);
    }

    public function isGreen(): bool
    {
        return $this->color === DayColor::Green;
    }

    /**
     * Contagem de dias vermelhos e verdes ao longo da internação, por nr_atendimento.
     *
     * Feito em uma única query agregada (GROUP BY color) apoiada pelo índice
     * (nr_atendimento, color). Retorna sempre as duas chaves, mesmo quando zeradas.
     *
     * @param  int[]  $attendanceNumbers
     * @return array<int, array{red: int, green: int}>
     */
    public static function colorCountsFor(array $attendanceNumbers): array
    {
        $counts = [];

        foreach ($attendanceNumbers as $nr) {
            $counts[$nr] = ['red' => 0, 'green' => 0];
        }

        if (empty($attendanceNumbers)) {
            return $counts;
        }

        $rows = static::query()
            ->select('nr_atendimento', 'color', DB::raw('COUNT(*) as total'))
            ->whereIn('nr_atendimento', $attendanceNumbers)
            ->groupBy('nr_atendimento', 'color')
            ->get();

        foreach ($rows as $row) {
            $color = $row->color instanceof DayColor ? $row->color->value : $row->color;
            $counts[$row->nr_atendimento][$color] = (int) $row->total;
        }

        return $counts;
    }
}
