<?php

namespace App\Http\Controllers;

use App\Services\Tasy\TasyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPrescriptionsController extends Controller
{
    public function __construct(protected TasyService $tasyService) {}

    /**
     * Pre-warms the prescriptions cache for a batch of patients.
     *
     * Called by the SBAR page after the sector loads so subsequent modal
     * opens are served from Redis (~5ms) instead of hitting Oracle.
     *
     * POST /patient-care/prescriptions/warm
     * Body: { "attendance_numbers": [123, 456, ...] }  (max 50)
     */
    public function warmCache(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_numbers' => 'required|array|min:1|max:50',
            'attendance_numbers.*' => 'required|integer|min:1',
        ]);

        $warmed = $this->tasyService->batchWarmTherapeuticPlan(
            $validated['attendance_numbers']
        );

        return response()->json([
            'warmed' => $warmed,
            'total' => count($validated['attendance_numbers']),
        ]);
    }

    /**
     * Pre-warms the patient details cache (getSbarPatientDetails) for a batch.
     *
     * POST /patient-care/details/warm
     * Body: { "attendance_numbers": [123, 456, ...] }  (max 30)
     */
    public function warmDetailsCache(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_numbers' => 'required|array|min:1|max:30',
            'attendance_numbers.*' => 'required|integer|min:1',
        ]);

        $warmed = $this->tasyService->batchWarmPatientDetails(
            $validated['attendance_numbers']
        );

        return response()->json([
            'warmed' => $warmed,
            'total' => count($validated['attendance_numbers']),
        ]);
    }
}
