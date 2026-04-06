<?php

// File: `app/Repositories/EMR/PatientScalesRepository.php`

namespace App\Repositories\EMR;

use App\Models\EMR\Scales\Braden;
use App\Models\EMR\Scales\Mews;
use App\Models\EMR\Scales\Morse;
use App\Models\EMR\Scales\Pain;
use App\Models\EMR\Scales\Pews;
use App\Models\EMR\Scales\VteThrombosis;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientScalesRepository
{
    protected $connection = 'tasy';

    protected const CHUNK_SIZE = 200;

    /** @var array<string, array{class: class-string, period: string, val: string, dt: string}> */
    private const SCALE_CONFIG = [
        'mews' => ['class' => Mews::class,          'period' => 'turno', 'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
        'pews' => ['class' => Pews::class,          'period' => 'turno', 'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
        'braden' => ['class' => Braden::class,        'period' => '24h',   'val' => 'qt_ponto',      'dt' => 'dt_avaliacao'],
        'morse' => ['class' => Morse::class,         'period' => '24h',   'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
        'pain' => ['class' => Pain::class,          'period' => 'turno', 'val' => 'qt_escala_dor', 'dt' => 'dt_sinal_vital'],
        'vte' => ['class' => VteThrombosis::class, 'period' => '24h',   'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
    ];

    public function getPatientsScalesUnified(array $attendanceNumbers, array $isNewPatientMap = []): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $result = [];

        foreach (array_chunk(array_values($attendanceNumbers), self::CHUNK_SIZE) as $chunk) {
            try {
                $p = implode(',', array_fill(0, count($chunk), '?'));

                // Single UNION ALL + ROW_NUMBER: 1 Oracle round-trip instead of 6
                $rows = DB::connection('tasy')->select("
                    SELECT scale_type, nr_atendimento, score_value, dt_ref, rn
                    FROM (
                        SELECT 'mews'   AS scale_type, nr_atendimento, qt_pontuacao  AS score_value, dt_avaliacao   AS dt_ref,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)   AS rn
                        FROM tasy.escala_mews
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                        UNION ALL
                        SELECT 'pews',   nr_atendimento, qt_pontuacao,  dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.escala_pews
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                        UNION ALL
                        SELECT 'braden', nr_atendimento, qt_ponto,      dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.atend_escala_braden
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_ponto IS NOT NULL
                        UNION ALL
                        SELECT 'morse',  nr_atendimento, qt_pontuacao,  dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.escala_morse
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                        UNION ALL
                        SELECT 'pain',   nr_atendimento, qt_escala_dor, dt_sinal_vital,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_sinal_vital DESC)
                        FROM tasy.atendimento_sinal_vital
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_escala_dor IS NOT NULL
                        UNION ALL
                        SELECT 'vte',    nr_atendimento, qt_pontuacao,  dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.escala_tev
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                    )
                    WHERE rn <= 2
                ", array_merge($chunk, $chunk, $chunk, $chunk, $chunk, $chunk));

                // Index by attendance → scale_type → rn (1=current, 2=previous)
                $index = [];
                foreach ($rows as $row) {
                    $index[$row->nr_atendimento][$row->scale_type][$row->rn] = $row;
                }

                foreach ($chunk as $attendance) {
                    $isNew = (bool) ($isNewPatientMap[$attendance] ?? false);
                    $scaleData = [];

                    foreach (self::SCALE_CONFIG as $key => $cfg) {
                        $current = $this->toScaleObject($index[$attendance][$key][1] ?? null, $cfg['val'], $cfg['dt']);
                        $previous = $this->toScaleObject($index[$attendance][$key][2] ?? null, $cfg['val'], $cfg['dt']);
                        $scaleData[$key] = $cfg['class']::buildStructure($current, $previous, $cfg['period'], $isNew);
                    }

                    $result[$attendance] = $scaleData;
                }
            } catch (\Throwable $e) {
                Log::error('PatientScalesRepository::getPatientsScalesUnified failed', [
                    'error' => $e->getMessage(),
                    'chunk_sample' => array_slice($chunk, 0, 5),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Builds a minimal stdClass that BaseScale::buildStructure() can consume.
     * buildStructure() only reads $model->{$valueCol} and $model->{$dateCol}.
     */
    private function toScaleObject(?object $raw, string $valueCol, string $dateCol): ?object
    {
        if ($raw === null) {
            return null;
        }

        $obj = new \stdClass;
        $obj->{$valueCol} = $raw->score_value ?? null;
        $obj->{$dateCol} = $raw->dt_ref ?? null;

        return $obj;
    }

    /**
     * Extrai current e previous de uma collection agrupada
     * Retorna o registro mais recente como current e o segundo mais recente como previous
     */
    private function pickCurrentAndPrevious($grouped, $attendanceKey): array
    {
        if (! isset($grouped[$attendanceKey])) {
            return [null, null];
        }

        $collection = $grouped[$attendanceKey]->values();
        $current = $collection->get(0) ?? null;

        if (! $current) {
            return [null, null];
        }

        $previous = null;

        // Busca o primeiro registro que seja anterior ao current
        for ($i = 1; $i < $collection->count(); $i++) {
            $candidate = $collection->get($i);

            if ($this->isBeforeCurrent($current, $candidate)) {
                $previous = $candidate;
                break;
            }
        }

        return [$current, $previous];
    }

    /**
     * Verifica se candidate é anterior ao current baseado na data
     */
    private function isBeforeCurrent($current, $candidate): bool
    {
        if (! $current || ! $candidate) {
            return false;
        }

        $dateCol = get_class($candidate) === Pain::class
            ? 'dt_sinal_vital'
            : 'dt_avaliacao';

        $currentTs = $current->{$dateCol} ?? null;
        $candidateTs = $candidate->{$dateCol} ?? null;

        if (! $currentTs || ! $candidateTs) {
            return false;
        }

        try {
            $currentCarbon = Carbon::parse($currentTs);
            $candidateCarbon = Carbon::parse($candidateTs);

            return $candidateCarbon < $currentCarbon;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Backwards compatibility helper
     */
    public function getPatientScalesUnified(int $attendanceNumber, bool $isNewPatient = false): ?array
    {
        if (! $attendanceNumber) {
            return null;
        }

        $map = $this->getPatientsScalesUnified(
            [$attendanceNumber],
            [$attendanceNumber => $isNewPatient]
        );

        return $map[$attendanceNumber] ?? null;
    }
}
