<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\System\SystemConfiguration;


class Sector extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    protected $primaryKey = 'cd_setor_atendimento';
    protected $table = 'tasy.setor_atendimento';

    
    /**
     * Get all allowed sectors with hospital information - CORRECTED
     */
    public function getAllowedSectors()
    {
        $cacheKey = "allowed_sectors_list";
        $allowedSectorIds = SystemConfiguration::allowedSectorCodes();

        if (empty($allowedSectorIds)) {
            return collect([]);
        }

        $placeholders = str_repeat('?,', count($allowedSectorIds) - 1) . '?';

        return Cache::remember($cacheKey, 1800, function() use ($allowedSectorIds, $placeholders) {
            $hospital = new Hospital();
            $results = DB::connection('tasy')->select("
                SELECT 
                    sa.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    sa.ds_prescricao as sector_short_name,
                    sa.nr_seq_agrupamento as hospital_id,
                    sa.ie_situacao as sector_status,
                    COUNT(ua.cd_unidade_basica) as total_beds,
                    COUNT(CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 END) as occupied_beds,
                    COUNT(CASE WHEN atp.nr_atendimento IS NULL THEN 1 END) as empty_beds
                FROM tasy.setor_atendimento sa
                LEFT JOIN tasy.unidade_atendimento ua ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
                    AND ua.ie_situacao = 'A'
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                    AND atp.dt_alta IS NULL
                WHERE sa.cd_setor_atendimento IN ({$placeholders})
                AND sa.ie_situacao = 'A'
                GROUP BY sa.cd_setor_atendimento, sa.ds_setor_atendimento, sa.ds_prescricao, 
                        sa.nr_seq_agrupamento, sa.ie_situacao
                ORDER BY sa.nr_seq_agrupamento, sa.ds_setor_atendimento
            ", $allowedSectorIds);

            return collect($results)->map(function($record) use ($hospital) {
                $hospitalInfo = $hospital->getHospitalBySector($record->cd_setor_atendimento);
                $totalBeds = intval($record->total_beds);
                $occupiedBeds = intval($record->occupied_beds);
                $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 2) : 0;

                return (object) [
                    'cd_setor_atendimento' => $record->cd_setor_atendimento,
                    'ds_setor_atendimento' => $record->ds_setor_atendimento,
                    'sector_short_name' => $record->sector_short_name,
                    'hospital_id' => $record->hospital_id,
                    'hospital_name' => $hospitalInfo ? $hospitalInfo->hospital_name : 'Hospital não identificado',
                    'sector_status' => $record->sector_status,
                    'total_beds' => $totalBeds,
                    'occupied_beds' => $occupiedBeds,
                    'empty_beds' => intval($record->empty_beds),
                    'occupancy_rate' => $occupancyRate
                ];
            });
        });
    }
    
    /**
     * Get sector details by ID
     */
    public function getSectorDetails($sectorId)
    {
        $cacheKey = "sector_details_{$sectorId}";
        
        return Cache::remember($cacheKey, 1800, function() use ($sectorId) {
            $result = DB::connection('tasy')->select("
                SELECT 
                    sa.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    sa.ds_prescricao as sector_short_name,
                    sa.nr_seq_agrupamento as hospital_id,
                    ags.ds_agrupamento as hospital_name,
                    sa.ie_situacao as sector_status,
                    sa.dt_cadastro as created_date,
                    sa.dt_inativacao as inactivation_date
                FROM tasy.setor_atendimento sa
                LEFT JOIN tasy.agrupamento_setor ags ON sa.nr_seq_agrupamento = ags.nr_sequencia
                WHERE sa.cd_setor_atendimento = :sector_id
                  AND sa.ie_situacao = 'A'
            ", ['sector_id' => $sectorId]);
            
            return !empty($result) ? $result[0] : null;
        });
    }
    
    /**
     * Get sectors by hospital 
     */
    public function getSectorsByHospital($hospitalId)
    {
        $cacheKey = "sectors_by_hospital_{$hospitalId}";
        
        return Cache::remember($cacheKey, 1800, function() use ($hospitalId) {
            $results = DB::connection('tasy')->select("
                SELECT 
                    sa.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    sa.ds_prescricao as sector_short_name,
                    sa.ie_situacao as sector_status,
                    COUNT(ua.cd_unidade_basica) as total_beds
                FROM tasy.setor_atendimento sa
                LEFT JOIN tasy.unidade_atendimento ua ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
                    AND ua.ie_situacao = 'A'
                WHERE sa.nr_seq_agrupamento = :hospital_id
                  AND sa.ie_situacao = 'A'
                GROUP BY sa.cd_setor_atendimento, sa.ds_setor_atendimento, sa.ds_prescricao, sa.ie_situacao
                ORDER BY sa.ds_setor_atendimento
            ", ['hospital_id' => $hospitalId]);
            
            return collect($results);
        });
    }
    
    /**
     * Check if sector is allowed
     */
    public function isAllowedSector($sectorId)
    {
        return in_array($sectorId, $this->allowedSectors);
    }
    
    /**
     * Get allowed sector IDs
     */
    public function getAllowedSectorIds()
    {
        return $this->allowedSectors;
    }
    
    /**
     * Get sector statistics with patient data - CORRECTED ORDER BY
     */
    public function getSectorStatistics($sectorId)
    {
        $cacheKey = "sector_stats_{$sectorId}";
        
        return Cache::remember($cacheKey, 600, function() use ($sectorId) {
            $result = DB::connection('tasy')->select("
                SELECT 
                    sa.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    ags.ds_agrupamento as hospital_name,
                    
                    -- Bed statistics using unidade_atendimento
                    COUNT(ua.cd_unidade_basica) as total_beds,
                    COUNT(CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 END) as occupied_beds,
                    COUNT(CASE WHEN atp.nr_atendimento IS NULL THEN 1 END) as empty_beds,
                    
                    -- Patient statistics
                    COUNT(CASE WHEN atp.nr_atendimento IS NOT NULL AND TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) = 0 THEN 1 END) as new_patients_today,
                    
                    -- MEWS statistics
                    COUNT(CASE WHEN mews.qt_pontuacao >= 5 THEN 1 END) as critical_patients,
                    COUNT(CASE WHEN mews.qt_pontuacao >= 3 AND mews.qt_pontuacao < 5 THEN 1 END) as warning_patients,
                    COUNT(CASE WHEN mews.qt_pontuacao < 3 THEN 1 END) as normal_patients,
                    COUNT(CASE WHEN atp.nr_atendimento IS NOT NULL AND mews.qt_pontuacao IS NULL THEN 1 END) as no_mews_patients
                    
                FROM tasy.setor_atendimento sa
                LEFT JOIN tasy.agrupamento_setor ags ON sa.nr_seq_agrupamento = ags.nr_sequencia
                JOIN tasy.unidade_atendimento ua ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
                    AND ua.ie_situacao = 'A'
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                    AND atp.dt_alta IS NULL
                LEFT JOIN (
                    SELECT 
                        nr_atendimento,
                        qt_pontuacao,
                        ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC) as rn
                    FROM tasy.escala_mews 
                    WHERE dt_inativacao IS NULL AND dt_liberacao IS NOT NULL
                ) mews ON atp.nr_atendimento = mews.nr_atendimento AND mews.rn = 1
                WHERE sa.cd_setor_atendimento = :sector_id
                  AND sa.ie_situacao = 'A'
                GROUP BY sa.cd_setor_atendimento, sa.ds_setor_atendimento, ags.ds_agrupamento
            ", ['sector_id' => $sectorId]);
            
            if (!empty($result)) {
                $data = $result[0];
                $totalBeds = intval($data->total_beds);
                $occupiedBeds = intval($data->occupied_beds);
                
                return (object) [
                    'cd_setor_atendimento' => $data->cd_setor_atendimento,
                    'ds_setor_atendimento' => $data->ds_setor_atendimento,
                    'hospital_name' => $data->hospital_name,
                    'total_beds' => $totalBeds,
                    'occupied_beds' => $occupiedBeds,
                    'empty_beds' => intval($data->empty_beds),
                    'occupancy_rate' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 2) : 0,
                    'new_patients_today' => intval($data->new_patients_today),
                    'critical_patients' => intval($data->critical_patients),
                    'warning_patients' => intval($data->warning_patients),
                    'normal_patients' => intval($data->normal_patients),
                    'no_mews_patients' => intval($data->no_mews_patients)
                ];
            }
            
            return null;
        });
    }
    
    /**
     * Get the ID of the sector
     */
    public function getIdAttribute()
    {
        return $this->cd_setor_atendimento;
    }
}
