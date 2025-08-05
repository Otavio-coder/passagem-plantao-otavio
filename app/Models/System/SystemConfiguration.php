<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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

    public static function allowedHospitalCodes()
    {
        return Cache::rememberForever('allowed_hospital_codes_v2', function () {
            return self::hospitals()->pluck('hospital_code')->filter()->values()->toArray();
        });
    }

    public static function allowedSectorCodes()
    {
        return Cache::rememberForever('allowed_sector_codes_v2', function () {
            return self::sectors()->pluck('sector_code')->filter()->values()->toArray();
        });
    }

    public static function allowedBedCodes()
    {
        return Cache::rememberForever('allowed_bed_codes_v2', function () {
            return self::beds()->get()->map(function($item) {
                return $item->bed_code . '|' . $item->sector_code;
            })->filter()->values()->toArray();
        });
    }

    // Clear cache when configuration changes
    public static function clearConfigCache()
    {
        Cache::forget('allowed_hospital_codes_v2');
        Cache::forget('allowed_sector_codes_v2');
        Cache::forget('allowed_bed_codes_v2');
    }

    protected static function boot()
    {
        parent::boot();
        
        static::saved(function () {
            self::clearConfigCache();
        });
        
        static::deleted(function () {
            self::clearConfigCache();
        });
    }
}