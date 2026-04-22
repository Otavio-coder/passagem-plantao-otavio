<?php

namespace App\Http\Controllers;

use App\Services\Tasy\TasyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SectorWarmController extends Controller
{
    public function __construct(protected TasyService $tasyService) {}

    /**
     * Pre-warms the sector patients cache for a list of sector IDs.
     *
     * Called by the SBAR page (fire-and-forget) for all user sectors after the
     * active sector loads, so subsequent sector switches are instant (~5ms cache hit).
     *
     * Only sectors belonging to the authenticated user are warmed.
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

        // Validate against user's allowed sectors
        $user = Auth::user();
        $allowedCodes = $user->sectorPreferences()->pluck('sector_code')->map('intval')->toArray();

        $warmed = 0;
        $skipped = 0;

        foreach ($validated['sector_ids'] as $sectorId) {
            $sectorId = (int) $sectorId;
            if (! in_array($sectorId, $allowedCodes)) {
                continue;
            }

            if ($this->tasyService->warmSbarSectorCache($sectorId)) {
                $warmed++;
            } else {
                $skipped++; // already cached
            }
        }

        return response()->json(['warmed' => $warmed, 'skipped' => $skipped]);
    }
}
