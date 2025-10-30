<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class MedicalOpinion extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PARECER_MEDICO_REQ';
    protected $primaryKey = 'nr_parecer';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_liberacao' => 'datetime',
        'dt_inativacao' => 'datetime',
    ];

    // ==================== RELACIONAMENTOS ====================

    public function responses(): HasMany
    {
        return $this->hasMany(MedicalOpinionResponse::class, 'nr_parecer', 'nr_parecer')
            ->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');
    }

    public function scopePending($query)
    {
        return $query->where(function($q) {
            $q->where('ie_situacao', 'P')
                ->orWhere('ie_situacao', 'A')
                ->orWhereNull('ie_situacao');
        });
    }

    // ==================== MÉTODOS ESTÁTICOS ====================

    /**
     * Retorna avaliações de equipe multidisciplinar ATIVAS
     */
    public static function getMultidisciplinaryTeamEvaluations(int $attendanceNumber): array
    {
        try {
            $opinions = static::where('nr_atendimento', $attendanceNumber)
                ->active()
                ->pending() // Apenas pendentes/em andamento
                ->get();

            $teams = [
                'fisioterapia' => false,
                'psicologia' => false,
                'nutricao' => false,
                'fonoaudiologia' => false,
                'servico_social' => false,
            ];

            foreach ($opinions as $opinion) {
                $reason = strtolower($opinion->ds_motivo_consulta ?? '');

                if (str_contains($reason, 'fisio')) {
                    $teams['fisioterapia'] = true;
                }
                if (str_contains($reason, 'psico')) {
                    $teams['psicologia'] = true;
                }
                if (str_contains($reason, 'nutri')) {
                    $teams['nutricao'] = true;
                }
                if (str_contains($reason, 'fono')) {
                    $teams['fonoaudiologia'] = true;
                }
                if (str_contains($reason, 'social') || str_contains($reason, 'assistente')) {
                    $teams['servico_social'] = true;
                }
            }

            return $teams;
        } catch (\Throwable) {
            return [
                'fisioterapia' => false,
                'psicologia' => false,
                'nutricao' => false,
                'fonoaudiologia' => false,
                'servico_social' => false,
            ];
        }
    }
}
