<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\Cache;
use App\Models\EMR\Patient;
use App\Repositories\EMR\PatientScales;
use App\Repositories\EMR\PatientClinical;
use App\Repositories\EMR\PatientCPOE;
use App\Models\EMR\Hospital;
use App\Models\EMR\Sector;
use App\Models\EMR\BedUnit;

// Modelo principal para relatórios SBAR
class SbarReport 
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    // Dependências injetadas (modelos auxiliares)
    protected $patient;
    protected $patientScales;
    protected $patientClinical;
    protected $patientCPOE;
    protected $hospital;
    protected $sector;
    protected $bedUnit;
    
    // Construtor: inicializa os modelos auxiliares
    public function __construct()
    {
        $this->patient = new Patient();
        $this->patientScales = new PatientScales();
        $this->patientClinical = new PatientClinical();
        $this->patientCPOE = new PatientCPOE();
        $this->hospital = new Hospital();
        $this->sector = new Sector();
        $this->bedUnit = new BedUnit();
    }
    
    /**
     * Busca dados completos dos pacientes de forma modular e otimizada
     */
    public function getBasePatientData($sectorId = null, $filters = [])
    {
        // Busca dados básicos dos pacientes/leitos
        $patients = $this->patient->getBasicPatientData($sectorId, $filters);
        
        if ($patients->isEmpty()) {
            return collect([]);
        }
        
        // Extrai números de atendimento dos pacientes
        $attendanceNumbers = $patients->where('has_patient', true)->pluck('nr_atendimento')->filter()->toArray();
        
        // Carrega dados adicionais usando modelos especializados
        $mewsData = !empty($attendanceNumbers) ? $this->patientScales->getMewsDataForPatients($attendanceNumbers) : [];
        $clinicalAlerts = !empty($attendanceNumbers) ? $this->patientClinical->getClinicalAlertsForPatients($attendanceNumbers) : [];
        $cpoeData = !empty($attendanceNumbers) ? $this->patientCPOE->getCpoePendingForPatients($attendanceNumbers) : [];
        $surgicalData = !empty($attendanceNumbers) ? $this->patientClinical->getSurgicalProceduresForPatients($attendanceNumbers) : [];
        
        // Mescla todos os dados nos registros dos pacientes/leitos
        $result = $patients->map(function($record) use ($mewsData, $clinicalAlerts, $cpoeData, $surgicalData) {
            if ($record->has_patient && $record->nr_atendimento) {
                // Adiciona dados MEWS
                $mews = $mewsData[$record->nr_atendimento] ?? ['mews_score' => null, 'mews_date' => null, 'mews_alert' => null];
                $record->mews_score = $mews['mews_score'];
                $record->mews_date = $mews['mews_date'];
                $record->mews_alert = $mews['mews_alert'];
                $record->mews_display = $mews['mews_display'];
                
                // Adiciona alertas clínicos
                $alerts = $clinicalAlerts[$record->nr_atendimento] ?? ['has_allergy' => false, 'has_isolation' => false];
                $record->has_allergy = $alerts['has_allergy'];
                $record->has_isolation = $alerts['has_isolation'];
                
                // Adiciona dados de pendências CPOE
                $cpoe = $cpoeData[$record->nr_atendimento] ?? ['has_cpoe_pending' => false, 'cpoe_pending_count' => 0];
                $record->has_cpoe_pending = $cpoe['has_cpoe_pending'];
                $record->cpoe_pending_count = $cpoe['cpoe_pending_count'];
                
                // Adiciona dados cirúrgicos
                $surgery = $surgicalData[$record->nr_atendimento] ?? ['has_surgery' => false, 'procedures' => []];
                $record->has_surgery = $surgery['has_surgery'];
                $record->surgical_procedures = $surgery['procedures'];
            } else {
                // Valores padrão para leitos vazios
                $record->mews_score = null;
                $record->mews_date = null;
                $record->has_allergy = false;
                $record->has_isolation = false;
                $record->has_cpoe_pending = false;
                $record->cpoe_pending_count = 0;
                $record->has_surgery = false;
                $record->surgical_procedures = [];
            }
            
            return $record;
        });
        
        // Aplica filtro MEWS se necessário
        if (isset($filters['mews_filter']) && $filters['mews_filter'] !== 'all') {
            $result = $this->patientScales->applyMewsFilter($result, $filters['mews_filter']);
        }
        
        // Aplica filtro cirúrgico
        if (isset($filters['surgical_filter']) && $filters['surgical_filter'] === 'with_surgery') {
            $result = $result->filter(function($patient) {
                return $patient->has_patient && $patient->has_surgery;
            });
        }
        
        // Aplica ordenação
        $result = $this->applySorting($result, $filters);
        
        return $result;
    }

    /**
     * Aplica ordenação nos dados dos pacientes/leitos
     */
    private function applySorting($patients, $filters)
    {
        $orderBy = $filters['order_by'] ?? 'leito';
        $direction = $filters['order_direction'] ?? 'asc';
        
        $sortedPatients = $patients->sort(function($a, $b) use ($orderBy, $direction) {
            // Sempre prioriza pacientes sobre leitos vazios
            if ($a->has_patient && !$b->has_patient) return -1;
            if (!$a->has_patient && $b->has_patient) return 1;
            
            switch ($orderBy) {
                case 'mews':
                    $result = ($b->mews_score ?? 0) <=> ($a->mews_score ?? 0);
                    return $direction === 'desc' ? $result : -$result;
                case 'name':
                    $result = strcasecmp($a->nm_pessoa_fisica ?? '', $b->nm_pessoa_fisica ?? '');
                    return $direction === 'desc' ? -$result : $result;
                case 'prontuario':
                    $result = ($a->nr_prontuario ?? '') <=> ($b->nr_prontuario ?? '');
                    return $direction === 'desc' ? -$result : $result;
                case 'internment':
                    $result = ($a->internment_days ?? 0) <=> ($b->internment_days ?? 0);
                    return $direction === 'desc' ? -$result : $result;
                case 'age':
                    $result = ($a->age ?? 0) <=> ($b->age ?? 0);
                    return $direction === 'desc' ? -$result : $result;
                default: // leito
                    $result = ($a->cd_unidade_basica ?? '') <=> ($b->cd_unidade_basica ?? '');
                    return $direction === 'desc' ? -$result : $result;
            }
        });
        
        return $sortedPatients->values();
    }

    /**
     * Busca detalhes completos do paciente (com cache)
     */
    public function getPatientDetails($attendanceNumber)
    {
        $cacheKey = "patient_complete_details_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            // Dados básicos
            $basicDetails = $this->patient->getPatientBasicDetails($attendanceNumber);
            if (!$basicDetails) {
                return null;
            }
            
            // Escalas do paciente
            $scales = $this->patientScales->getPatientScales($attendanceNumber);
            
            // Dados clínicos
            $clinical = $this->patientClinical->getPatientClinicalDetails($attendanceNumber);
            
            // Procedimentos CPOE
            $cpoeData = $this->patientCPOE->getPatientCpoeProcedures($attendanceNumber);
            
            // Mescla todos os dados
            $result = (object) array_merge(
                (array) $basicDetails,
                (array) $scales,
                (array) $clinical
            );
            
            // Adiciona procedimentos CPOE
            $result->cpoe_procedures = $cpoeData;
            
            return $result;
        });
    }

    /**
     * Busca procedimentos CPOE do paciente
     */
    public function getPatientCpoeProcedures($attendanceNumber)
    {
        return $this->patientCPOE->getPatientCpoeProcedures($attendanceNumber);
    }

    /**
     * Busca nome do hospital via modelo Hospital
     */
    public function getHospitalName($sectorId)
    {
        return $this->hospital->getHospitalName($sectorId);
    }
    
    /**
     * Busca detalhes do setor
     */
    public function getSectorDetails($sectorId)
    {
        return $this->sector->getSectorDetails($sectorId);
    }
    
    /**
     * Busca estatísticas do setor
     */
    public function getSectorStatistics($sectorId)
    {
        return $this->sector->getSectorStatistics($sectorId);
    }
    
    /**
     * Busca estatísticas do hospital
     */
    public function getHospitalStatistics($hospitalId = null)
    {
        return $this->hospital->getHospitalStatistics($hospitalId);
    }
    
    /**
     * Busca todos hospitais com seus setores
     */
    public function getHospitalsWithSectors()
    {
        return $this->hospital->getAllHospitalsWithSectors();
    }
    
    /**
     * Busca leitos por setor
     */
    public function getBedsBySector($sectorId)
    {
        return $this->bedUnit->getBedsBySector($sectorId);
    }

    // Métodos legados para compatibilidade - delegam para novos modelos
    public function getCpoeProceduresData($sectorId = null, $attendanceNumber = null)
    {
        return $this->patientCPOE->getCpoeProceduresData($sectorId, $attendanceNumber);
    }

    public function getExamPrioritiesData($attendanceNumber)
    {
        return $this->patientClinical->getPatientClinicalDetails($attendanceNumber);
    }
}