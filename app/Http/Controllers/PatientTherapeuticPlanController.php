<?php

namespace App\Http\Controllers;

use App\Services\TasyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientTherapeuticPlanController extends Controller
{
    public function __construct(protected TasyService $tasyService) {}

    /**
     * Pre-warms the therapeutic plan cache for a batch of patients.
     *
     * Called by the SBAR page after the sector loads so subsequent modal
     * opens are served from Redis (~5ms) instead of hitting Oracle.
     *
     * POST /patient-care/therapeutic-plan/warm
     * Body: { "attendance_numbers": [123, 456, ...] }  (max 50)
     */
    public function warmCache(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_numbers'   => 'required|array|min:1|max:50',
            'attendance_numbers.*' => 'required|integer|min:1',
        ]);

        $warmed = $this->tasyService->batchWarmTherapeuticPlans(
            $validated['attendance_numbers']
        );

        return response()->json([
            'warmed' => $warmed,
            'total'  => count($validated['attendance_numbers']),
        ]);
    }
}
