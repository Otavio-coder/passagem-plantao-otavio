<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Hospital extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    /**
     * Get hospital information by sector ID
     */
    public function getHospitalBySector($sectorId)
    {
        $cacheKey = "hospital_by_sector_{$sectorId}";
        
        return Cache::remember($cacheKey, 1800, function() use ($sectorId) {
            $result = DB::connection('tasy')->select("
                SELECT 
                    ags.nr_sequencia as hospital_id,
                    ags.ds_agrupamento as hospital_name,
                    ags.ie_situacao as hospital_status,
                    sa.cd_setor_atendimento,
                    sa.ds_setor_atendimento
                FROM tasy.setor_atendimento sa
                LEFT JOIN tasy.agrupamento_setor ags ON sa.nr_seq_agrupamento = ags.nr_sequencia
                WHERE sa.cd_setor_atendimento = :sector_id
                  AND sa.ie_situacao = 'A'
            ", ['sector_id' => $sectorId]);
            
            return !empty($result) ? $result[0] : null;
        });
    }
    
    /**
     * Get all active hospitals with their sectors
     */
    public function getAllHospitalsWithSectors()
    {
        $cacheKey = "hospitals_with_sectors";
        
        return Cache::remember($cacheKey, 3600, function() {
            $results = DB::connection('tasy')->select("
                SELECT 
                    ags.nr_sequencia as hospital_id,
                    ags.ds_agrupamento as hospital_name,
                    ags.ie_situacao as hospital_status,
                    COUNT(sa.cd_setor_atendimento) as total_sectors,
                    LISTAGG(sa.cd_setor_atendimento, ', ') WITHIN GROUP (ORDER BY sa.ds_setor_atendimento) as sector_ids
                FROM tasy.agrupamento_setor ags
                LEFT JOIN tasy.setor_atendimento sa ON ags.nr_sequencia = sa.nr_seq_agrupamento
                    AND sa.ie_situacao = 'A'
                WHERE ags.ie_situacao = 'A'
                GROUP BY ags.nr_sequencia, ags.ds_agrupamento, ags.ie_situacao
                ORDER BY ags.ds_agrupamento
            ");
            
            return collect($results)->map(function($record) {
                return (object) [
                    'hospital_id' => $record->hospital_id,
                    'hospital_name' => $record->hospital_name,
                    'hospital_status' => $record->hospital_status,
                    'total_sectors' => intval($record->total_sectors),
                    'sector_ids' => $record->sector_ids ? explode(', ', $record->sector_ids) : []
                ];
            });
        });
    }
    
    /**
     * Get hospital name by sector (simplified method)
     */
    public function getHospitalName($sectorId)
    {
        $hospital = $this->getHospitalBySector($sectorId);
        return $hospital ? $hospital->hospital_name : 'Hospital não identificado';
    }
    
    /**
     * Get hospital statistics - CORRECTED ORDER BY
     */
    public function getHospitalStatistics($hospitalId = null)
    {
        $cacheKey = "hospital_stats_" . ($hospitalId ?? 'all');
        
        return Cache::remember($cacheKey, 900, function() use ($hospitalId) {
            $whereClause = $hospitalId ? "WHERE ags.nr_sequencia = :hospital_id" : "";
            $params = $hospitalId ? ['hospital_id' => $hospitalId] : [];
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    ags.nr_sequencia as hospital_id,
                    ags.ds_agrupamento as hospital_name,
                    COUNT(DISTINCT sa.cd_setor_atendimento) as total_sectors,
                    COUNT(DISTINCT ua.cd_unidade_basica) as total_beds,
                    COUNT(DISTINCT CASE WHEN atp.nr_atendimento IS NOT NULL THEN ua.cd_unidade_basica END) as occupied_beds,
                    COUNT(DISTINCT CASE WHEN atp.nr_atendimento IS NULL THEN ua.cd_unidade_basica END) as empty_beds
                FROM tasy.agrupamento_setor ags
                JOIN tasy.setor_atendimento sa ON ags.nr_sequencia = sa.nr_seq_agrupamento
                    AND sa.ie_situacao = 'A'
                JOIN tasy.unidade_atendimento ua ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
                    AND ua.ie_situacao = 'A'
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                    AND atp.dt_alta IS NULL
                {$whereClause}
                GROUP BY ags.nr_sequencia, ags.ds_agrupamento
                ORDER BY ags.ds_agrupamento ASC
            ", $params);
            
            return collect($results)->map(function($record) {
                $totalBeds = intval($record->total_beds);
                $occupiedBeds = intval($record->occupied_beds);
                $emptyBeds = intval($record->empty_beds);
                $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 2) : 0;
                
                return (object) [
                    'hospital_id' => $record->hospital_id,
                    'hospital_name' => $record->hospital_name,
                    'total_sectors' => intval($record->total_sectors),
                    'total_beds' => $totalBeds,
                    'occupied_beds' => $occupiedBeds,
                    'empty_beds' => $emptyBeds,
                    'occupancy_rate' => $occupancyRate
                ];
            });
        });
    }
}
