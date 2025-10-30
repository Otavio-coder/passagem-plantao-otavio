<?php

namespace App\Models\EMR\CPOE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Model para PRESCR_PROCEDIMENTO (Procedimentos Prescritos - CPOE)
 *
 * Esta tabela armazena procedimentos prescritos durante a internação:
 * - Curativos
 * - Coletas de exames
 * - Fisioterapia
 * - Glicemia capilar
 * - Gasometria
 * - Procedimentos de enfermagem
 * - Etc
 *
 * DIFERENTE de AGENDA_PACIENTE (que são agendamentos futuros de cirurgias/exames)
 */
class Procedure extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PRESCR_PROCEDIMENTO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_prev_execucao' => 'datetime',
        'dt_baixa' => 'datetime',
        'dt_atualizacao' => 'datetime',
        'dt_coleta' => 'datetime',
        'dt_suspensao' => 'datetime',
        'dt_resultado' => 'datetime',
        'dt_inicio_exame' => 'datetime',
        'dt_emissao_resultado' => 'datetime',
        'dt_inicio' => 'datetime',
        'dt_fim' => 'datetime',
        'dt_executada' => 'datetime',
    ];

    // ========== RELAÇÕES ==========

    /**
     * Prescrição médica pai
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'nr_prescricao', 'nr_prescricao');
    }

    // ========== SCOPES - FILTROS BÁSICOS ==========

    /**
     * Procedimentos de hoje
     */
    public function scopeToday($query)
    {
        return $query->whereDate('dt_prev_execucao', Carbon::today());
    }

    /**
     * Procedimentos pendentes (não realizados)
     */
    public function scopePending($query)
    {
        return $query->whereNull('dt_baixa');
    }

    /**
     * Procedimentos realizados
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('dt_baixa');
    }

    /**
     * Procedimentos não suspensos
     */
    public function scopeNotSuspended($query)
    {
        return $query->where(function($q) {
            $q->whereNull('ie_suspenso')
                ->orWhere('ie_suspenso', '!=', 'S');
        });
    }

    /**
     * Próximos procedimentos (próximas X horas)
     */
    public function scopeUpcoming($query, $hours = 12)
    {
        $now = Carbon::now();
        return $query->whereBetween('dt_prev_execucao', [$now, $now->copy()->addHours($hours)])
            ->whereNull('dt_baixa')
            ->whereNotIn('nr_seq_proc_interno', [1341, 5970]); // Excluir procedimentos específicos
    }

    // ========== SCOPES - TIPOS DE PROCEDIMENTO ==========

    /**
     * Procedimentos de coleta de exame laboratorial
     */
    public function scopeLabCollections($query)
    {
        return $query->where('ie_origem_proced', 4); // Laboratório
    }

    /**
     * Procedimentos de imagem
     */
    public function scopeImageProcedures($query)
    {
        return $query->whereIn('ie_origem_proced', [1, 2, 3]); // Imagem, Ressonância, Ultra
    }

    /**
     * Exames prioritários pendentes
     */
    public function scopePriorityExams($query)
    {
        return $query->where('ie_status_execucao', '10')
            ->whereNull('dt_coleta')
            ->where('ie_origem_proced', '!=', 4);
    }

    /**
     * Procedimentos urgentes
     */
    public function scopeUrgent($query)
    {
        return $query->where('ie_urgencia', 'S');
    }

    /**
     * Procedimentos "se necessário"
     */
    public function scopeIfNeeded($query)
    {
        return $query->where('ie_se_necessario', 'S');
    }

    /**
     * Procedimentos ACM (Alta Complexidade)
     */
    public function scopeAcm($query)
    {
        return $query->where('ie_acm', 'S');
    }

    /**
     * Procedimentos externos
     */
    public function scopeExternal($query)
    {
        return $query->where('ie_externo', 'S');
    }

    // ========== SCOPES - STATUS ==========

    /**
     * Procedimentos com status específico
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('ie_status_execucao', $status);
    }

    /**
     * Procedimentos agendados
     */
    public function scopeScheduled($query)
    {
        return $query->where('ie_status_execucao', '10');
    }

    /**
     * Procedimentos em execução
     */
    public function scopeInProgress($query)
    {
        return $query->where('ie_status_execucao', '20');
    }

    /**
     * Procedimentos finalizados
     */
    public function scopeFinished($query)
    {
        return $query->where('ie_status_execucao', '30');
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Verifica se o procedimento foi realizado
     */
    public function isCompleted(): bool
    {
        return !is_null($this->dt_baixa);
    }

    /**
     * Verifica se está suspenso
     */
    public function isSuspended(): bool
    {
        return $this->ie_suspenso === 'S';
    }

    /**
     * Verifica se é urgente
     */
    public function isUrgent(): bool
    {
        return $this->ie_urgencia === 'S';
    }

    /**
     * Verifica se é "se necessário"
     */
    public function isIfNeeded(): bool
    {
        return $this->ie_se_necessario === 'S';
    }

    /**
     * Verifica se é ACM
     */
    public function isAcm(): bool
    {
        return $this->ie_acm === 'S';
    }

    /**
     * Verifica se é externo
     */
    public function isExternal(): bool
    {
        return $this->ie_externo === 'S';
    }

    /**
     * Verifica se é exame de laboratório
     */
    public function isLabExam(): bool
    {
        return $this->ie_origem_proced == 4;
    }

    /**
     * Verifica se é exame de imagem
     */
    public function isImageExam(): bool
    {
        return in_array($this->ie_origem_proced, [1, 2, 3]);
    }

    /**
     * Retorna descrição do procedimento via função TASY
     */
    public function getDescriptionAttribute(): string
    {
        if (empty($this->nr_seq_proc_interno)) {
            return 'Procedimento não identificado';
        }

        try {
            $result = DB::connection('tasy')->selectOne(
                "SELECT TASY.OBTER_DESC_PROC_INTERNO(:seq) AS descricao FROM dual",
                ['seq' => $this->nr_seq_proc_interno]
            );
            return $result->descricao ?? 'Procedimento não identificado';
        } catch (\Throwable) {
            return 'Procedimento não identificado';
        }
    }

    /**
     * Retorna turno do procedimento (M/T/N)
     * Manhã: 07:00-12:59
     * Tarde: 13:00-18:59
     * Noite: 19:00-06:59
     */
    public function getShiftAttribute(): string
    {
        if (!$this->dt_prev_execucao) {
            return 'N';
        }

        $hour = Carbon::parse($this->dt_prev_execucao)->hour;

        if ($hour >= 7 && $hour < 13) {
            return 'M';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'T';
        }

        return 'N';  // 19:00-06:59 = Noite
    }

    /**
     * Retorna data/hora prevista formatada
     */
    public function getScheduledDateTimeFormattedAttribute(): string
    {
        return $this->dt_prev_execucao
            ? Carbon::parse($this->dt_prev_execucao)->format('d/m/Y H:i')
            : 'Não informado';
    }

    /**
     * Retorna status de execução formatado
     */
    public function getExecutionStatusDescriptionAttribute(): string
    {
        return match($this->ie_status_execucao) {
            '10' => 'Agendado',
            '20' => 'Em Execução',
            '30' => 'Finalizado',
            '40' => 'Cancelado',
            '50' => 'Suspenso',
            default => 'Status desconhecido'
        };
    }

    /**
     * Retorna tipo de procedimento via domínio TASY
     */
    public function getProcedureTypeAttribute(): ?string
    {
        if (empty($this->ie_origem_proced)) {
            return null;
        }

        try {
            $result = DB::connection('tasy')->selectOne(
                "SELECT TASY.OBTER_VALOR_DOMINIO(815, :tipo) AS descricao FROM dual",
                ['tipo' => $this->ie_origem_proced]
            );
            return $result->descricao ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retorna descrição do intervalo (cd_intervalo) via domínio TASY
     */
    public function getIntervalDescriptionAttribute(): ?string
    {
        if (empty($this->cd_intervalo)) {
            return null;
        }

        try {
            $result = DB::connection('tasy')->selectOne(
                "SELECT TASY.OBTER_VALOR_DOMINIO(816, :intervalo) AS descricao FROM dual",
                ['intervalo' => $this->cd_intervalo]
            );
            return $result->descricao ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Retorna labels do procedimento
     */
    public function getLabelsAttribute(): array
    {
        $labels = [];

        if ($this->isUrgent()) {
            $labels[] = 'Urgente';
        }

        if ($this->isIfNeeded()) {
            $labels[] = 'Se Necessário';
        }

        if ($this->isAcm()) {
            $labels[] = 'ACM';
        }

        if ($this->isExternal()) {
            $labels[] = 'Externo';
        }

        if ($this->ie_lado) {
            $lado_map = ['D' => 'Direito', 'E' => 'Esquerdo', 'B' => 'Bilateral'];
            $labels[] = 'Lado: ' . ($lado_map[$this->ie_lado] ?? $this->ie_lado);
        }

        return $labels;
    }

    // ========== MÉTODOS ESTÁTICOS ==========

    public static function getPatientCpoeProcedures(int $attendanceNumber): array
    {
        $rows = static::query()
            ->from('tasy.prescr_procedimento as pp')
            ->join('tasy.prescr_medica as pm', 'pm.nr_prescricao', '=', 'pp.nr_prescricao')
            ->selectRaw(implode(',', [
                'pm.nr_atendimento',
                'pp.nr_prescricao',
                'pp.nr_seq_proc_interno',
                "TASY.OBTER_DESC_PROC_INTERNO(pp.nr_seq_proc_interno) AS ds_procedimento",
                'pp.dt_prev_execucao',
                'pp.dt_baixa',
                "TO_CHAR(pp.dt_prev_execucao, 'DD/MM/YY') AS dt_prev_execucao_fmt",
                "TO_CHAR(pp.dt_prev_execucao, 'HH24:MI') AS hr_prev_execucao",
                "CASE
                WHEN TO_NUMBER(SUBSTR(TO_CHAR(pp.dt_prev_execucao, 'HH24:MI'), 1, 2)) BETWEEN 7 AND 13 THEN 'MANHÃ'
                WHEN TO_NUMBER(SUBSTR(TO_CHAR(pp.dt_prev_execucao, 'HH24:MI'), 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                ELSE 'NOITE'
             END AS turno",
                'pm.dt_liberacao'
            ]))
            ->where('pm.nr_atendimento', $attendanceNumber)
            ->whereRaw('TRUNC(pp.dt_prev_execucao) = TRUNC(SYSDATE)')
            ->whereNotNull('pm.dt_liberacao')
            ->orderByRaw('pp.dt_prev_execucao')
            ->get();

        $total = $rows->count();
        $grouped = $rows->groupBy('turno');

        $result = ['total_count' => $total, 'shifts' => []];
        foreach (['MANHÃ','TARDE','NOITE'] as $shift) {
            $items = $grouped->get($shift, collect())->map(fn($it) => [
                'procedimento' => $it->ds_procedimento ?? 'Procedimento não identificado',
                'data_prevista' => $it->dt_prev_execucao_fmt ?? '',
                'horario' => $it->hr_prev_execucao ?? '',
                'turno' => $it->turno,
                'is_completed' => !empty($it->dt_baixa),
                'has_details' => true,
            ])->toArray();

            $result['shifts'][$shift] = ['count' => count($items), 'procedures' => $items];
        }

        return $result;
    }
}
