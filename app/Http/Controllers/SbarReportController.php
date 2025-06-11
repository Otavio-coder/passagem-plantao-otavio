<?php

namespace App\Http\Controllers;

use App\Models\EMR\SbarReport; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SbarReportController extends Controller
{
    /**
     * Setores permitidos
     */
    protected $allowedSectorIds = [20277, 1228, 9598, 1147, 4855];

    /**
     * Exibir view principal
     */
    public function index(Request $request)
    {
        return view('sbar.report');
    }

    /**
     * API endpoint para dados do relatório
     */
    public function getData(Request $request)
    {
        $sectorFilter = $request->input('sector_filter');
        
        if (!in_array($sectorFilter, $this->allowedSectorIds)) {
            $sectorFilter = $this->allowedSectorIds[0];
        }
        
        try {
            $sbarReport = new SbarReport();
            $reportData = $sbarReport->getBasePatientData($sectorFilter);
            
            return response()->json([
                'success' => true,
                'data' => $reportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar dados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalhes do paciente
     */
    public function getPatientDetails(Request $request)
    {
        $attendanceNumber = $request->input('attendance');
        
        if (empty($attendanceNumber)) {
            return response()->json(['error' => 'Attendance number required'], 400);
        }
        
        try {
            $sbarReport = new SbarReport();
            $patientDetails = $sbarReport->getPatientDetails(trim((string)$attendanceNumber));
            
            return response()->json([
                'success' => true,
                'data' => $patientDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}