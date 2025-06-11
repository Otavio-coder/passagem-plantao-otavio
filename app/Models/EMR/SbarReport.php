<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SbarReport extends Model
{
    use HasFactory;

    protected $connection = 'tasy';
    public $timestamps = false;
    
    // Inject dependencies
    protected $patient;
    protected $patientScales;
    protected $patientClinical;
    protected $patientCPOE;
    protected $hospital;
    protected $sector;
    protected $bedUnit;
    
    public function __construct()
    {
        parent::__construct();
        $this->patient = new Patient();
        $this->patientScales = new PatientScales();
        $this->patientClinical = new PatientClinical();
        $this->patientCPOE = new PatientCPOE();
        $this->hospital = new Hospital();
        $this->sector = new Sector();
        $this->bedUnit = new BedUnit();
    }
    
    /**
     * Get complete patient data using modular approach - OPTIMIZED
     */
    public function getBasePatientData($sectorId = null, $filters = [])
    {
        // Get basic patient/bed data from Patient model
        $patients = $this->patient->getBasicPatientData($sectorId, $filters);
        
        if ($patients->isEmpty()) {
            return collect([]);
        }
        
        // Get attendance numbers for patients only
        $attendanceNumbers = $patients->where('has_patient', true)->pluck('nr_atendimento')->filter()->toArray();
        
        // Load additional data using specialized models
        $mewsData = !empty($attendanceNumbers) ? $this->patientScales->getMewsDataForPatients($attendanceNumbers) : [];
        $clinicalAlerts = !empty($attendanceNumbers) ? $this->patientClinical->getClinicalAlertsForPatients($attendanceNumbers) : [];
        $cpoeData = !empty($attendanceNumbers) ? $this->patientCPOE->getCpoePendingForPatients($attendanceNumbers) : [];
        
        // Merge all data efficiently
        $result = $patients->map(function($record) use ($mewsData, $clinicalAlerts, $cpoeData) {
            if ($record->has_patient && $record->nr_atendimento) {
                // Add MEWS data
                $mews = $mewsData[$record->nr_atendimento] ?? ['mews_score' => null, 'mews_date' => null];
                $record->mews_score = $mews['mews_score'];
                $record->mews_date = $mews['mews_date'];
                
                // Add clinical alerts
                $alerts = $clinicalAlerts[$record->nr_atendimento] ?? ['has_allergy' => false, 'has_isolation' => false];
                $record->has_allergy = $alerts['has_allergy'];
                $record->has_isolation = $alerts['has_isolation'];
                
                // Add CPOE data
                $cpoe = $cpoeData[$record->nr_atendimento] ?? ['has_cpoe_pending' => false, 'cpoe_pending_count' => 0];
                $record->has_cpoe_pending = $cpoe['has_cpoe_pending'];
                $record->cpoe_pending_count = $cpoe['cpoe_pending_count'];
            } else {
                // Empty bed defaults
                $record->mews_score = null;
                $record->mews_date = null;
                $record->has_allergy = false;
                $record->has_isolation = false;
                $record->has_cpoe_pending = false;
                $record->cpoe_pending_count = 0;
            }
            
            return $record;
        });
        
        // Apply filters using specialized models
        if (isset($filters['mews_filter']) && $filters['mews_filter'] !== 'all') {
            $result = $this->patientScales->applyMewsFilter($result, $filters['mews_filter']);
        }
        
        // Apply sorting
        $result = $this->applySorting($result, $filters);
        
        return $result;
    }

    /**
     * Apply sorting to patient data
     */
    private function applySorting($patients, $filters)
    {
        $orderBy = $filters['order_by'] ?? 'leito';
        $direction = $filters['order_direction'] ?? 'asc';
        
        $sortedPatients = $patients->sort(function($a, $b) use ($orderBy, $direction) {
            // Always put patients before empty beds
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
     * Get complete patient details using modular approach
     */
    public function getPatientDetails($attendanceNumber)
    {
        $cacheKey = "patient_complete_details_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            // Get basic details
            $basicDetails = $this->patient->getPatientBasicDetails($attendanceNumber);
            if (!$basicDetails) {
                return null;
            }
            
            // Get scales
            $scales = $this->patientScales->getPatientScales($attendanceNumber);
            
            // Get clinical details
            $clinical = $this->patientClinical->getPatientClinicalDetails($attendanceNumber);
            
            // Merge all data
            return (object) array_merge(
                (array) $basicDetails,
                (array) $scales,
                (array) $clinical
            );
        });
    }

    /**
     * Get CPOE procedures for patient
     */
    public function getPatientCpoeProcedures($attendanceNumber)
    {
        return $this->patientCPOE->getPatientCpoeProcedures($attendanceNumber);
    }

    /**
     * Get hospital name using Hospital model
     */
    public function getHospitalName($sectorId)
    {
        return $this->hospital->getHospitalName($sectorId);
    }
    
    /**
     * Get sector details
     */
    public function getSectorDetails($sectorId)
    {
        return $this->sector->getSectorDetails($sectorId);
    }
    
    /**
     * Get sector statistics
     */
    public function getSectorStatistics($sectorId)
    {
        return $this->sector->getSectorStatistics($sectorId);
    }
    
    /**
     * Get hospital statistics
     */
    public function getHospitalStatistics($hospitalId = null)
    {
        return $this->hospital->getHospitalStatistics($hospitalId);
    }
    
    /**
     * Get all hospitals with sectors
     */
    public function getHospitalsWithSectors()
    {
        return $this->hospital->getAllHospitalsWithSectors();
    }
    
    /**
     * Get beds by sector
     */
    public function getBedsBySector($sectorId)
    {
        return $this->bedUnit->getBedsBySector($sectorId);
    }

    // Legacy methods for compatibility - delegate to new models
    public function getCpoeProceduresData($sectorId = null, $attendanceNumber = null)
    {
        return $this->patientCPOE->getCpoeProceduresData($sectorId, $attendanceNumber);
    }

    public function getExamPrioritiesData($attendanceNumber)
    {
        return $this->patientClinical->getPatientClinicalDetails($attendanceNumber);
    }
}