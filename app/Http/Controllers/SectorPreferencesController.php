<?php

namespace App\Http\Controllers;

use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SectorPreferencesController extends Controller
{
    public function save(Request $request): JsonResponse
    {
        $codes = array_filter((array) $request->input('sectors', []));

        if (empty($codes)) {
            return response()->json(['error' => 'Selecione ao menos um setor.'], 422);
        }

        $user = Auth::user();
        $sectorsFlat = collect(Sector::allowedForPreferences())->keyBy('sector_code');

        // Clear user-specific handover caches for old sectors before replacing preferences.
        $oldSectorIds = UserSectorPreference::where('user_id', $user->id)->pluck('sector_code');
        foreach ($oldSectorIds as $sectorId) {
            foreach (['M', 'T', 'N'] as $shift) {
                Cache::forget("sector_handover_{$sectorId}_{$user->id}_{$shift}");
            }
            Cache::forget("sector_patients_{$sectorId}_{$user->id}");
        }

        UserSectorPreference::where('user_id', $user->id)->delete();

        foreach ($codes as $code) {
            $sector = $sectorsFlat->get((string) $code);
            if (! $sector) {
                continue;
            }

            UserSectorPreference::create([
                'user_id' => $user->id,
                'sector_code' => $sector['sector_code'],
                'sector_name' => $sector['sector_name'],
                'hospital_code' => $sector['hospital_code'],
                'hospital_name' => $sector['hospital_name'],
                'is_active' => true,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
