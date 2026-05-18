<?php

namespace App\Http\Controllers;

use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
