<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientMultidisciplinaryRepository
{
    private const TEAM_SEQ_MAP = [
        'fonoaudiologia' => [43],
        'servico_social' => [39],
        'nutricao' => [42],
        'fisioterapia' => [593, 609, 608],
        'psicologia' => [41],
        'acessos_vasculares' => [639, 644, 646, 647, 648, 651, 652, 653, 655],
    ];

    private const DEFAULT_TEAMS = [
        'fisioterapia' => false,
        'psicologia' => false,
        'nutricao' => false,
        'fonoaudiologia' => false,
        'servico_social' => false,
        'acessos_vasculares' => false,
    ];

    /**
     * Busca detalhes completos das solicitações de parecer de um único atendimento
     */
    public function getDetailedMultidisciplinaryRequests(int $attendanceNumber): array
    {
        try {
            $rows = DB::connection('tasy')->select('
                SELECT
                    pr.nr_parecer,
                    pr.dt_criacao          AS dt_registro,
                    pr.dt_liberacao,
                    pr.ie_situacao         AS ie_status,
                    pf_req.nm_pessoa_fisica AS nm_requisitante,
                    tp.ds_tipo_parecer     AS ds_equipe_destino,
                    pm_resp.dt_registro    AS dt_resposta,
                    COALESCE(pf_resp.nm_pessoa_fisica, pm_resp.nm_usuario) AS nm_responsavel_resposta
                FROM tasy.PARECER_MEDICO_REQ pr
                LEFT JOIN tasy.pessoa_fisica pf_req
                    ON pf_req.cd_pessoa_fisica = pr.cd_pessoa_fisica
                LEFT JOIN tasy.TIPO_PARECER tp
                    ON tp.nr_sequencia = pr.nr_seq_tipo_parecer
                LEFT JOIN tasy.PARECER_MEDICO pm_resp
                    ON pm_resp.nr_parecer = pr.nr_parecer
                   AND pm_resp.nr_sequencia = (
                       SELECT MAX(pm2.nr_sequencia)
                       FROM tasy.PARECER_MEDICO pm2
                       WHERE pm2.nr_parecer = pr.nr_parecer
                   )
                LEFT JOIN tasy.usuario u_resp
                    ON u_resp.nm_usuario = pm_resp.nm_usuario
                LEFT JOIN tasy.pessoa_fisica pf_resp
                    ON pf_resp.cd_pessoa_fisica = u_resp.cd_pessoa_fisica
                WHERE pr.nr_atendimento = :attendance
                  AND pr.dt_liberacao IS NOT NULL
                  AND pr.dt_inativacao IS NULL
                ORDER BY pr.dt_criacao DESC
            ', ['attendance' => $attendanceNumber]);

            if (empty($rows)) {
                return [];
            }

            $statusMap = ['A' => 'Aberto', 'R' => 'Respondido', 'L' => 'Liberado', 'C' => 'Cancelado'];
            $parecerIds = array_map(fn ($r) => $r->nr_parecer, $rows);

            $motivoMap = $this->fetchPareceMotivos($parecerIds);
            $pareceresMap = $this->fetchPareceRespostas($parecerIds);

            $requests = [];
            foreach ($rows as $row) {
                $requests[] = [
                    'nr_parecer' => $row->nr_parecer,
                    'dt_registro' => $row->dt_registro,
                    'dt_liberacao' => $row->dt_liberacao,
                    'dt_resposta' => $row->dt_resposta,
                    'ds_motivo_consulta' => $motivoMap[$row->nr_parecer] ?? null,
                    'ds_parecer' => $pareceresMap[$row->nr_parecer] ?? null,
                    'ie_status' => $row->ie_status,
                    'ds_status' => $statusMap[$row->ie_status] ?? $row->ie_status,
                    'nm_requisitante' => $row->nm_requisitante,
                    'ds_equipe_destino' => $row->ds_equipe_destino,
                    'ds_tipo_parecer_destino' => null,
                    'nm_responsavel_resposta' => $row->nm_responsavel_resposta,
                ];
            }

            return $requests;
        } catch (\Throwable $e) {
            Log::warning('PatientMultidisciplinaryRepository::getDetailedMultidisciplinaryRequests failed', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Detecção de equipes multidisciplinares de um único atendimento via nr_seq_equipe_dest
     */
    public function getMultidisciplinaryTeamEvaluations(int $attendanceNumber): array
    {
        try {
            $rows = DB::connection('tasy')->select('
                SELECT pr.nr_seq_equipe_dest
                FROM tasy.PARECER_MEDICO_REQ pr
                WHERE pr.nr_atendimento = :attendance
                  AND pr.dt_liberacao IS NOT NULL
                  AND pr.dt_inativacao IS NULL
                  AND pr.nr_seq_equipe_dest IS NOT NULL
            ', ['attendance' => $attendanceNumber]);

            if (empty($rows)) {
                return self::DEFAULT_TEAMS;
            }

            $teams = self::DEFAULT_TEAMS;

            foreach ($rows as $r) {
                $seq = (int) $r->nr_seq_equipe_dest;
                foreach (self::TEAM_SEQ_MAP as $key => $validSeqs) {
                    if (in_array($seq, $validSeqs, true)) {
                        $teams[$key] = true;
                    }
                }
            }

            return $teams;
        } catch (\Throwable $e) {
            Log::warning('PatientMultidisciplinaryRepository::getMultidisciplinaryTeamEvaluations failed', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);

            return self::DEFAULT_TEAMS;
        }
    }

    /**
     * Retorna equipes multidisciplinares associadas aos atendimentos (BATCH)
     */
    public function getMultidisciplinaryTeams(array $attendances): array
    {
        if (empty($attendances)) {
            return [];
        }

        $result = [];

        foreach (array_chunk($attendances, 100) as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT
                        nr_atendimento,
                        nr_seq_equipe_dest
                    FROM tasy.PARECER_MEDICO_REQ
                    WHERE nr_atendimento IN ($placeholders)
                      AND dt_liberacao IS NOT NULL
                      AND dt_inativacao IS NULL
                      AND nr_seq_equipe_dest IS NOT NULL
                ", $chunk);

                foreach ($chunk as $nr) {
                    if (! isset($result[$nr])) {
                        $result[$nr] = self::DEFAULT_TEAMS;
                    }
                }

                foreach ($rows as $row) {
                    $nr = $row->nr_atendimento;
                    $seq = (int) $row->nr_seq_equipe_dest;

                    foreach (self::TEAM_SEQ_MAP as $key => $validSeqs) {
                        if (in_array($seq, $validSeqs, true)) {
                            $result[$nr][$key] = true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('PatientMultidisciplinaryRepository::getMultidisciplinaryTeams chunk failed', [
                    'error' => $e->getMessage(),
                    'chunk_sample' => array_slice($chunk, 0, 5),
                ]);

                foreach ($chunk as $nr) {
                    if (! isset($result[$nr])) {
                        $result[$nr] = self::DEFAULT_TEAMS;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Busca solicitações de parecer detalhadas em BATCH para todos os atendimentos.
     */
    public function getMultidisciplinaryRequestsBatch(array $attendances): array
    {
        if (empty($attendances)) {
            return [];
        }

        $result = [];
        foreach ($attendances as $nr) {
            $result[$nr] = [];
        }

        $statusMap = ['A' => 'Aberto', 'R' => 'Respondido', 'L' => 'Liberado', 'C' => 'Cancelado'];
        $allRows = [];

        foreach (array_chunk($attendances, 50) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $rows = DB::connection('tasy')->select("
                    SELECT
                        pr.nr_atendimento,
                        pr.nr_parecer,
                        pr.dt_criacao          AS dt_registro,
                        pr.dt_liberacao,
                        pr.ie_situacao         AS ie_status,
                        pf_req.nm_pessoa_fisica AS nm_requisitante,
                        tp.ds_tipo_parecer     AS ds_equipe_destino,
                        pm_resp.dt_registro    AS dt_resposta,
                        COALESCE(pf_resp.nm_pessoa_fisica, pm_resp.nm_usuario) AS nm_responsavel_resposta
                    FROM tasy.PARECER_MEDICO_REQ pr
                    LEFT JOIN tasy.pessoa_fisica pf_req
                        ON pf_req.cd_pessoa_fisica = pr.cd_pessoa_fisica
                    LEFT JOIN tasy.TIPO_PARECER tp
                        ON tp.nr_sequencia = pr.nr_seq_tipo_parecer
                    LEFT JOIN tasy.PARECER_MEDICO pm_resp
                        ON pm_resp.nr_parecer = pr.nr_parecer
                       AND pm_resp.nr_sequencia = (
                           SELECT MAX(pm2.nr_sequencia)
                           FROM tasy.PARECER_MEDICO pm2
                           WHERE pm2.nr_parecer = pr.nr_parecer
                       )
                    LEFT JOIN tasy.usuario u_resp
                        ON u_resp.nm_usuario = pm_resp.nm_usuario
                    LEFT JOIN tasy.pessoa_fisica pf_resp
                        ON pf_resp.cd_pessoa_fisica = u_resp.cd_pessoa_fisica
                    WHERE pr.nr_atendimento IN ($placeholders)
                      AND pr.dt_liberacao IS NOT NULL
                      AND pr.dt_inativacao IS NULL
                    ORDER BY pr.nr_atendimento, pr.dt_criacao DESC
                ", $chunk);

                foreach ($rows as $row) {
                    $allRows[$row->nr_parecer] = $row;
                }
            } catch (\Throwable $e) {
                Log::warning('PatientMultidisciplinaryRepository::getMultidisciplinaryRequestsBatch chunk failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($allRows)) {
            return $result;
        }

        $parecerIds = array_keys($allRows);
        $motivoMap = $this->fetchPareceMotivos($parecerIds);
        $pareceresMap = $this->fetchPareceRespostas($parecerIds);

        foreach ($allRows as $nrParecer => $row) {
            $result[$row->nr_atendimento][] = [
                'nr_parecer' => $row->nr_parecer,
                'dt_registro' => $row->dt_registro,
                'dt_liberacao' => $row->dt_liberacao,
                'dt_resposta' => $row->dt_resposta,
                'ds_motivo_consulta' => $motivoMap[$nrParecer] ?? null,
                'ds_parecer' => $pareceresMap[$nrParecer] ?? null,
                'ie_status' => $row->ie_status,
                'ds_status' => $statusMap[$row->ie_status] ?? $row->ie_status,
                'nm_requisitante' => $row->nm_requisitante,
                'ds_equipe_destino' => $row->ds_equipe_destino,
                'ds_tipo_parecer_destino' => null,
                'nm_responsavel_resposta' => $row->nm_responsavel_resposta,
            ];
        }

        return $result;
    }

    /**
     * Busca DS_MOTIVO_CONSULTA (LONG) de PARECER_MEDICO_REQ por lote de nr_parecer.
     * Query isolada porque Oracle só permite 1 LONG por SELECT.
     */
    private function fetchPareceMotivos(array $parecerIds): array
    {
        $result = [];
        if (empty($parecerIds)) {
            return $result;
        }

        foreach (array_chunk($parecerIds, 100) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $rows = DB::connection('tasy')->select("
                    SELECT nr_parecer, ds_motivo_consulta
                    FROM tasy.PARECER_MEDICO_REQ
                    WHERE nr_parecer IN ($placeholders)
                      AND dt_inativacao IS NULL
                ", $chunk);
                foreach ($rows as $row) {
                    $result[$row->nr_parecer] = $this->stripRtf($row->ds_motivo_consulta);
                }
            } catch (\Throwable $e) {
                Log::warning('PatientMultidisciplinaryRepository fetchPareceMotivos failed', [
                    'exception' => $e,
                    'chunk_size' => count($chunk),
                ]);
            }
        }

        return $result;
    }

    /**
     * Busca DS_PARECER (LONG) da última resposta em PARECER_MEDICO por lote de nr_parecer.
     * Query isolada porque Oracle só permite 1 LONG por SELECT.
     */
    private function fetchPareceRespostas(array $parecerIds): array
    {
        $result = [];
        if (empty($parecerIds)) {
            return $result;
        }

        foreach (array_chunk($parecerIds, 100) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $rows = DB::connection('tasy')->select("
                    SELECT pm.nr_parecer, pm.ds_parecer
                    FROM tasy.PARECER_MEDICO pm
                    WHERE pm.nr_parecer IN ($placeholders)
                      AND pm.nr_sequencia = (
                          SELECT MAX(pm2.nr_sequencia)
                          FROM tasy.PARECER_MEDICO pm2
                          WHERE pm2.nr_parecer = pm.nr_parecer
                      )
                ", $chunk);
                foreach ($rows as $row) {
                    $result[$row->nr_parecer] = $this->stripRtf($row->ds_parecer);
                }
            } catch (\Throwable $e) {
                Log::warning('PatientMultidisciplinaryRepository fetchPareceRespostas failed', [
                    'exception' => $e,
                    'chunk_size' => count($chunk),
                ]);
            }
        }

        return $result;
    }

    /**
     * Remove formatação RTF ou HTML e retorna texto simples.
     */
    private function stripRtf(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        $trimmed = ltrim($text);

        if (stripos($trimmed, '<html') === 0 || stripos($trimmed, '<body') === 0) {
            $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
            $text = preg_replace('/<\/p>/i', "\n", $text);
            $text = preg_replace('/<\/div>/i', "\n", $text);
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/(\r\n|\r|\n)+/', "\n", trim($text));

            return $text ?: null;
        }

        if (strpos($trimmed, '{\\rtf') === 0 || strpos($trimmed, '{\rtf') === 0) {
            $text = preg_replace('/\{\\\\fonttbl(?:\{[^}]*\}|[^}])*\}/', '', $text);
            $text = preg_replace('/\{\\\\colortbl(?:\{[^}]*\}|[^}])*\}/', '', $text);

            $text = preg_replace_callback("/\\\\'([0-9a-fA-F]{2})/", function ($m) {
                return mb_convert_encoding(chr(hexdec($m[1])), 'UTF-8', 'Windows-1252');
            }, $text);

            $text = preg_replace('/\\\\par\\b[ ]?/', "\n", $text);
            $text = preg_replace('/\\\\line\\b[ ]?/', "\n", $text);
            $text = preg_replace('/\\\\[a-zA-Z]+\-?\d*[ ]?/', ' ', $text);
            $text = str_replace('\\*', '', $text);
            $text = str_replace(['{', '}'], '', $text);

            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/(\r\n|\r|\n)+/', "\n", trim($text));

            return $text ?: null;
        }

        return trim($text) ?: null;
    }
}
