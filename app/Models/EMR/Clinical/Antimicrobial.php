<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Antimicrobial extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PRESCR_MATERIAL';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_suspensao' => 'datetime',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(\App\Models\EMR\CPOE\Prescription::class, 'nr_prescricao', 'nr_prescricao');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'cd_material', 'cd_material');
    }

    /**
     * Busca antimicrobianos ativos de um paciente
     * Usa função TASY padrão para evitar dependência de estrutura de tabela
     */
    public static function getActiveForAttendance($attendanceNumber): ?string
    {
        // Tenta usar o método simples primeiro
        return static::getActiveForAttendanceSimple($attendanceNumber);
    }

    /**
     * Método alternativo mais simples que não depende da estrutura exata
     */
    public static function getActiveForAttendanceSimple($attendanceNumber): ?string
    {
        try {
            // Busca usando função TASY se existir
            $result = DB::connection('tasy')->selectOne("
                SELECT TASY.OBTER_ANTIMICROBIANOS_ATEND(:nr_atendimento) AS antimicrobianos
                FROM dual
            ", ['nr_atendimento' => $attendanceNumber]);

            return $result->antimicrobianos ?? null;
        } catch (\Exception $e1) {
            try {
                // Fallback: busca direto dos materiais prescritos
                $materials = DB::connection('tasy')->select("
                    SELECT DISTINCT
                        TASY.OBTER_DESC_MATERIAL(pt.cd_material) AS material,
                        pt.nr_dia_util
                    FROM tasy.prescr_material pt
                    JOIN tasy.prescr_medica pm ON pm.nr_prescricao = pt.nr_prescricao
                    JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia = (
                        SELECT nr_seq_ficha_tecnica
                        FROM tasy.material
                        WHERE cd_material = pt.cd_material
                    )
                    WHERE pm.nr_atendimento = :nr_atendimento
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_validade_prescr >= SYSDATE
                    AND pm.dt_suspensao IS NULL
                    AND pt.dt_suspensao IS NULL
                    AND mf.ie_antimicrobiano = 'S'
                    AND pt.nr_dia_util IS NOT NULL
                    ORDER BY material
                ", ['nr_atendimento' => $attendanceNumber]);

                if (empty($materials)) {
                    return null;
                }

                $list = array_map(function($m) {
                    return ucwords(strtolower($m->material)) . ' ' . $m->nr_dia_util . ' Dia(s)';
                }, $materials);

                return implode("\n", $list);
            } catch (\Exception $e2) {
                \Log::warning("Erro ao buscar antimicrobianos (fallback): " . $e2->getMessage(), [
                    'attendance' => $attendanceNumber
                ]);
                return null;
            }
        }
    }
}
