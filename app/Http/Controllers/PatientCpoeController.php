<?php

namespace App\Http\Controllers;

use App\Services\TasyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PatientCpoeController extends Controller
{
    public function __construct(protected TasyService $tasyService) {}

    public function load(int $attendanceNumber): JsonResponse
    {
        if ($attendanceNumber <= 0) {
            return response()->json(['error' => 'Atendimento inválido'], 422);
        }

        try {
            $cpoeData = $this->tasyService->getPatientCPOEData($attendanceNumber);

            if (!$cpoeData) {
                return response()->json([
                    'cpoe_procedures'     => null,
                    'cpoe_medications'    => null,
                    'cpoe_nutrition'      => null,
                    'cpoe_recommendations' => null,
                    'cpoe_interventions'  => null,
                ]);
            }

            return response()->json([
                'cpoe_procedures'      => $cpoeData->cpoe_procedures ?? null,
                'cpoe_medications'     => $cpoeData->cpoe_medications ?? null,
                'cpoe_nutrition'       => $cpoeData->cpoe_nutrition ?? null,
                'cpoe_recommendations' => $cpoeData->cpoe_recommendations ?? null,
                'cpoe_interventions'   => $cpoeData->cpoe_interventions ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('PatientCpoeController: Error loading CPOE', [
                'attendance' => $attendanceNumber,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Erro ao carregar prescrições'], 500);
        }
    }
}
