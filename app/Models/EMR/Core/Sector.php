<?php

namespace App\Models\EMR\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Sector extends Model
{
    private const ALLOWED_FOR_PREFERENCES_CACHE_KEY = 'allowed_sectors_for_preferences_v1';

    private const ALLOWED_FOR_PREFERENCES_CACHE_TTL = 3600;

    protected $connection = 'tasy';

    protected $table = 'TASY.SETOR_ATENDIMENTO';

    protected $primaryKey = 'cd_setor_atendimento';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'nr_seq_agrupamento', 'nr_sequencia');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class, 'cd_setor_atendimento', 'cd_setor_atendimento');
    }

    /**
     * Scope para filtrar apenas setores permitidos para o usuário atual
     * Baseado nas preferências do usuário em user_sector_preferences
     */
    public function scopeAllowed($query)
    {
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Obtém os códigos de setores preferidos do usuário
        $preferredSectorCodes = $user->sectorPreferences()
            ->pluck('sector_code')
            ->map(fn ($code) => (string) $code)
            ->toArray();

        if (empty($preferredSectorCodes)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($this->getTable().'.cd_setor_atendimento', $preferredSectorCodes);
    }

    /**
     * Retorna setores permitidos para configuração de preferências.
     *
     * Estrutura de retorno:
     * [
     *   ['sector_code' => string, 'sector_name' => string,
     *    'hospital_code' => string, 'hospital_name' => string],
     *   ...
     * ]
     *
     * @return array<int, array{sector_code: string, sector_name: string, hospital_code: string, hospital_name: string}>
     */
    public static function allowedForPreferences(): array
    {
        return Cache::remember(self::ALLOWED_FOR_PREFERENCES_CACHE_KEY, self::ALLOWED_FOR_PREFERENCES_CACHE_TTL, function (): array {
            $allowedHospitalIds = array_map('intval', (array) config('hospitals.allowed_ids', []));

            if (empty($allowedHospitalIds)) {
                return [];
            }

            $hospitals = Hospital::query()
                ->whereIn('nr_sequencia', $allowedHospitalIds)
                ->where('ie_situacao', 'A')
                ->get()
                ->mapWithKeys(fn (Hospital $hospital) => [(string) $hospital->nr_sequencia => $hospital->ds_agrupamento]);

            $sectors = self::query()
                ->whereIn('nr_seq_agrupamento', $allowedHospitalIds)
                ->whereIn('cd_classif_setor', [1, 3, 4])
                ->where('ie_situacao', 'A')
                ->orderByRaw('NVL(ds_prescricao, ds_setor_atendimento)')
                ->get();

            if ($sectors->isEmpty()) {
                return [];
            }

            $codesWithBeds = Bed::query()
                ->whereIn('cd_setor_atendimento', $sectors->pluck('cd_setor_atendimento')->all())
                ->where('ie_situacao', 'A')
                ->distinct()
                ->pluck('cd_setor_atendimento')
                ->flip()
                ->toArray();

            return $sectors
                ->filter(fn (Sector $sector) => isset($codesWithBeds[$sector->cd_setor_atendimento]))
                ->map(fn (Sector $sector) => [
                    'sector_code' => (string) $sector->cd_setor_atendimento,
                    'sector_name' => (string) ($sector->ds_prescricao ?? $sector->ds_setor_atendimento),
                    'hospital_code' => (string) $sector->nr_seq_agrupamento,
                    'hospital_name' => (string) $hospitals->get((string) $sector->nr_seq_agrupamento, 'Hospital'),
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * @return array<string, array<int, array{sector_code: string, sector_name: string, hospital_code: string, hospital_name: string}>>
     */
    public static function allowedForPreferencesGroupedByHospital(): array
    {
        return collect(self::allowedForPreferences())
            ->groupBy('hospital_code')
            ->map(fn (Collection $rows) => $rows->values()->toArray())
            ->toArray();
    }
}
