<?php

namespace App\Http\Controllers;

use App\Services\PatientData\PatientDataLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SectorWarmController extends Controller
{
    /**
     * Pre-warms all PatientDataLoader caches for a list of sector IDs.
     *
     * Called fire-and-forget by the SBAR page after the active sector loads,
     * so subsequent sector switches hit cache and are near-instant.
     *
     * POST /sectors/warm
     * Body: { "sector_ids": [123, 456, ...] }  (max 20)
     */
    public function warm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sector_ids' => 'required|array|min:1|max:20',
            'sector_ids.*' => 'required|integer|min:1',
        ]);

        $allowedCodes = Auth::user()
            ->sectorPreferences()
            ->pluck('sector_code')
            ->map('intval')
            ->toArray();

        $warmed = 0;
        $skipped = 0;

        foreach ($validated['sector_ids'] as $rawId) {
            $sectorId = (int) $rawId;

            if (! in_array($sectorId, $allowedCodes)) {
                continue;
            }

            // Skip if demographics cache already warm — all other caches are likely warm too
            if (Cache::has("sector_demographics_{$sectorId}")) {
                $skipped++;

                continue;
            }

            try {
                PatientDataLoader::forSector($sectorId)
                    ->include('demographics', 'scales', 'pending_events', 'clinical', 'multidisciplinary', 'surgery')
                    ->get();
                $warmed++;
            } catch (\Throwable) {
                // ignore — warm is best-effort
            }
        }

        return response()->json(['warmed' => $warmed, 'skipped' => $skipped]);
    }
}
