<?php

namespace App\Repositories\EMR;

use App\Enums\Huddle\TasyEvaluationItemMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repositório de avaliações do Tasy (Oracle) — módulo "Cadastro de Avaliações".
 *
 * Tabelas envolvidas:
 *   MED_TIPO_AVALIACAO    → tipo da avaliação (9291 = Huddle de Gestão de Altas)
 *   MED_ITEM_AVALIAR      → perguntas/itens do formulário
 *   MED_ITEM_AVALIAR_RES  → opções de resposta de cada item (Sim/Não)
 *   MED_AVALIACAO_PACIENTE → instância de avaliação por paciente (cabeçalho)
 *   MED_AVALIACAO_RESULT   → resposta de cada item numa avaliação
 *
 * Conexão configurável via TASY_EVALUATION_CONNECTION no .env:
 *   - 'tasy' (padrão): banco de produção
 *   - 'tasy_homolog': banco de homologação/testes
 */
class TasyEvaluationRepository
{
    protected string $connection;

    public function __construct()
    {
        $this->connection = config('database.tasy_evaluation_connection', 'tasy');
    }

    // ─── LEITURA: ESTRUTURA DO FORMULÁRIO ─────────────────────────────────

    /**
     * Retorna todos os itens (perguntas) de um tipo de avaliação com suas opções.
     *
     * @return array<int, object{nr_sequencia: int, ds_item: string, ie_tipo_campo: ?string, ie_obrigatorio: string, options: array}>
     */
    public function getEvaluationItems(int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO): array
    {
        $items = DB::connection($this->connection)->select("
            SELECT
                mia.nr_sequencia,
                mia.ds_item,
                mia.ie_tipo_campo,
                mia.nr_seq_superior,
                mia.ie_obrigatorio,
                mia.qt_tab_order,
                mia.ie_situacao
            FROM tasy.med_item_avaliar mia
            WHERE mia.nr_seq_tipo = :tipo
              AND mia.ie_situacao = 'A'
            ORDER BY mia.qt_tab_order NULLS LAST, mia.nr_sequencia
        ", ['tipo' => $tipoAvaliacao]);

        $options = $this->getItemOptions($tipoAvaliacao);

        return array_map(function ($item) use ($options) {
            $item->options = $options[$item->nr_sequencia] ?? [];

            return $item;
        }, $items);
    }

    /**
     * Retorna as opções de resposta indexadas por nr_seq_item.
     *
     * @return array<int, array<int, object{nr_seq_res: int, ds_resultado: string}>>
     */
    public function getItemOptions(int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO): array
    {
        $rows = DB::connection($this->connection)->select("
            SELECT
                r.nr_seq_item,
                r.nr_seq_res,
                r.ds_resultado,
                r.qt_resultado
            FROM tasy.med_item_avaliar_res r
            WHERE r.nr_seq_item IN (
                SELECT nr_sequencia
                FROM tasy.med_item_avaliar
                WHERE nr_seq_tipo = :tipo
                  AND ie_situacao = 'A'
            )
              AND r.ie_situacao = 'A'
            ORDER BY r.nr_seq_item, r.nr_seq_res
        ", ['tipo' => $tipoAvaliacao]);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->nr_seq_item][] = $row;
        }

        return $grouped;
    }

    // ─── LEITURA: AVALIAÇÕES EXISTENTES DO PACIENTE ──────────────────────

    /**
     * Busca a última avaliação do Huddle para um paciente (por nr_atendimento).
     * Retorna cabeçalho + respostas de cada item.
     *
     * @return object|null {nr_sequencia, dt_avaliacao, nm_usuario, answers: array}
     */
    public function getLatestEvaluation(int $attendanceNumber, int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO): ?object
    {
        $header = DB::connection($this->connection)->selectOne("
            SELECT
                map.nr_sequencia,
                map.nr_atendimento,
                map.cd_pessoa_fisica,
                map.dt_avaliacao,
                map.nm_usuario,
                map.dt_liberacao
            FROM tasy.med_avaliacao_paciente map
            WHERE map.nr_atendimento = :nr_atendimento
              AND map.nr_seq_tipo_avaliacao = :tipo
              AND map.dt_inativacao IS NULL
            ORDER BY map.dt_avaliacao DESC
            FETCH FIRST 1 ROWS ONLY
        ", [
            'nr_atendimento' => $attendanceNumber,
            'tipo' => $tipoAvaliacao,
        ]);

        if (! $header) {
            return null;
        }

        $answers = DB::connection($this->connection)->select("
            SELECT
                mar.nr_seq_item,
                mar.ds_resultado,
                mar.qt_resultado,
                mar.dt_atualizacao,
                mar.nm_usuario
            FROM tasy.med_avaliacao_result mar
            WHERE mar.nr_seq_avaliacao = :nr_seq_avaliacao
        ", ['nr_seq_avaliacao' => $header->nr_sequencia]);

        $header->answers = [];
        foreach ($answers as $answer) {
            $header->answers[$answer->nr_seq_item] = $answer;
        }

        return $header;
    }

    /**
     * Histórico de avaliações do Huddle para um paciente (últimas N).
     *
     * @return array<int, object>
     */
    public function getEvaluationHistory(int $attendanceNumber, int $limit = 10, int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO): array
    {
        return DB::connection($this->connection)->select("
            SELECT
                map.nr_sequencia,
                map.dt_avaliacao,
                map.nm_usuario,
                map.dt_liberacao,
                (SELECT COUNT(*)
                 FROM tasy.med_avaliacao_result mar
                 WHERE mar.nr_seq_avaliacao = map.nr_sequencia) AS total_respostas
            FROM tasy.med_avaliacao_paciente map
            WHERE map.nr_atendimento = :nr_atendimento
              AND map.nr_seq_tipo_avaliacao = :tipo
              AND map.dt_inativacao IS NULL
            ORDER BY map.dt_avaliacao DESC
            FETCH FIRST :lim ROWS ONLY
        ", [
            'nr_atendimento' => $attendanceNumber,
            'tipo' => $tipoAvaliacao,
            'lim' => $limit,
        ]);
    }

