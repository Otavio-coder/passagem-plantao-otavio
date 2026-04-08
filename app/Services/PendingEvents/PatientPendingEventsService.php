<?php

namespace App\Services\PendingEvents;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Services\PendingEvents\Handlers\AgendaPendingHandler;
use App\Services\PendingEvents\Handlers\AntibioticPendingHandler;
use App\Services\PendingEvents\Handlers\ChemotherapyPendingHandler;
use App\Services\PendingEvents\Handlers\HemotherapyPendingHandler;
use App\Services\PendingEvents\Handlers\PrescriptionPendingHandler;
use App\Services\Tasy\TasyFormatter;
use App\Support\PendingEventPresentation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Coordenador de eventos pendentes por setor.
 *
 * A query principal carrega os dados básicos por paciente.
 * Cada handler especializado enriquece os resultados com seu tipo de evento.
 *
 * Retorna: [nr_atendimento => ['events' => [...], 'discharge' => array|null]]
 */
class PatientPendingEventsService
{
    private const CACHE_TTL = 180; // 3 minutos

    /** @var AbstractPendingHandler[] */
    private array $handlers;

    public function __construct()
    {
        $repo = app(PatientPrescriptionsRepository::class);

        $this->handlers = [
            new PrescriptionPendingHandler,
            new HemotherapyPendingHandler($repo),
            new AntibioticPendingHandler($repo),
            new ChemotherapyPendingHandler($repo),
            new AgendaPendingHandler($repo),
        ];
    }

    /**
     * Busca pendências do setor em batch.
     * Retorna [nr_atendimento => ['events' => [...], 'discharge' => array|null]]
     */
    public function getPendingEventsForSector(int $sectorId): array
    {
        $cacheKey = "sector_pending_fast_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->fetchEventsForSector($sectorId));
    }

    /**
     * Busca pendências sem cache — usar em relatórios que exigem dados sempre frescos.
     */
    public function getFreshEventsForSector(int $sectorId): array
    {
        return $this->fetchEventsForSector($sectorId);
    }

    private function fetchEventsForSector(int $sectorId): array
    {
        $start = microtime(true);

        // Query principal otimizada: traz dados básicos e contexto de alta médica.
        $rows = DB::connection('tasy')->select("
                SELECT
                    ua.nr_atendimento,
                    ap.cd_pessoa_fisica,
                    pf.dt_obito,
                    ap.dt_alta,
                    ap.dt_alta_medico,
                    ma2.ds_motivo_alta,
                    apa.dt_previsto_alta AS apa_dt_previsto_alta
                FROM tasy.unidade_atendimento ua
                INNER JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
                INNER JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
                LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
                LEFT JOIN (
                    SELECT nr_atendimento, dt_previsto_alta,
                        ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) AS rn
                    FROM tasy.atend_previsao_alta
                ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
                WHERE ua.cd_setor_atendimento = :sector_id
                    AND ua.ie_situacao = 'A'
                    AND ap.dt_alta IS NULL
            ", ['sector_id' => $sectorId]);

        try {
            Log::debug('[PendingEvents] Query principal', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'patient_count' => count($rows),
                'sector_id' => $sectorId,
            ]);
        } catch (\Throwable) {
        }

        $results = [];
        $allNrs = [];

        foreach ($rows as $row) {
            $nr = $row->nr_atendimento;
            $events = [];

            // ÓBITO
            if (! empty($row->dt_obito)) {
                $events[] = [
                    'tipo' => 'aviso',
                    'subtipo' => 'obito',
                    'icone' => 'alert.svg',
                    'descricao' => 'Óbito registrado',
                    'urgente' => true,
                    'dt_evento' => $row->dt_obito,
                    'dt_evento_formatted' => Carbon::parse($row->dt_obito)->format('d/m/Y H:i'),
                ];
            }

            $discharge = $this->buildDischarge($row, $events);
            $allNrs[] = $nr;
            $results[$nr] = ['events' => $events, 'discharge' => $discharge];
        }

        // Executa handlers especializados — todos recebem $allNrs;
        // cada handler filtra internamente quais atendimentos têm dados.
        foreach ($this->handlers as $handler) {
            $handler->handle($results, $allNrs);
        }

        // Ordena eventos: urgentes primeiro, depois por proximidade ao momento atual
        $now = now()->timestamp;
        foreach ($results as &$data) {
            usort($data['events'], function ($a, $b) use ($now) {
                $urgA = $a['urgente'] ?? false;
                $urgB = $b['urgente'] ?? false;
                if ($urgA !== $urgB) {
                    return $urgA ? -1 : 1;
                }

                $da = $a['dt_evento'] ?? null;
                $db = $b['dt_evento'] ?? null;
                if ($da === null && $db === null) {
                    return 0;
                }
                if ($da === null) {
                    return 1;
                }
                if ($db === null) {
                    return -1;
                }

                return abs(Carbon::parse($da)->timestamp - $now) - abs(Carbon::parse($db)->timestamp - $now);
            });
        }
        unset($data);

        // Adiciona motivo_pendente a todos os eventos (fonte única: PendingEventPresentation)
        foreach ($results as &$data) {
            foreach ($data['events'] as &$event) {
                $event['motivo_pendente'] = PendingEventPresentation::motivoPendente($event);
            }
            unset($event);
        }
        unset($data);

        try {
            Log::debug('[PendingEvents] Total', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'sector_id' => $sectorId,
            ]);
        } catch (\Throwable) {
        }

