<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BedUnit extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    protected $primaryKey = 'cd_unidade_basica';
    protected $table = 'tasy.unidade_atendimento';

    /**
     * Get all beds in a sector
     *
     * Retorna apenas dados do leito e ocupação, sem dados clínicos/detalhados do paciente.
     */
    public function getBedsBySector($sectorId)
    {
        $cacheKey = "beds_by_sector_{$sectorId}";

        return Cache::remember($cacheKey, 600, function() use ($sectorId) {
            $results = DB::connection('tasy')->select("
                SELECT
                    ua.cd_unidade_basica,
                    ua.cd_unidade_basica as bed_name,
                    ua.nr_seq_interno as bed_sequence,
                    ua.nr_seq_interno as display_order,
                    ua.ie_situacao as bed_status,
                    ua.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    ags.ds_agrupamento as hospital_name,

                    -- Current occupancy
                    ua.nr_atendimento as current_attendance,
                    CASE WHEN ua.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as is_occupied,

                    -- Patient minimal info if occupied
                    atp.cd_pessoa_fisica,
                    tasy.obter_nome_paciente(ua.nr_atendimento) AS patient_name,
                    atp.dt_entrada,
                    TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS internment_days

                FROM tasy.unidade_atendimento ua
                JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
                LEFT JOIN tasy.agrupamento_setor ags ON sa.nr_seq_agrupamento = ags.nr_sequencia
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                    AND atp.dt_alta IS NULL
                WHERE ua.cd_setor_atendimento = :sector_id
                  AND ua.ie_situacao = 'A'
                ORDER BY
                    CASE
                        WHEN ua.nr_seq_interno IS NOT NULL THEN ua.nr_seq_interno
                        ELSE 999999
                    END ASC,
                    ua.cd_unidade_basica ASC
            ", ['sector_id' => $sectorId]);

            return collect($results)->map(function($record) {
                return (object) [
                    'cd_unidade_basica'      => $record->cd_unidade_basica,
                    'bed_name'               => $record->bed_name,
                    'bed_sequence'           => $record->bed_sequence,
                    'display_order'          => $record->display_order,
                    'bed_status'             => $record->bed_status,
                    'cd_setor_atendimento'   => $record->cd_setor_atendimento,
                    'ds_setor_atendimento'   => $record->ds_setor_atendimento,
                    'hospital_name'          => property_exists($record, 'hospital_name') ? $record->hospital_name : null,
                    'current_attendance'     => $record->current_attendance,
                    'nr_atendimento'         => $record->current_attendance,
                    'is_occupied'            => (bool)$record->is_occupied,
                    'cd_pessoa_fisica'       => $record->cd_pessoa_fisica,
                    'patient_name'           => $record->patient_name,
                    'dt_entrada'             => $record->dt_entrada,
                    'internment_days'        => $record->internment_days ? intval($record->internment_days) : null
                ];
            });
        });
    }

    /**
     * Busca leitos de múltiplos setores em uma única query
     */
    public function getBedsBySectors(array $sectorIds)
    {
        if (empty($sectorIds)) {
            return collect();
        }

        $placeholders = str_repeat('?,', count($sectorIds) - 1) . '?';

        $results = DB::connection('tasy')->select("
            SELECT
                ua.cd_unidade_basica,
                ua.cd_unidade_basica as bed_name,
                ua.nr_seq_interno as bed_sequence,
                ua.cd_setor_atendimento,
                sa.ds_setor_atendimento
            FROM tasy.unidade_atendimento ua
            JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
            WHERE ua.cd_setor_atendimento IN ({$placeholders})
            AND ua.ie_situacao = 'A'
            ORDER BY ua.cd_setor_atendimento,
                    CASE WHEN ua.nr_seq_interno IS NOT NULL THEN ua.nr_seq_interno ELSE 999999 END,
                    ua.cd_unidade_basica
        ", $sectorIds);

        return collect($results);
    }

    /**
     * Get bed details by ID
     */
    public function getBedDetails($bedId)
    {
        $cacheKey = "bed_details_{$bedId}";

        return Cache::remember($cacheKey, 600, function() use ($bedId) {
            $result = DB::connection('tasy')->select("
                SELECT
                    ua.cd_unidade_basica,
                    ua.cd_unidade_basica as bed_name,
                    ua.nr_seq_interno as bed_sequence,
                    ua.nr_seq_interno as display_order,
                    ua.ie_situacao as bed_status,
                    ua.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    ags.ds_agrupamento as hospital_name,

                    -- Current occupancy
                    ua.nr_atendimento as current_attendance,
                    CASE WHEN ua.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as is_occupied,

                    -- Patient minimal info if occupied
                    atp.cd_pessoa_fisica,
                    tasy.obter_nome_paciente(ua.nr_atendimento) AS patient_name,
                    atp.dt_entrada,
                    TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS internment_days

                FROM tasy.unidade_atendimento ua
                JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
                LEFT JOIN tasy.agrupamento_setor ags ON sa.nr_seq_agrupamento = ags.nr_sequencia
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                    AND atp.dt_alta IS NULL
                WHERE ua.cd_unidade_basica = :bed_id
                  AND ua.ie_situacao = 'A'
            ", ['bed_id' => $bedId]);

            return !empty($result) ? $result[0] : null;
        });
    }

    /**
     * Get bed occupancy history
     */
    public function getBedOccupancyHistory($bedId, $days = 30)
    {
        $cacheKey = "bed_history_{$bedId}_{$days}";

        return Cache::remember($cacheKey, 1800, function() use ($bedId, $days) {
            $results = DB::connection('tasy')->select("
                SELECT
                    atp.nr_atendimento,
                    atp.cd_pessoa_fisica,
                    tasy.obter_nome_paciente(atp.nr_atendimento) AS patient_name,
                    atp.dt_entrada,
                    atp.dt_alta,
                    CASE WHEN atp.dt_alta IS NULL THEN 1 ELSE 0 END as is_current,
                    TRUNC(NVL(atp.dt_alta, SYSDATE) - TRUNC(atp.dt_entrada)) AS length_of_stay
                FROM tasy.unidade_atendimento ua
                JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                WHERE ua.cd_unidade_basica = :bed_id
                  AND atp.dt_entrada >= SYSDATE - :days
                ORDER BY atp.dt_entrada DESC
            ", ['bed_id' => $bedId, 'days' => $days]);

            return collect($results)->map(function($record) {
                return (object) [
                    'nr_atendimento' => $record->nr_atendimento,
                    'cd_pessoa_fisica' => $record->cd_pessoa_fisica,
                    'patient_name' => $record->patient_name,
                    'dt_entrada' => $record->dt_entrada,
                    'dt_alta' => $record->dt_alta,
                    'is_current' => (bool)$record->is_current,
                    'length_of_stay' => intval($record->length_of_stay ?? 0)
                ];
            });
        });
    }

    /**
     * Get beds with occupancy statistics
     */
    public function getBedsWithStatistics($sectorId = null)
    {
        $cacheKey = "beds_with_stats_" . ($sectorId ?? 'all');

        return Cache::remember($cacheKey, 900, function() use ($sectorId) {
            $whereClause = $sectorId ? "AND ua.cd_setor_atendimento = :sector_id" : "";
            $params = $sectorId ? ['sector_id' => $sectorId] : [];

            $results = DB::connection('tasy')->select("
                SELECT
                    ua.cd_unidade_basica,
                    ua.cd_unidade_basica as bed_name,
                    ua.cd_setor_atendimento,
                    sa.ds_setor_atendimento,
                    ags.ds_agrupamento as hospital_name,

                    -- Current status
                    CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END as is_occupied,
                    ua.nr_atendimento as current_attendance,

                    -- Statistics (last 30 days)
                    (SELECT COUNT(*)
                     FROM tasy.unidade_atendimento ua2
                     JOIN tasy.atendimento_paciente atp2 ON ua2.nr_atendimento = atp2.nr_atendimento
                     WHERE ua2.cd_unidade_basica = ua.cd_unidade_basica
                       AND atp2.dt_entrada >= SYSDATE - 30) as admissions_30d,

                    (SELECT AVG(TRUNC(NVL(atp3.dt_alta, SYSDATE) - TRUNC(atp3.dt_entrada)))
                     FROM tasy.unidade_atendimento ua3
                     JOIN tasy.atendimento_paciente atp3 ON ua3.nr_atendimento = atp3.nr_atendimento
                     WHERE ua3.cd_unidade_basica = ua.cd_unidade_basica
                       AND atp3.dt_entrada >= SYSDATE - 30) as avg_length_of_stay

                FROM tasy.unidade_atendimento ua
                JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
                LEFT JOIN tasy.agrupamento_setor ags ON sa.nr_seq_agrupamento = ags.nr_sequencia
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento
                    AND atp.dt_alta IS NULL
                WHERE ua.ie_situacao = 'A'
                  {$whereClause}
                ORDER BY
                    ags.ds_agrupamento ASC,
                    sa.ds_setor_atendimento ASC,
                    CASE
                        WHEN ua.nr_seq_interno IS NOT NULL THEN ua.nr_seq_interno
                        ELSE 999999
                    END ASC,
                    ua.cd_unidade_basica ASC
            ", $params);

            return collect($results)->map(function($record) {
                return (object) [
                    'cd_unidade_basica' => $record->cd_unidade_basica,
                    'bed_name' => $record->bed_name,
                    'cd_setor_atendimento' => $record->cd_setor_atendimento,
                    'ds_setor_atendimento' => $record->ds_setor_atendimento,
                    'hospital_name' => $record->hospital_name,
                    'is_occupied' => (bool)$record->is_occupied,
                    'current_attendance' => $record->current_attendance,
                    'admissions_30d' => intval($record->admissions_30d ?? 0),
                    'avg_length_of_stay' => $record->avg_length_of_stay ? round(floatval($record->avg_length_of_stay), 1) : 0
                ];
            });
        });
    }

    /**
     * Get bed ID (primary key)
     */
    public function getIdAttribute()
    {
        return $this->cd_unidade_basica;
    }
}
