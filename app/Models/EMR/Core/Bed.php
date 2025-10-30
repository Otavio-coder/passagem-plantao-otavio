<?php

namespace App\Models\EMR\Core;

use App\Models\System\SystemConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function scopeAllowed($query)
    {
        $allowed = SystemConfiguration::allowedBedCodes();
        if (empty($allowed)) {
            return $query->whereRaw('1 = 0');
        }

        $bedCodes = [];
        $sectorMap = [];

        foreach ($allowed as $composite) {
            if (!str_contains($composite, '|')) {
                if ($composite !== '') $bedCodes[] = (string) $composite;
                continue;
            }
            [$bed, $sector] = explode('|', $composite, 2);
            if ($bed !== '') $bedCodes[] = (string) $bed;
            if ($sector !== '') $sectorMap[(string) $sector] = true;
        }

        $bedCodes = array_values(array_filter($bedCodes, fn($v) => $v !== ''));
        if (empty($bedCodes)) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn($this->getTable() . '.cd_unidade_basica', $bedCodes);

        if (!empty($sectorMap)) {
            $sectorCodes = array_values(array_filter(array_keys($sectorMap), fn($v) => $v !== ''));
            if (!empty($sectorCodes)) {
                $query->whereIn($this->getTable() . '.cd_setor_atendimento', $sectorCodes);
            }
        }

        return $query;
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