        return $this->sanitizeUtf8($results);
    }

    /**
     * Recursively scrub invalid UTF-8 sequences from Oracle strings.
     * MySQL utf8mb4 rejects invalid byte sequences that some Oracle charsets produce.
     */
    private function sanitizeUtf8(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_scrub($value, 'UTF-8');
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitizeUtf8($v), $value);
        }

        return $value;
    }

    private function buildDischarge(object $row, array &$events): ?array
    {
        if (! empty($row->dt_alta)) {
            $events[] = [
                'tipo' => 'alta',
                'icone' => 'alta.svg',
                'descricao' => 'Alta Efetivada'.(! empty($row->ds_motivo_alta) ? ' - '.$row->ds_motivo_alta : ''),
                'ds_subtipo' => 'Alta',
                'dt_evento' => $row->dt_alta,
                'dt_evento_formatted' => Carbon::parse($row->dt_alta)->format('d/m/Y H:i'),
                'urgente' => true,
            ];

            return TasyFormatter::buildDischargeInfo(
                (string) $row->dt_alta,
                null,
                null,
                ! empty($row->ds_motivo_alta) ? (string) $row->ds_motivo_alta : null
            );
        }

        if (! empty($row->dt_alta_medico)) {
            $descAltaMedica = 'Alta Médica';
            if (! empty($row->apa_dt_previsto_alta)) {
                $descAltaMedica .= ' | Prev. Alta: '.Carbon::parse($row->apa_dt_previsto_alta)->format('d/m/Y H:i');
            }
            $events[] = [
                'tipo' => 'alta_medica',
                'icone' => 'alta.svg',
                'descricao' => $descAltaMedica,
                'ds_subtipo' => 'Alta Médica',
                'dt_evento' => $row->dt_alta_medico,
                'dt_evento_formatted' => Carbon::parse($row->dt_alta_medico)->format('d/m/Y H:i'),
                'urgente' => true,
            ];

            return TasyFormatter::buildDischargeInfo(
                null,
                (string) $row->dt_alta_medico,
                ! empty($row->apa_dt_previsto_alta) ? (string) $row->apa_dt_previsto_alta : null,
                ! empty($row->ds_motivo_alta) ? (string) $row->ds_motivo_alta : null
            );
        }

        return null;
    }
}
