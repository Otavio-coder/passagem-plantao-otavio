<?php

namespace App\Services\Huddle;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carrega do Tasy (Oracle) os dados de alta que ainda não vêm pelos loaders do SBAR:
 * hoje, o transporte (FORMULARIO_TRANSPORTE). Orientação de alta e prescrição de alta
 * entram aqui pelo mesmo padrão quando as tabelas forem mapeadas.
 *
 * Consulta em lote por setor (uma query para todos os pacientes) e cacheia por alguns
 * minutos, no mesmo padrão dos demais loaders do projeto.
 */
class HuddleDischargeLoader
{
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Status de transporte por nr_atendimento.
     *
     * Regra (a confirmar com dados reais do FORMULARIO_TRANSPORTE):
     *  - 'organizado' → há formulário ativo e liberado (DT_LIBERACAO preenchida)
     *  - 'pendente'   → há formulário ativo, mas ainda não liberado
     *  - null         → nenhum formulário de transporte para a internação
     *
     * @param  int[]  $attendanceNumbers
     * @return array<int, string|null>
     */
    public function transporteForSector(int $sectorId, array $attendanceNumbers): array
    {
        // DESABILITADO: FORMULARIO_TRANSPORTE está vazia neste hospital — o transporte
        // de alta não é registrado no Tasy (processo manual). Evita consultar tabela
        // sem uso. O item "Transporte" segue como preenchimento manual no checklist.
        // Quando a tabela real de transporte for identificada, é só reativar o cache
        // abaixo apontando para o novo método de query.
        return [];

        // return Cache::remember(
        //     "huddle_transporte_{$sectorId}",
        //     self::CACHE_TTL,
        //     fn () => $this->queryTransporte($attendanceNumbers)
        // );
    }

    /**
     * Status da orientação de alta por nr_atendimento (ORIENTACAO_ALTA_ENF).
     *
     * Confirmado com dados reais:
     *  - 'feita'    → registro ativo com DT_LIBERACAO preenchida
     *  - 'pendente' → registro ativo sem liberação (em andamento)
     *  - null       → sem registro de orientação
     *
     * @param  int[]  $attendanceNumbers
     * @return array<int, string|null>
     */
    public function orientacaoForSector(int $sectorId, array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        return Cache::remember(
            "huddle_orientacao_{$sectorId}",
            self::CACHE_TTL,
            fn () => $this->queryOrientacao($attendanceNumbers)
        );
    }

    /**
     * @param  int[]  $attendanceNumbers
     * @return array<int, string|null>
     */
    private function queryOrientacao(array $attendanceNumbers): array
    {
        $result = [];

        try {
            $placeholders = implode(',', array_fill(0, count($attendanceNumbers), '?'));

            $rows = DB::connection('tasy')->select(
                "SELECT nr_atendimento, dt_liberacao
                 FROM tasy.orientacao_alta_enf
                 WHERE ie_situacao = 'A' AND dt_inativacao IS NULL
                   AND nr_atendimento IN ({$placeholders})",
                $attendanceNumbers
            );

            foreach ($rows as $row) {
                $nr = (int) $row->nr_atendimento;
                $status = ! empty($row->dt_liberacao) ? 'feita' : 'pendente';

                // 'feita' prevalece se houver mais de um registro
                if (($result[$nr] ?? null) !== 'feita') {
                    $result[$nr] = $status;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[HuddleDischargeLoader] falha ao consultar orientação de alta', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @param  int[]  $attendanceNumbers
     * @return array<int, string|null>
     */
    private function queryTransporte(array $attendanceNumbers): array
    {
        $result = [];

        try {
            $placeholders = implode(',', array_fill(0, count($attendanceNumbers), '?'));

            $rows = DB::connection('tasy')->select(
                "SELECT nr_atendimento, ie_situacao, dt_liberacao, dt_inativacao
                 FROM tasy.formulario_transporte
                 WHERE nr_atendimento IN ({$placeholders})",
                $attendanceNumbers
            );

            foreach ($rows as $row) {
                $nr = (int) $row->nr_atendimento;

                // Ignora formulários inativados
                if (! empty($row->dt_inativacao) || ($row->ie_situacao ?? 'A') !== 'A') {
                    continue;
                }

                $status = ! empty($row->dt_liberacao) ? 'organizado' : 'pendente';

                // 'organizado' prevalece se houver mais de um formulário
                if (($result[$nr] ?? null) !== 'organizado') {
                    $result[$nr] = $status;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[HuddleDischargeLoader] falha ao consultar transporte', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