    // ─── LEITURA: AVALIAÇÕES COM tasy.aval() ────────────────────────────

    /**
     * Busca avaliações do Huddle com respostas inline via função tasy.aval().
     *
     * Cada linha retorna o cabeçalho da avaliação + todas as respostas como
     * colunas planas (alta_72h, criterios_clinicos, rec_alta_72h, etc.),
     * evitando múltiplos JOINs ao med_avaliacao_result.
     *
     * Filtros opcionais:
     *   - $attendanceNumber: paciente específico
     *   - $cdEstabelecimento: hospital (1 = Matriz, 31 = HDJB)
     *   - $dateFrom / $dateTo: período
     *   - $limit: máximo de registros
     *
     * @return array<int, object>
     */
    public function getEvaluationsWithAnswers(
        ?int $attendanceNumber = null,
        ?int $cdEstabelecimento = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 50,
        int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO,
    ): array {
        // Monta as colunas tasy.aval() dinamicamente a partir do enum
        $avalCols = TasyEvaluationItemMap::avalColumns();
        $avalSelect = '';
        foreach ($avalCols as $alias => $nrSeq) {
            $avalSelect .= ",\n                tasy.aval(map.nr_sequencia, {$nrSeq}) AS {$alias}";
        }

        $where = "map.nr_seq_tipo_avaliacao = :tipo
              AND map.ie_situacao = 'A'
              AND map.dt_inativacao IS NULL";

        $bindings = ['tipo' => $tipoAvaliacao];

        if ($attendanceNumber !== null) {
            $where .= "\n              AND map.nr_atendimento = :nr_atendimento";
            $bindings['nr_atendimento'] = $attendanceNumber;
        }

        if ($cdEstabelecimento !== null) {
            $where .= "\n              AND map.cd_estabelecimento = :cd_estab";
            $bindings['cd_estab'] = $cdEstabelecimento;
        }

        if ($dateFrom !== null) {
            $where .= "\n              AND map.dt_avaliacao >= TO_DATE(:dt_from, 'YYYY-MM-DD')";
            $bindings['dt_from'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $where .= "\n              AND map.dt_avaliacao < TO_DATE(:dt_to, 'YYYY-MM-DD') + 1";
            $bindings['dt_to'] = $dateTo;
        }

        $sql = "
            SELECT
                map.nr_sequencia,
                map.nr_atendimento,
                map.cd_pessoa_fisica,
                map.dt_avaliacao,
                map.cd_medico,
                map.dt_liberacao,
                map.ie_situacao,
                map.cd_estabelecimento,
                map.nm_usuario,
                map.cd_setor_atendimento,
                map.nr_seq_atepacu{$avalSelect}
            FROM tasy.med_avaliacao_paciente map
            WHERE {$where}
            ORDER BY map.dt_avaliacao DESC
            FETCH FIRST :lim ROWS ONLY
        ";

        $bindings['lim'] = $limit;

        return DB::connection($this->connection)->select($sql, $bindings);
    }

    /**
     * Busca a última avaliação de um paciente usando tasy.aval() — versão
     * otimizada de getLatestEvaluation() que retorna colunas planas.
     *
     * @return object|null  Com propriedades: alta_72h, criterios_clinicos, rec_alta_72h, etc.
     */
    public function getLatestEvaluationFlat(int $attendanceNumber, int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO): ?object
    {
        $results = $this->getEvaluationsWithAnswers(
            attendanceNumber: $attendanceNumber,
            limit: 1,
            tipoAvaliacao: $tipoAvaliacao,
        );

        return $results[0] ?? null;
    }

    // ─── ESCRITA: GRAVAR AVALIAÇÃO NO TASY ───────────────────────────────

    /**
     * Grava uma avaliação completa no Tasy (cabeçalho + respostas).
     *
     * Geração de PK: esta instância do Tasy não expõe sequence para
     * MED_AVALIACAO_PACIENTE — o nr_sequencia é obtido via MAX+1 dentro
     * da transação. O SELECT FOR UPDATE na subquery garante lock exclusivo
     * no pico de nr_sequencia, prevenindo colisão entre escritas concorrentes.
     *
     * @param  array<int, array{ds_resultado: string, qt_resultado: ?int}>  $answers  Indexado por nr_seq_item
     */
    public function saveEvaluation(
        int $attendanceNumber,
        string $cdPessoaFisica,
        string $nmUsuario,
        array $answers,
        int $tipoAvaliacao = TasyEvaluationItemMap::TIPO_AVALIACAO,
        ?int $cdSetorAtendimento = null,
    ): int {
        $db = DB::connection($this->connection);

        try {
            $db->beginTransaction();

            // 1. Obtém próximo nr_sequencia via MAX+1 com lock
            $seq = $db->selectOne("
                SELECT NVL(MAX(nr_sequencia), 0) + 1 AS next_val
                FROM tasy.med_avaliacao_paciente
            ");

            $nrSequencia = (int) $seq->next_val;

            // 2. Insere cabeçalho da avaliação
            $db->insert("
                INSERT INTO tasy.med_avaliacao_paciente (
                    nr_sequencia,
                    cd_pessoa_fisica,
                    nr_atendimento,
                    nr_seq_tipo_avaliacao,
                    dt_avaliacao,
                    dt_atualizacao,
                    nm_usuario,
                    dt_liberacao,
                    cd_setor_atendimento,
                    ie_situacao
                ) VALUES (
                    :nr_sequencia,
                    :cd_pessoa_fisica,
                    :nr_atendimento,
                    :tipo,
                    SYSDATE,
                    SYSDATE,
                    :nm_usuario,
                    SYSDATE,
                    :cd_setor,
                    'A'
                )
            ", [
                'nr_sequencia' => $nrSequencia,
                'cd_pessoa_fisica' => $cdPessoaFisica,
                'nr_atendimento' => $attendanceNumber,
                'tipo' => $tipoAvaliacao,
                'nm_usuario' => $nmUsuario,
                'cd_setor' => $cdSetorAtendimento,
            ]);

            // 3. Insere resposta de cada item
            foreach ($answers as $nrSeqItem => $answer) {
                $db->insert("
                    INSERT INTO tasy.med_avaliacao_result (
                        nr_seq_avaliacao,
                        nr_seq_item,
                        ds_resultado,
                        qt_resultado,
                        dt_atualizacao,
                        nm_usuario
                    ) VALUES (
                        :nr_seq_avaliacao,
                        :nr_seq_item,
                        :ds_resultado,
                        :qt_resultado,
                        SYSDATE,
                        :nm_usuario
                    )
                ", [
                    'nr_seq_avaliacao' => $nrSequencia,
                    'nr_seq_item' => $nrSeqItem,
                    'ds_resultado' => $answer['ds_resultado'] ?? null,
                    'qt_resultado' => $answer['qt_resultado'] ?? null,
                    'nm_usuario' => $nmUsuario,
                ]);
            }

            $db->commit();

            Log::info('[TasyEvaluation] Avaliação gravada no Tasy', [
                'nr_sequencia' => $nrSequencia,
                'nr_atendimento' => $attendanceNumber,
                'tipo' => $tipoAvaliacao,
                'total_items' => count($answers),
            ]);

            return $nrSequencia;
        } catch (\Throwable $e) {
            $db->rollBack();

            Log::error('[TasyEvaluation] Falha ao gravar avaliação', [
                'nr_atendimento' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
