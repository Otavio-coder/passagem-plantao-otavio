<?php

namespace App\Models\EMR\CPOE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use Carbon\Carbon;

class Nutrition extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.CPOE_DIETA';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_inicio' => 'date',
        'dt_fim' => 'date',
        'dt_liberacao' => 'datetime',
        'dt_suspensao' => 'datetime',
        'dt_inicio_jejum' => 'datetime',
        'dt_fim_jejum' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function diet(): BelongsTo
    {
        return $this->belongsTo(Diet::class, 'cd_dieta', 'cd_dieta');
    }

    public function fastingType(): BelongsTo
    {
        return $this->belongsTo(FastingType::class, 'nr_seq_tipo', 'nr_sequencia');
    }

    /**
     * Scope para dietas ativas
     */
    public function scopeActive($query)
    {
        $today = Carbon::today();
        return $query->whereNotNull('dt_liberacao')
            ->whereNull('dt_suspensao')
            ->whereDate('dt_inicio', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('dt_fim')
                    ->orWhereDate('dt_fim', '>=', $today);
            });
    }

    /**
     * Verifica se é jejum
     */
    public function isJejum(): bool
    {
        return $this->ie_tipo_dieta === 'J';
    }

    /**
     * Retorna tipo de nutrição
     */
    public function getNutritionTypeAttribute(): string
    {
        if ($this->isJejum()) {
            return 'Jejum';
        }

        if ($this->ie_dieta_enteral === 'S') {
            return 'Enteral';
        }

        if ($this->ie_dieta_especial === 'S') {
            return 'Especial';
        }

        return 'Dieta';
    }

    /**
     * Retorna turno baseado no primeiro horário
     */
    public function getShiftAttribute(): string
    {
        if (empty($this->hr_prim_horario)) {
            return 'NOITE';
        }

        $hour = intval(substr($this->hr_prim_horario, 0, 2));

        if ($hour >= 7 && $hour < 13) {
            return 'MANHÃ';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'TARDE';
        } else {
            return 'NOITE';
        }
    }

    /**
     * Retorna descrição do material via função TASY
     */
    public function getMaterialDescriptionAttribute(): string
    {
        if (empty($this->cd_material)) {
            return '';
        }

        return \DB::connection('tasy')->selectOne(
            "SELECT TASY.OBTER_DESC_MATERIAL(:cd) AS descricao FROM dual",
            ['cd' => $this->cd_material]
        )->descricao ?? '';
    }
}
