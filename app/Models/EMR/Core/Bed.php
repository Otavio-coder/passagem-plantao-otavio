<?php

namespace App\Models\EMR\Core;

use App\Models\System\UserSectorPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Bed extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.UNIDADE_ATENDIMENTO';
    protected $primaryKey = 'cd_unidade_basica';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    // Corrected: relate to Core Sector model (was pointing to EMR_Antigo)
    public function sector(): BelongsTo
    {
        return $this->belongsTo(\App\Models\EMR\Core\Sector::class, 'cd_setor_atendimento', 'cd_setor_atendimento');
    }

    public function currentPatient(): HasOne
    {
        return $this->hasOne(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function scopeActive($query)
    {
        return $query->where($this->getTable() . '.ie_situacao', 'A');
    }

    /**
     * Scope para filtrar apenas leitos em setores permitidos para o usuário atual
     * Baseado nas preferências do usuário em user_sector_preferences
     */
    public function scopeAllowed($query)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Obtém os códigos de setores preferidos do usuário
        $preferredSectorCodes = $user->sectorPreferences()
            ->pluck('sector_code')
            ->map(fn($code) => (string) $code)
            ->toArray();

        if (empty($preferredSectorCodes)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($this->getTable() . '.cd_setor_atendimento', $preferredSectorCodes);
    }

    public function getSequenceAttribute()
    {
        return $this->cd_unidade_basica;
    }

    public function getStatusAttribute()
    {
        return $this->ie_situacao ?? null;
    }
}
