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
    public function getPatientData($attendanceNumber = null, $sectorId = null, $filters = [])
    {
        if ($attendanceNumber) {
            // Consulta detalhada individual (mantém como está)
            $result = DB::connection('tasy')->select("
                SELECT
                    ua.cd_unidade_basica,
                    ua.cd_setor_atendimento,
                    atp.cd_pessoa_fisica,
                    atp.nr_atendimento,
                    tasy.obter_nome_paciente(atp.nr_atendimento) AS nm_pessoa_fisica,
                    pf.nr_prontuario,
                    pf.dt_nascimento AS birth_date,
                    FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS idade_anos,
                    MOD(FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)), 12) AS idade_meses,
                    TRUNC(SYSDATE - ADD_MONTHS(pf.dt_nascimento, FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)))) AS idade_dias,
                    pf.ie_sexo AS sexo,
                    tasy.obter_desc_convenio(tasy.obter_convenio_atendimento(atp.nr_atendimento)) AS convenio,
                    tasy.obter_medico_resp_atend(atp.nr_atendimento, 'N') AS medico_responsavel,
                    atp.dt_entrada,
                    TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS internment_days
                FROM tasy.atendimento_paciente atp
                JOIN tasy.unidade_atendimento ua ON ua.nr_atendimento = atp.nr_atendimento
                LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                WHERE atp.nr_atendimento = :attendanceNumber
            ", ['attendanceNumber' => $attendanceNumber]);
            if (!empty($result)) {
                $row = $result[0];
                return (object) [
                    'cd_unidade_basica'      => $row->cd_unidade_basica,
                    'cd_setor_atendimento'   => $row->cd_setor_atendimento,
                    'cd_pessoa_fisica'       => $row->cd_pessoa_fisica,
                    'nr_atendimento'         => $row->nr_atendimento,
                    'nm_pessoa_fisica'       => $row->nm_pessoa_fisica,
                    'nr_prontuario'          => $row->nr_prontuario,
                    'birth_date'             => $row->birth_date ? \Carbon\Carbon::parse($row->birth_date)->format('d/m/Y') : '',
                    'age'                    => $row->idade_anos,
                    'age_detailed'           => $this->formatDetailedAge($row->idade_anos, $row->idade_meses, $row->idade_dias),
                    'sexo'                   => $row->sexo,
                    'convenio'               => $row->convenio,
                    'medico_responsavel'     => $row->medico_responsavel,
                    'dt_entrada'             => $row->dt_entrada,
                    'internment_days'        => $row->internment_days,
                    'has_patient'            => true,
                ];
            }
            return null;
        }

        // 1. Busca todos os leitos do setor (dados mínimos do leito e ocupação)
        $bedUnit = new BedUnit();
        $beds = $bedUnit->getBedsBySector($sectorId);

        // 2. Coleta todos os atendimentos ocupados (apenas 1x, sem duplicação)
        $occupiedAttendances = $beds->filter(fn($b) => $b->is_occupied && $b->nr_atendimento)
            ->pluck('nr_atendimento')->unique()->values()->all();

        // 3. Busca todos os dados básicos dos pacientes ocupados em lote (apenas 1 query)
        $details = [];
        if (!empty($occupiedAttendances)) {
            $placeholders = implode(',', array_fill(0, count($occupiedAttendances), '?'));
            $results = DB::connection('tasy')->select("
                SELECT
                    atp.nr_atendimento,
                    tasy.obter_nome_paciente(atp.nr_atendimento) AS nm_pessoa_fisica,
                    pf.nr_prontuario,
                    pf.dt_nascimento AS birth_date,
                    FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS idade_anos,
                    MOD(FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)), 12) AS idade_meses,
                    TRUNC(SYSDATE - ADD_MONTHS(pf.dt_nascimento, FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)))) AS idade_dias,
                    pf.ie_sexo AS sexo,
                    tasy.obter_desc_convenio(tasy.obter_convenio_atendimento(atp.nr_atendimento)) AS convenio,
                    tasy.obter_medico_resp_atend(atp.nr_atendimento, 'N') AS medico_responsavel
                FROM tasy.atendimento_paciente atp
                LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                WHERE atp.nr_atendimento IN ($placeholders)
            ", $occupiedAttendances);

            foreach ($results as $row) {
                $details[$row->nr_atendimento] = [
                    'nm_pessoa_fisica'   => $row->nm_pessoa_fisica,
                    'nr_prontuario'      => $row->nr_prontuario,
                    'birth_date'         => $row->birth_date ? \Carbon\Carbon::parse($row->birth_date)->format('d/m/Y') : '',
                    'age'                => $row->idade_anos,
                    'age_detailed'       => $this->formatDetailedAge($row->idade_anos, $row->idade_meses, $row->idade_dias),
                    'sexo'               => $row->sexo,
                    'convenio'           => $row->convenio,
                    'medico_responsavel' => $row->medico_responsavel,
                ];
            }
        }

        // 4. Monta o retorno: cada leito recebe os dados básicos do paciente se ocupado (merge em memória, sem loops de consulta)
        return $beds->map(function($bed) use ($details) {
            $patientDetails = ($bed->is_occupied && isset($details[$bed->nr_atendimento])) ? $details[$bed->nr_atendimento] : [
                'nm_pessoa_fisica'   => null,
                'nr_prontuario'      => null,
                'birth_date'         => null,
                'age'                => null,
                'age_detailed'       => null,
                'sexo'               => null,
                'convenio'           => null,
                'medico_responsavel' => null,
            ];
            return (object) array_merge([
                'cd_unidade_basica'      => $bed->cd_unidade_basica,
                'bed_name'               => $bed->bed_name ?? null,
                'bed_sequence'           => $bed->bed_sequence ?? null,
                'display_order'          => $bed->display_order ?? null,
                'bed_status'             => $bed->bed_status ?? null,
                'cd_setor_atendimento'   => $bed->cd_setor_atendimento,
                'ds_setor_atendimento'   => $bed->ds_setor_atendimento,
                'current_attendance'     => $bed->current_attendance,
                'nr_atendimento'         => $bed->nr_atendimento ?? $bed->current_attendance ?? null,
                'is_occupied'            => $bed->is_occupied,
                'cd_pessoa_fisica'       => $bed->cd_pessoa_fisica ?? null,
                'patient_name'           => $bed->patient_name ?? null,
                'dt_entrada'             => $bed->dt_entrada ?? null,
                'internment_days'        => $bed->internment_days ?? null,
                'has_patient'            => $bed->is_occupied,
            ], $patientDetails);
        });
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

    public function getFullPatientData($attendanceNumber)
    {
        $basic = $this->getPatientData($attendanceNumber);
        if (!$basic) return null;

        $scales   = (new \App\Repositories\EMR\PatientScales())->getPatientScales($attendanceNumber);
        $clinical = (new \App\Repositories\EMR\PatientClinical())->getPatientClinicalDetails($attendanceNumber);
        $cpoeRepo = new \App\Repositories\EMR\PatientCPOE();
        $cpoe     = $cpoeRepo->getPatientCpoeProcedures($attendanceNumber);
        $medications = $cpoeRepo->getPatientMedications($attendanceNumber);
        $nutrition = $cpoeRepo->getPatientNutrition($attendanceNumber);

        // Retorna objeto único, já pronto para o SBAR
        return (object) array_merge(
            (array) $basic,
            (array) $scales,
            (array) $clinical,
            [
                'cpoe_procedures' => $cpoe,
                'cpoe_medications' => $medications,
                'cpoe_nutrition' => $nutrition
            ]
        );
    }

    public function getAllPatientsInSector($sectorId, $filters = [])
    {
        $beds = $this->getPatientData(null, $sectorId, $filters);

        // Coleta todos os nr_atendimento ocupados
        $attendanceNumbers = $beds->filter(fn($b) => $b->has_patient && $b->nr_atendimento)
            ->pluck('nr_atendimento')->unique()->values()->all();

        // Busca dados extras em lote
        $scalesRepo   = new \App\Repositories\EMR\PatientScales();
        $clinicalRepo = new \App\Repositories\EMR\PatientClinical();
        $cpoeRepo     = new \App\Repositories\EMR\PatientCPOE();

        $mewsData     = $scalesRepo->getMewsDataForPatients($attendanceNumbers);
        $alertsData   = $clinicalRepo->getClinicalAlertsForPatients($attendanceNumbers);
        $surgeryData  = $clinicalRepo->getSurgicalProceduresForPatients($attendanceNumbers);
        $cpoePending  = $cpoeRepo->getUnifiedCpoePendingForPatients($attendanceNumbers);
        $medicationSummary = $cpoeRepo->getMedicationSummaryForPatients($attendanceNumbers);
        $nutritionSummary = $cpoeRepo->getNutritionSummaryForPatients($attendanceNumbers);

        // Enriquecer cada paciente/leito
        return $beds->map(function($bed) use ($mewsData, $alertsData, $surgeryData, $cpoePending, $medicationSummary, $nutritionSummary) {
            if ($bed->has_patient && $bed->nr_atendimento) {
                $nr = $bed->nr_atendimento;
                // MEWS
                $bed->mews_score = $mewsData[$nr]['mews_score'] ?? null;
                $bed->mews_display = $mewsData[$nr]['mews_display'] ?? null;
                // Alertas
                $bed->has_allergy = $alertsData[$nr]['has_allergy'] ?? false;
                $bed->has_isolation = $alertsData[$nr]['has_isolation'] ?? false;
                // Cirurgia
                $bed->has_surgery = $surgeryData[$nr]['has_surgery'] ?? false;
                $bed->surgical_procedures = $surgeryData[$nr]['procedures'] ?? [];
                // CPOE pendente (unified count)
                $bed->has_cpoe_pending = $cpoePending[$nr]['has_cpoe_pending'] ?? false;
                $bed->cpoe_pending_count = $cpoePending[$nr]['cpoe_pending_count'] ?? 0;
                $bed->pending_procedures = $cpoePending[$nr]['pending_procedures'] ?? 0;
                $bed->pending_medications = $cpoePending[$nr]['pending_medications'] ?? 0;
                $bed->pending_nutrition = $cpoePending[$nr]['pending_nutrition'] ?? 0;
                // Medicamentos
                $bed->has_medications = $medicationSummary[$nr]['has_medications'] ?? false;
                $bed->total_medications = $medicationSummary[$nr]['total_medications'] ?? 0;
                $bed->pending_administrations = $medicationSummary[$nr]['pending_administrations'] ?? 0;
                // Nutrição
                $bed->has_nutrition = $nutritionSummary[$nr]['has_nutrition'] ?? false;
                $bed->total_nutrition_prescriptions = $nutritionSummary[$nr]['total_nutrition_prescriptions'] ?? 0;
                $bed->active_nutrition_prescriptions = $nutritionSummary[$nr]['active_prescriptions'] ?? 0;
            } else {
                // Leito vazio: zera campos
                $bed->mews_score = null;
                $bed->mews_display = null;
                $bed->has_allergy = false;
                $bed->has_isolation = false;
                $bed->has_surgery = false;
                $bed->surgical_procedures = [];
                $bed->has_cpoe_pending = false;
                $bed->cpoe_pending_count = 0;
                $bed->pending_procedures = 0;
                $bed->pending_medications = 0;
                $bed->pending_nutrition = 0;
                $bed->has_medications = false;
                $bed->total_medications = 0;
                $bed->pending_administrations = 0;
                $bed->has_nutrition = false;
                $bed->total_nutrition_prescriptions = 0;
                $bed->active_nutrition_prescriptions = 0;
            }
            return $bed;
        });
    }
}
