<?php

namespace App\Http\Controllers;

use App\Models\EMR\Core\Hospital;
use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SystemConfigurationController extends Controller
{
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

        return view('configuration.system.index', [
            'sectorsByHospital' => $sectorsByHospital,
            'selectedSectors' => $selectedSectors,
            'userPreferences' => $userPreferences,
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
}
