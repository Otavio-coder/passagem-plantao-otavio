<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Patient extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    /**
     * Get basic patient information with bed status - CLEANED UP
     */
    public function getBasicPatientData($sectorId = null, $filters = [])
    {
        $cacheKey = "patient_basic_data_" . ($sectorId ?? 'all') . '_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 120, function() use ($sectorId, $filters) {
            // Use BedUnit model for bed structure instead of duplicating
            $bedUnit = new BedUnit();
            $beds = $bedUnit->getBedsWithBasicPatientInfo($sectorId);
            
            return $beds->map(function($bed) {
                return $this->formatBedRecord($bed);
            });
        });
    }
    
    /**
     * Format bed record with patient data - SIMPLIFIED
     */
    private function formatBedRecord($bed)
    {
        $hasPatient = $bed->is_occupied;
        
        // Generate initials only for patients
        $initials = '';
        if ($hasPatient && !empty($bed->patient_name)) {
            $words = array_filter(explode(' ', trim($bed->patient_name)));
            $initials = strtoupper(substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1));
            if (strlen($initials) < 2) {
                $initials = strtoupper(substr(trim($bed->patient_name), 0, 2));
            }
        }
        
        // CORREÇÃO: Cálculo mais preciso de internment_days
        $internmentDays = null;
        if ($hasPatient && !empty($bed->dt_entrada)) {
            try {
                $entryDate = \Carbon\Carbon::parse($bed->dt_entrada);
                $today = \Carbon\Carbon::now();
                $internmentDays = $entryDate->diffInDays($today, false); // false = não absoluto, pode ser negativo
                
                // Se for negativo (entrada no futuro), considerar como 0
                if ($internmentDays < 0) {
                    $internmentDays = 0;
                }
            } catch (\Exception $e) {
                $internmentDays = null;
            }
        }
        
        $ageDetailed = $hasPatient ? $this->formatDetailedAge($bed->patient_age_years, $bed->patient_age_months, $bed->patient_age_days) : '';
        $birthDateFormatted = $hasPatient && $bed->patient_birth_date ? Carbon::parse($bed->patient_birth_date)->format('d/m/Y') : '';
        
        return (object) [
            'cd_unidade_basica' => $bed->cd_unidade_basica,
            'nr_atendimento' => $bed->current_attendance,
            'cd_setor_atendimento' => $bed->cd_setor_atendimento,
            'ds_setor_atendimento' => $bed->ds_setor_atendimento,
            'hospital_name' => $bed->hospital_name,
            'cd_pessoa_fisica' => $bed->cd_pessoa_fisica,
            'nm_pessoa_fisica' => $bed->patient_name,
            'initials' => $initials,
            'age' => $hasPatient ? intval($bed->patient_age_years ?? 0) : null,
            'age_detailed' => $ageDetailed,
            'birth_date' => $birthDateFormatted,
            'sexo' => $bed->patient_gender,
            'nr_prontuario' => $bed->patient_record_number,
            'dt_entrada' => $bed->dt_entrada,
            'internment_days' => $internmentDays,
            'medico_responsavel' => $bed->responsible_doctor,
            'convenio' => $bed->insurance_plan,
            'has_patient' => $hasPatient,
        ];
    }
    
    /**
     * Format detailed age - HELPER METHOD
     */
    private function formatDetailedAge($years, $months, $days)
    {
        $ageDetailed = intval($years ?? 0) . 'a';
        if (intval($months ?? 0) > 0) $ageDetailed .= ' ' . intval($months) . 'm';
        if (intval($days ?? 0) > 0) $ageDetailed .= ' ' . intval($days) . 'd';
        return $ageDetailed;
    }
    
    /**
     * Get basic patient details for modal - SIMPLIFIED
     */
    public function getPatientBasicDetails($attendanceNumber)
    {
        $cacheKey = "patient_basic_details_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            $result = DB::connection('tasy')->select("
                SELECT
                    ua.cd_unidade_basica,
                    ua.cd_setor_atendimento,
                    atp.cd_pessoa_fisica,
                    atp.nr_atendimento,
                    tasy.obter_nome_paciente(atp.nr_atendimento) AS nm_pessoa_fisica,
                    pf.nr_prontuario,
                    FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS idade_anos,
                    TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS tempo_internacao_dias,
                    tasy.obter_desc_convenio(tasy.obter_convenio_atendimento(atp.nr_atendimento)) AS convenio,
                    tasy.obter_medico_resp_atend(atp.nr_atendimento, 'N') AS medico_responsavel
                FROM tasy.atendimento_paciente atp
                JOIN tasy.unidade_atendimento ua ON ua.nr_atendimento = atp.nr_atendimento
                LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                WHERE atp.nr_atendimento = :attendance
            ", ['attendance' => $attendanceNumber]);
            
            return !empty($result) ? $result[0] : null;
        });
    }
}
