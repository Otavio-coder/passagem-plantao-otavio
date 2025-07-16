<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    protected $table = 'system_configurations';

    protected $fillable = [
        'hospital_code',
        'hospital_name',
        'sector_code',
        'sector_name',
        'bed_code',
        'bed_name',
        'is_active',
        'configuration_type',
        'configured_by',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    // Scopes para facilitar consultas
    public function scopeHospitals($query)
    {
        return $query->where('configuration_type', 'hospital')->where('is_active', true);
    }

    public function scopeSectors($query)
    {
        return $query->where('configuration_type', 'sector')->where('is_active', true);
    }

    public function scopeBeds($query)
    {
        return $query->where('configuration_type', 'bed')->where('is_active', true);
    }

    // Retorna arrays de códigos permitidos
    public static function allowedHospitalCodes()
    {
        return self::hospitals()->pluck('hospital_code')->toArray();
    }

    public static function allowedSectorCodes()
    {
        return self::sectors()->pluck('sector_code')->toArray();
    }

    public static function allowedBedCodes()
    {
        return self::beds()->get()->map(function($item) {
            return $item->bed_code . '|' . $item->sector_code;
        })->toArray();
    }
}