<?php

namespace App\Http\Controllers;

use App\Services\TasyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PatientRecomendacoesController extends Controller
{
    public function __construct(protected TasyService $tasyService) {}

    public function load(int $attendanceNumber): JsonResponse
    {
        Log::debug('PatientRecomendacoesController: Requisição recebida', [
            'attendance' => $attendanceNumber,
            'authenticated' => auth()->check(),
            'user_id' => auth()->id(),
        ]);

        if ($attendanceNumber <= 0) {
            return response()->json(['error' => 'Atendimento inválido'], 422);
        }

        try {
            $data = $this->tasyService->getPatientRecomendacoesData($attendanceNumber);

            if (! $data) {
                return response()->json([
                    'procedimentos' => null,
                    'medicamentos' => null,
                    'nutricao' => null,
                    'recomendacoes' => null,
                    'intervencoes' => null,
                ]);
            }

            return response()->json([
                'procedimentos' => $data->procedimentos ?? null,
                'medicamentos' => $data->medicamentos ?? null,
                'nutricao' => $data->nutricao ?? null,
                'recomendacoes' => $data->recomendacoes ?? null,
                'intervencoes' => $data->intervencoes ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('PatientRecomendacoesController: Erro ao carregar recomendações', [
                'attendance' => $attendanceNumber,
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Erro ao carregar recomendações'], 500);
        }
    }
}
