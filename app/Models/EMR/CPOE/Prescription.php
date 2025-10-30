<?php

namespace App\Models\EMR\CPOE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

/**
 * Model para PRESCR_MEDICA (Cabeçalho da Prescrição Médica)
 *
 * Representa o documento de prescrição médica.
 * Contém informações gerais sobre a prescrição (quando foi feita, por quem, validade, etc)
 */
class Prescription extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PRESCR_MEDICA';
    protected $primaryKey = 'nr_prescricao';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_prescricao' => 'datetime',
        'dt_liberacao' => 'datetime',
        'dt_suspensao' => 'datetime',
        'dt_validade_prescr' => 'datetime',
    ];

    // ========== RELAÇÕES ==========

    /**
     * Paciente da prescrição
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    /**
     * Medicamentos desta prescrição
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class, 'nr_prescricao', 'nr_prescricao');
    }

    /**
     * Procedimentos desta prescrição
     */
    public function procedures(): HasMany
    {
        return $this->hasMany(Procedure::class, 'nr_prescricao', 'nr_prescricao');
    }

    // ========== SCOPES ==========

    /**
     * Prescrições ativas (liberadas, não suspensas, válidas)
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('dt_liberacao')
            ->whereNull('dt_suspensao')
            ->where('dt_validade_prescr', '>=', Carbon::now());
    }

    /**
     * Prescrições do dia
     */
    public function scopeToday($query)
    {
        return $query->whereDate('dt_prescricao', Carbon::today());
    }

    /**
     * Prescrições liberadas (prontas para uso)
     */
    public function scopeReleased($query)
    {
        return $query->whereNotNull('dt_liberacao');
    }

    /**
     * Prescrições não suspensas
     */
    public function scopeNotSuspended($query)
    {
        return $query->whereNull('dt_suspensao');
    }

    /**
     * Prescrições válidas
     */
    public function scopeValid($query)
    {
        return $query->where('dt_validade_prescr', '>=', Carbon::now());
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Verifica se a prescrição está ativa
     */
    public function isActive(): bool
    {
        return !is_null($this->dt_liberacao)
            && is_null($this->dt_suspensao)
            && $this->dt_validade_prescr >= Carbon::now();
    }

    /**
     * Verifica se a prescrição foi liberada
     */
    public function isReleased(): bool
    {
        return !is_null($this->dt_liberacao);
    }

    /**
     * Verifica se a prescrição foi suspensa
     */
    public function isSuspended(): bool
    {
        return !is_null($this->dt_suspensao);
    }

    /**
     * Verifica se a prescrição está válida
     */
    public function isValid(): bool
    {
        return $this->dt_validade_prescr >= Carbon::now();
    }

    /**
     * Retorna status legível da prescrição
     */
    public function getStatusAttribute(): string
    {
        if ($this->isSuspended()) {
            return 'Suspensa';
        }

        if (!$this->isReleased()) {
            return 'Pendente de Liberação';
        }

        if (!$this->isValid()) {
            return 'Expirada';
        }

        return 'Ativa';
    }

    /**
     * Retorna data de prescrição formatada
     */
    public function getPrescriptionDateFormattedAttribute(): string
    {
        return $this->dt_prescricao
            ? Carbon::parse($this->dt_prescricao)->format('d/m/Y H:i')
            : 'Não informado';
    }

    /**
     * Retorna data de validade formatada
     */
    public function getValidityDateFormattedAttribute(): string
    {
        return $this->dt_validade_prescr
            ? Carbon::parse($this->dt_validade_prescr)->format('d/m/Y H:i')
            : 'Não informado';
    }
}
