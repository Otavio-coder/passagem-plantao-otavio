<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\EMR\Core\Patient;
use App\Models\EMR\Core\Person;

class Allergy extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.W_PAN_PACIENTE';
    protected $primaryKey = 'cd_pessoa_paciente';
    public $incrementing = false;
    public $timestamps = false;

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class, 'cd_pessoa_paciente', 'cd_pessoa_fisica');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_pessoa_paciente', 'cd_pessoa_fisica');
    }

    /**
     * Verifica se o paciente tem alergia registrada
     */
    public function hasAllergy(): bool
    {
        return !empty($this->ds_alergias) && strlen(trim($this->ds_alergias)) > 0;
    }

    /**
     * Retorna alergias limpas (remove textos padrão)
     */
    public function getCleanAllergiesAttribute(): string
    {
        if (empty($this->ds_alergias)) {
            return 'Sem alergias registradas';
        }

        $cleaned = preg_replace(
            '/ - (Não informado|desconhecido|N\/A)[^;]*/i',
            '',
            $this->ds_alergias
        );

        return trim($cleaned) ?: 'Sem alergias registradas';
    }

    /**
     * Scope para buscar pacientes com alergia
     */
    public function scopeWithAllergies($query)
    {
        return $query->whereNotNull('ds_alergias')
            ->where('ds_alergias', '!=', '');
    }
}
