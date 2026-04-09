<?php

namespace App\Models\EMR\CPOE;

use App\Models\EMR\Core\Patient;
use App\Models\EMR\Core\Person;
use App\Models\EMR\Core\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Appointment extends Model
{
    protected $connection = 'tasy';

    protected $table = 'TASY.AGENDA_PACIENTE';

    protected $primaryKey = 'nr_sequencia';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'dt_agenda' => 'date',
        'hr_inicio' => 'datetime',
        'dt_atualizacao' => 'datetime',
        'dt_agendamento' => 'datetime',
        'dt_confirmacao' => 'datetime',
        'dt_nascimento_pac' => 'date',
        'dt_chegada' => 'datetime',
        'dt_atendimento' => 'datetime',
        'dt_cancelamento' => 'datetime',
        'dt_bloqueio' => 'datetime',
        'dt_executada' => 'datetime',
        'dt_em_exame' => 'datetime',
        'dt_atendido' => 'datetime',
    ];

    // ========== RELAÇÕES ==========

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_pessoa_fisica', 'cd_pessoa_fisica');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function getSectorAttribute(): ?Sector
    {
        return $this->patient?->sector;
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_medico', 'cd_pessoa_fisica');
    }

    // ========== MÉTODOS AUXILIARES ==========

    public function isSurgery(): bool
    {
        return ! is_null($this->ie_carater_cirurgia)
            && $this->ie_carater_cirurgia !== 'X';
    }

    public function isExam(): bool
    {
        return $this->ie_origem_proced != 5;
    }

    public function getSurgeryCharacterAttribute(): ?string
    {
        if (! $this->isSurgery()) {
            return null;
        }

        return match ($this->ie_carater_cirurgia) {
            'A' => 'Ambulatorial',
            'E' => 'Eletiva',
            'M' => 'Emergência',
            'U' => 'Urgência',
            default => trim((string) ($this->ie_carater_cirurgia ?? '')) !== '' ? $this->ie_carater_cirurgia : 'Não informado',
        };
    }

    public function getProcedureDescriptionAttribute(): string
    {
        if ($this->isSurgery()) {
            try {
                $option = $this->dt_executada ? 'P' : 'AA';
                $result = DB::connection('tasy')->selectOne(
                    'SELECT TASY.OBTER_CIRURGIA_PACIENTE(:attendance_id, :ie_opcao) AS descricao FROM dual',
                    [
                        'attendance_id' => $this->nr_atendimento,
                        'ie_opcao' => $option,
                    ]
                );

                return $result->descricao ?? ($this->ds_cirurgia ?? 'Procedimento cirúrgico');
            } catch (\Throwable) {
                return $this->ds_cirurgia ?? 'Procedimento cirúrgico';
            }
        }

        if ($this->isExam() && ! empty($this->cd_procedimento) && ! empty($this->ie_origem_proced)) {
            try {
                $result = DB::connection('tasy')->selectOne(
                    'SELECT TASY.OBTER_EXAME_AGENDA(:cd_proc, :origem) AS descricao FROM dual',
                    [
                        'cd_proc' => $this->cd_procedimento,
                        'origem' => $this->ie_origem_proced,
                    ]
                );

                return $result->descricao ?? 'Exame não especificado';
            } catch (\Throwable) {
                return 'Exame não especificado';
            }
        }

        return $this->ds_cirurgia ?? 'Procedimento não especificado';
    }

    /**
     * Query scope to filter only surgery appointments.
     *
     * Usage:
     *   Appointment::surgeries()->where(...)->get();
     */
    public function scopeSurgeries($query)
    {
        return $query->whereNotNull('ie_carater_cirurgia')
            ->where('ie_carater_cirurgia', '<>', 'X');
    }

    /**
     * Static helper returning a query builder, allowing Appointment::surgeries()
     * to be called as in the repository.
     */
    public static function surgeries()
    {
        return (new static)->newQuery()->surgeries();
    }
}
