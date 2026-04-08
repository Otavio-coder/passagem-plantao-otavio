<?php

namespace App\Http\Controllers;

use App\Models\EMR\Core\Hospital;
use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SystemConfigurationController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const HOSPITAL_COLORS = [
        '1' => '#8C3134',
        '8' => '#42909A',
        '2' => '#A96538',
        '6' => '#3E6B35',
        '4' => '#4586B4',
        '3' => '#9574A1',
        '25' => '#CE7D74',
        '7' => '#563174',
        '18' => '#073772',
    ];

    /**
     * Exibe página de preferências do usuário
     */
    public function index()
    {
        $user = Auth::user();

        // Obtém setores disponíveis dos hospitais permitidos
        $availableSectors = $this->getAvailableSectors();

        // Obtém preferências atuais do usuário
        $userPreferences = $user->sectorPreferences()
            ->get()
            ->mapWithKeys(function ($pref) {
                return [$pref->sector_code => $pref];
            });

        // Agrupa setores por hospital para exibição
        $sectorsByHospital = collect($availableSectors)->groupBy('hospital_code');

        // Códigos de setores selecionados pelo usuário
        $selectedSectors = $userPreferences->keys()->toArray();

        $hospitalSections = $this->buildHospitalSections($sectorsByHospital, $selectedSectors);

        return view('configuration.system.index', [
            'sectorsByHospital' => $sectorsByHospital,
            'selectedSectors' => $selectedSectors,
            'userPreferences' => $userPreferences,
            'hospitalSections' => $hospitalSections,
        ]);
    }

    /**
     * Atualiza preferências de setores do usuário
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        try {
            $selectedSectors = $request->input('sector_codes', []);

            if (empty($selectedSectors)) {
                return redirect()
                    ->route('user.preferences.index')
                    ->with('error', 'Selecione pelo menos um setor.');
            }

            // Valida que os setores selecionados estão na lista permitida
            $availableSectors = $this->getAvailableSectors();
            $availableSectorCodes = collect($availableSectors)->pluck('sector_code')->toArray();
            $validSectors = array_intersect($selectedSectors, $availableSectorCodes);

            if (empty($validSectors)) {
                return redirect()
                    ->route('user.preferences.index')
                    ->with('error', 'Seleção de setores inválida.');
            }

            // Captura setores anteriores para auditoria
            $previousSectors = $user->sectorPreferences()
                ->pluck('sector_name', 'sector_code')
                ->toArray();

            // Remove preferências existentes
            UserSectorPreference::where('user_id', $user->id)->delete();

            // Cria novas preferências
            $sectorsMap = collect($availableSectors)->keyBy('sector_code');

            foreach ($validSectors as $sectorCode) {
                $sector = $sectorsMap->get($sectorCode);
                if ($sector) {
                    UserSectorPreference::create([
                        'user_id' => $user->id,
                        'sector_code' => $sectorCode,
                        'sector_name' => $sector['sector_name'],
                        'hospital_code' => $sector['hospital_code'],
                        'hospital_name' => $sector['hospital_name'],
                    ]);
                }
            }

            // Auditoria
            $newSectors = collect($validSectors)
                ->mapWithKeys(fn ($code) => [$code => $sectorsMap->get($code)['sector_name'] ?? $code])
                ->toArray();

            Log::channel('audit')->info('preferences.updated', [
                'user_id' => $user->id,
                'user' => $user->name,
                'previous_sectors' => $previousSectors,
                'new_sectors' => $newSectors,
                'ip' => $request->ip(),
            ]);

            // Limpa cache
            Cache::forget('user_sectors_'.$user->id);

            return redirect()
                ->route('user.preferences.index')
                ->with('success', 'Preferências atualizadas com sucesso!');

        } catch (\Exception $e) {
            Log::error('[UserPreferences] Erro ao atualizar preferências', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('user.preferences.index')
                ->with('error', 'Erro ao atualizar preferências: '.$e->getMessage());
        }
    }

    /**
     * Obtém setores disponíveis dos hospitais permitidos
     * Apenas setores com cd_classif_setor = 3 ou 4 (internação)
     */
    private function getAvailableSectors(): array
    {
        return Sector::allowedForPreferences();
    }

    /**
     * Obtém IDs de hospitais permitidos
     */
    public static function getAllowedHospitalIds(): array
    {
        return array_map('intval', (array) config('hospitals.allowed_ids', []));
    }

    /**
     * @param  Collection<int|string, Collection<int, array<string, mixed>>>  $sectorsByHospital
     * @param  array<int, string>  $selectedSectors
     * @return array<int, array<string, mixed>>
     */
    private function buildHospitalSections($sectorsByHospital, array $selectedSectors): array
    {
        return $sectorsByHospital->map(function ($sectors, $hospitalCode) use ($selectedSectors): array {
            $hospitalName = (string) ($sectors->first()['hospital_name'] ?? 'Hospital');
            $selectedCount = $sectors->filter(fn ($sector) => in_array($sector['sector_code'], $selectedSectors, true))->count();

            return [
                'code' => (string) $hospitalCode,
                'name' => $hospitalName,
                'name_lower' => mb_strtolower($hospitalName),
                'icon_label' => mb_substr($hospitalName, 0, 1),
                'color' => self::HOSPITAL_COLORS[(string) $hospitalCode] ?? '#004D9D',
                'total_sectors' => $sectors->count(),
                'selected_count' => $selectedCount,
                'is_expanded' => $selectedCount > 0,
                'sectors' => $sectors->map(function ($sector) use ($selectedSectors): array {
                    $isChecked = in_array($sector['sector_code'], $selectedSectors, true);

                    return [
                        'code' => $sector['sector_code'],
                        'name' => $sector['sector_name'],
                        'name_lower' => mb_strtolower($sector['sector_name']),
                        'is_checked' => $isChecked,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
