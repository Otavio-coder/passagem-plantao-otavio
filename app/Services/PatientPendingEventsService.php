<?php

namespace App\Services;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use App\Services\PendingEvents\Handlers\{
    PrescriptionPendingHandler,
    HemotherapyPendingHandler,
    AntibioticPendingHandler,
    ChemotherapyPendingHandler,
    SurgeryPendingHandler,
    ExamsPendingHandler
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

    /** @var PendingEventHandler[] */
    private array $handlers;

    public function __construct()
    {
        $this->handlers = [
            new PrescriptionPendingHandler(),
            new HemotherapyPendingHandler(),
            new AntibioticPendingHandler(),
            new ChemotherapyPendingHandler(),
            new SurgeryPendingHandler(),
            new ExamsPendingHandler(),
        ];
    }

    /**
     * Busca pendências do setor em batch.
     * Retorna [nr_atendimento => ['events' => [...], 'discharge' => array|null]]
     */
    public function getPendingEventsForSector(int $sectorId): array
    {
        $cacheKey = "sector_pending_fast_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sectorId) {
            $start = microtime(true);

            $rows = DB::connection('tasy')->select("
                SELECT
                    ua.nr_atendimento,
                    ap.cd_pessoa_fisica,
                    pf.dt_obito,
                    ap.dt_alta,
                    ap.dt_alta_medico,
                    ma2.ds_motivo_alta,
                    apa.dt_previsto_alta AS apa_dt_previsto_alta,
                    COUNT(DISTINCT CASE
                        WHEN pp.dt_baixa IS NULL AND pp.dt_coleta IS NULL
                            AND pp.ie_status_execucao = '10'
                            AND pm.dt_liberacao IS NOT NULL
                            AND pm.dt_suspensao IS NULL
                        THEN pp.nr_seq_proc_interno || ',' || NVL(TO_CHAR(pp.cd_procedimento), '0')
                    END) as prescr_count,
                    COUNT(DISTINCT CASE
                        WHEN hemo.dt_suspensao IS NULL
                            AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                        THEN hemo.nr_sequencia
                    END) as hemo_count
                FROM tasy.unidade_atendimento ua
                INNER JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
                INNER JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
                LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
                LEFT JOIN (
                    SELECT nr_atendimento, dt_previsto_alta,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) AS rn
                    FROM tasy.atend_previsao_alta
                    WHERE dt_registro >= SYSDATE - 10
                ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
                LEFT JOIN tasy.prescr_medica pm ON pm.nr_atendimento = ua.nr_atendimento
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_suspensao IS NULL
                LEFT JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                    AND pp.ie_status_execucao = '10'
                    AND pp.dt_coleta IS NULL
                    AND pp.dt_baixa IS NULL
                    AND pp.ie_origem_proced <> 4
                LEFT JOIN tasy.cpoe_hemoterapia hemo ON hemo.nr_atendimento = ua.nr_atendimento
                    AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                    AND hemo.dt_suspensao IS NULL
                WHERE ua.cd_setor_atendimento = :sector_id
                    AND ua.ie_situacao = 'A'
                    AND ap.dt_alta IS NULL
                GROUP BY ua.nr_atendimento, ap.cd_pessoa_fisica, pf.dt_obito,
                    ap.dt_alta, ap.dt_alta_medico, ma2.ds_motivo_alta, apa.dt_previsto_alta
            ", ['sector_id' => $sectorId]);

            Log::info("[PendingEvents] Query principal: " . round((microtime(true) - $start) * 1000, 2) . "ms - " . count($rows) . " pacientes");

            $results     = [];
            $needsPrescr = [];
            $needsHemo   = [];
            $allNrs      = [];

            foreach ($rows as $row) {
                $nr     = $row->nr_atendimento;
                $events = [];

                // ÓBITO
                if (!empty($row->dt_obito)) {
                    $events[] = [
                        'tipo'               => 'aviso',
                        'subtipo'            => 'obito',
                        'icone'              => 'alert.svg',
                        'descricao'          => 'Óbito registrado',
                        'urgente'            => true,
                        'dt_evento'          => $row->dt_obito,
                        'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_obito)),
                    ];
                }

                // DISCHARGE INFO
                $discharge = $this->buildDischarge($row, $events);

                if ($row->prescr_count > 0) $needsPrescr[] = $nr;
                if ($row->hemo_count   > 0) $needsHemo[]   = $nr;

                $allNrs[]     = $nr;
                $results[$nr] = ['events' => $events, 'discharge' => $discharge];
            }

            // Executa handlers especializados
            $this->handlers[0]->handle($results, $needsPrescr);  // Prescrições
            $this->handlers[1]->handle($results, $needsHemo);    // Hemoterapia
            $this->handlers[2]->handle($results, $allNrs);       // Antibióticos
            $this->handlers[3]->handle($results, $allNrs);       // Quimioterapia
            $this->handlers[4]->handle($results, $allNrs);       // Cirurgias
            $this->handlers[5]->handle($results, $allNrs);       // Exames agendados

            // Ordena eventos: urgentes primeiro, depois por proximidade ao momento atual
            $now = time();
            foreach ($results as &$data) {
                usort($data['events'], function ($a, $b) use ($now) {
                    $urgA = $a['urgente'] ?? false;
                    $urgB = $b['urgente'] ?? false;
                    if ($urgA !== $urgB) return $urgA ? -1 : 1;

                    $da = $a['dt_evento'] ?? null;
                    $db = $b['dt_evento'] ?? null;
                    if ($da === null && $db === null) return 0;
                    if ($da === null) return 1;
                    if ($db === null) return -1;

                    return abs(strtotime($da) - $now) - abs(strtotime($db) - $now);
                });
            }
            unset($data);

            Log::info("[PendingEvents] Total: " . round((microtime(true) - $start) * 1000, 2) . "ms");

            return $results;
        });
    }

    private function buildDischarge(object $row, array &$events): ?array
    {
        if (!empty($row->dt_alta)) {
            $events[] = [
                'tipo'               => 'alta',
                'icone'              => 'alta.svg',
                'descricao'          => 'Alta Efetivada' . (!empty($row->ds_motivo_alta) ? ' - ' . $row->ds_motivo_alta : ''),
                'ds_subtipo'         => 'Alta',
                'dt_evento'          => $row->dt_alta,
                'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_alta)),
                'urgente'            => true,
            ];
            return [
                'tipo'               => 'alta',
                'dt_alta'            => $row->dt_alta,
                'dt_alta_formatted'  => date('d/m/Y H:i', strtotime($row->dt_alta)),
                'ds_motivo_alta'     => $row->ds_motivo_alta ?? null,
            ];
        }

        if (!empty($row->dt_alta_medico)) {
            $descAltaMedica = 'Alta Médica';
            if (!empty($row->apa_dt_previsto_alta)) {
                $descAltaMedica .= ' | Prev. Alta: ' . date('d/m/Y', strtotime($row->apa_dt_previsto_alta));
            }
            $events[] = [
                'tipo'               => 'alta_medica',
                'icone'              => 'alta.svg',
                'descricao'          => $descAltaMedica,
                'ds_subtipo'         => 'Alta Médica',
                'dt_evento'          => $row->dt_alta_medico,
                'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_alta_medico)),
                'urgente'            => true,
            ];
            return [
                'tipo'                       => 'alta_medica',
                'dt_alta_medico'             => $row->dt_alta_medico,
                'dt_alta_medico_formatted'   => date('d/m/Y H:i', strtotime($row->dt_alta_medico)),
                'dt_previsto_alta'           => $row->apa_dt_previsto_alta ?? null,
                'dt_previsto_alta_formatted' => !empty($row->apa_dt_previsto_alta)
                    ? date('d/m/Y H:i', strtotime($row->apa_dt_previsto_alta)) : null,
            ];
        }

        if (!empty($row->apa_dt_previsto_alta)) {
            $events[] = [
                'tipo'               => 'previsao_alta',
                'icone'              => 'alta.svg',
                'descricao'          => 'Previsão de Alta: ' . date('d/m/Y', strtotime($row->apa_dt_previsto_alta)),
                'ds_subtipo'         => 'Previsão',
                'dt_evento'          => $row->apa_dt_previsto_alta,
                'dt_evento_formatted'=> date('d/m/Y', strtotime($row->apa_dt_previsto_alta)),
                'urgente'            => false,
            ];
            return [
                'tipo'                       => 'previsao_alta',
                'dt_previsto_alta'           => $row->apa_dt_previsto_alta,
                'dt_previsto_alta_formatted' => date('d/m/Y', strtotime($row->apa_dt_previsto_alta)),
            ];
        }

        return null;
    }
}
