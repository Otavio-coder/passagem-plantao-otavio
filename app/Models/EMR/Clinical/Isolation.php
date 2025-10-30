<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

/**
 * Model para ATENDIMENTO_PRECAUCAO (Isolamento/Precaução do Paciente)
 *
 * Representa as precauções de isolamento ativas de um paciente internado.
 * Usado para controle de infecções e medidas de biossegurança.
 */
class Isolation extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.ATENDIMENTO_PRECAUCAO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_inicio' => 'datetime',
        'dt_termino' => 'datetime',
        'dt_liberacao' => 'datetime',
        'dt_inativacao' => 'datetime',
    ];

    // ========== RELAÇÕES ==========

    /**
     * Paciente em isolamento
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Motivo do isolamento
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(IsolationReason::class, 'nr_seq_motivo_isol', 'nr_sequencia');
    }

    /**
     * Tipo de precaução
     */
    public function precaution(): BelongsTo
    {
        return $this->belongsTo(Precaution::class, 'nr_seq_precaucao', 'nr_sequencia');
    }

    // ========== SCOPES ==========

    /**
     * Isolamentos ativos (dentro do período e liberados)
     */
    public function scopeActive($query)
    {
        $now = Carbon::now();
        return $query->where('dt_inicio', '<=', $now)
            ->where(function($q) use ($now) {
                $q->whereNull('dt_termino')
                    ->orWhere('dt_termino', '>=', $now);
            })
            ->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');
    }

    /**
     * Isolamentos liberados
     */
    public function scopeReleased($query)
    {
        return $query->whereNotNull('dt_liberacao');
    }

    /**
     * Isolamentos não inativados
     */
    public function scopeNotInactivated($query)
    {
        return $query->whereNull('dt_inativacao');
    }

    /**
     * Isolamentos de hoje
     */
    public function scopeToday($query)
    {
        return $query->whereDate('dt_inicio', Carbon::today());
    }

    /**
     * Isolamentos em período específico
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('dt_inicio', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ]);
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Verifica se o isolamento está ativo
     */
    public function isActive(): bool
    {
        $now = Carbon::now();

        return $this->dt_inicio <= $now
            && (is_null($this->dt_termino) || $this->dt_termino >= $now)
            && !is_null($this->dt_liberacao)
            && is_null($this->dt_inativacao);
    }

    /**
     * Verifica se o isolamento foi liberado
     */
    public function isReleased(): bool
    {
        return !is_null($this->dt_liberacao);
    }

    /**
     * Verifica se o isolamento foi inativado
     */
    public function isInactivated(): bool
    {
        return !is_null($this->dt_inativacao);
    }

    /**
     * Verifica se o isolamento expirou
     */
    public function isExpired(): bool
    {
        return !is_null($this->dt_termino) && Carbon::parse($this->dt_termino)->isPast();
    }

    /**
     * Retorna descrição completa do isolamento
     */
    public function getFullDescriptionAttribute(): string
    {
        $precaution = $this->precaution ? $this->precaution->ds_precaucao : 'Precaução não especificada';
        $reason = $this->reason ? $this->reason->ds_motivo : 'Motivo não especificado';

        return "{$precaution} - {$reason}";
    }

    /**
     * Retorna data de início formatada
     */
    public function getStartDateFormattedAttribute(): string
    {
        return $this->dt_inicio
            ? Carbon::parse($this->dt_inicio)->format('d/m/Y H:i')
            : 'Não informado';
    }

    /**
     * Retorna data de término formatada
     */
    public function getEndDateFormattedAttribute(): string
    {
        return $this->dt_termino
            ? Carbon::parse($this->dt_termino)->format('d/m/Y H:i')
            : 'Indeterminado';
    }

    /**
     * Retorna duração do isolamento
     */
    public function getDurationAttribute(): string
    {
        if (!$this->dt_inicio) {
            return 'Não informado';
        }

        $start = Carbon::parse($this->dt_inicio);
        $end = $this->dt_termino ? Carbon::parse($this->dt_termino) : Carbon::now();

        $days = $start->diffInDays($end);

        if ($days == 0) {
            return 'Menos de 1 dia';
        }

        return "{$days} dia(s)";
    }

    /**
     * Retorna status legível do isolamento
     */
    public function getStatusAttribute(): string
    {
        if ($this->isInactivated()) {
            return 'Inativado';
        }

        if ($this->isExpired()) {
            return 'Expirado';
        }

        if ($this->isActive()) {
            return 'Ativo';
        }

        if (!$this->isReleased()) {
            return 'Pendente de Liberação';
        }

        return 'Aguardando Início';
    }

    // ========== MÉTODOS ESTÁTICOS ==========

    /**
     * Verifica se um paciente tem isolamento ativo
     */
    public static function hasActiveIsolation($attendanceNumber): bool
    {
        return static::where('nr_atendimento', $attendanceNumber)
            ->active()
            ->exists();
    }

    /**
     * Busca isolamentos ativos de um paciente formatados
     */
    public static function getActiveForPatient($attendanceNumber): array
    {
        return static::where('nr_atendimento', $attendanceNumber)
            ->active()
            ->with(['reason', 'precaution'])
            ->orderByDesc('dt_inicio')
            ->get()
            ->map(function($isolation) {
                return [
                    'nr_sequencia' => $isolation->nr_sequencia,
                    'precaucao' => $isolation->precaution ? $isolation->precaution->ds_precaucao : 'Não especificada',
                    'motivo' => $isolation->reason ? $isolation->reason->ds_motivo : 'Não especificado',
                    'descricao_completa' => $isolation->full_description,
                    'dt_inicio' => $isolation->start_date_formatted,
                    'dt_termino' => $isolation->end_date_formatted,
                    'duracao' => $isolation->duration,
                    'status' => $isolation->status,
                ];
            })
            ->toArray();
    }

    /**
     * Busca motivos de isolamento formatados (string simples)
     */
    public static function getIsolationReasonsForPatient($attendanceNumber): ?string
    {
        $isolations = static::where('nr_atendimento', $attendanceNumber)
            ->active()
            ->with(['reason', 'precaution'])
            ->get();

        if ($isolations->isEmpty()) {
            return null;
        }

        return $isolations->map(function($isolation) {
            return $isolation->full_description;
        })->implode('; ');
    }

    /**
     * Busca resumo de isolamento (sim/não)
     */
    public static function getIsolationStatusForPatient($attendanceNumber): string
    {
        return static::hasActiveIsolation($attendanceNumber) ? 'Sim' : 'Não';
    }
}
