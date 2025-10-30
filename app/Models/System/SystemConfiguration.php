<?php
namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Models\EMR\Core\Bed;
use App\Models\EMR_Antigo\Sector;
use App\Models\EMR_Antigo\Hospital;

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

    /**
     * ============================================
     * MÉTODOS NECESSÁRIOS PARA O SBAR REPORT
     * ============================================
     */

    /**
     * Retorna array de códigos de hospitais permitidos
     * Usado por: SbarReport->loadInitialData()
     */
    public static function allowedHospitalCodes(): array
    {
        return Cache::rememberForever('allowed_hospital_codes', function () {
            return self::hospitals()
                ->pluck('hospital_code')
                ->filter()
                ->map(fn($code) => (int)$code) // Converte para inteiro
                ->values()
                ->toArray();
        });
    }

    /**
     * Retorna array de códigos de setores permitidos
     * Usado por: SbarReport->loadInitialData() e loadSectorsForHospital()
     */
    public static function allowedSectorCodes(): array
    {
        return Cache::rememberForever('allowed_sector_codes', function () {
            return self::sectors()
                ->pluck('sector_code')
                ->filter()
                ->map(fn($code) => (int)$code) // Converte para inteiro
                ->values()
                ->toArray();
        });
    }

    /**
     * ============================================
     * QUERY SCOPES (MÉTODOS AUXILIARES)
     * ============================================
     */

    /**
     * Retorna registros de configuração do tipo 'hospital' ativos
     */
    public static function hospitals()
    {
        return self::where('configuration_type', 'hospital')
            ->where('is_active', true);
    }

    /**
     * Retorna registros de configuração do tipo 'sector' ativos
     */
    public static function sectors()
    {
        return self::where('configuration_type', 'sector')
            ->where('is_active', true);
    }

    /**
     * Retorna registros de configuração do tipo 'bed' ativos
     */
    public static function beds()
    {
        return self::where('configuration_type', 'bed')
            ->where('is_active', true);
    }

    /**
     * ============================================
     * MÉTODO AUXILIAR (USADO PELO CONTROLLER)
     * ============================================
     */

    /**
     * Retorna array de códigos de leitos permitidos no formato "bed_code|sector_code"
     * Usado por: SystemConfigurationController->edit()
     */
    public static function allowedBedCodes(): array
    {
        return Cache::rememberForever('allowed_bed_codes', function () {
            return self::beds()
                ->get()
                ->map(function($item) {
                    return $item->bed_code . '|' . $item->sector_code;
                })
                ->filter()
                ->values()
                ->toArray();
        });
    }

    /**
     * ============================================
     * GERENCIAMENTO DE CACHE
     * ============================================
     */

    /**
     * Limpa cache de configurações
     * Chamado após atualizar configurações
     */
    public static function clearConfigCache(): void
    {
        Cache::forget('allowed_hospital_codes');
        Cache::forget('allowed_sector_codes');
        Cache::forget('allowed_bed_codes');
    }

    /**
     * ============================================
     * RELACIONAMENTOS ELOQUENT
     * ============================================
     */

    /**
     * Hospital relation (EMR/hospital)
     * hospital_code maps to Hospital->nr_sequencia
     */
    public function hospital(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_code', 'nr_sequencia');
    }

    /**
     * Sector relation (EMR/setor_atendimento)
     * sector_code maps to Sector->cd_setor_atendimento
     */
    public function sector(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector_code', 'cd_setor_atendimento');
    }

    /**
     * Bed relation (EMR/unidade_atendimento)
     * bed_code maps to Bed->cd_unidade_basica
     */
    public function bed(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bed::class, 'bed_code', 'cd_unidade_basica');
    }
}
