<?php

namespace App\Http\Controllers;

use App\Jobs\WarmSectorCacheJob;
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
     * Dispatches WarmSectorCacheJob instead of warming inline so this endpoint
     * returns immediately (< 5ms) without holding a PHP-FPM worker for 10-30s
     * during a cold Oracle load.
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

        $queued = 0;
        $skipped = 0;

        foreach ($validated['sector_ids'] as $rawId) {
            $sectorId = (int) $rawId;

            if (! in_array($sectorId, $allowedCodes)) {
                continue;
            }

            if (Cache::has("sector_demographics_{$sectorId}")) {
                $skipped++;

                continue;
            }

            WarmSectorCacheJob::dispatch($sectorId);
            $queued++;
        }

        return response()->json(['queued' => $queued, 'skipped' => $skipped]);
    }
}
