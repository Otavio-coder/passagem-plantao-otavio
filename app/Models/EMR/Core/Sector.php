<?php

namespace App\Models\EMR\Core;

use App\Models\EMR\CPOE\Appointment;
use App\Models\System\SystemConfiguration;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class Sector extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.SETOR_ATENDIMENTO';
    protected $primaryKey = 'cd_setor_atendimento';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'nr_seq_agrupamento', 'nr_sequencia');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class, 'cd_setor_atendimento', 'cd_setor_atendimento');
    }

    public function scopeAllowed($query)
    {
        $codes = SystemConfiguration::allowedSectorCodes();
        if (empty($codes)) {
            return $query->whereRaw('1 = 0');
        }

        $codes = array_values(array_filter(array_map('strval', $codes), fn($v) => $v !== ''));

        return $query->whereIn($this->getTable() . '.cd_setor_atendimento', $codes);
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            Patient::class,
            'cd_setor_atendimento',
            'nr_atendimento',
            'cd_setor_atendimento',
            'nr_atendimento'
        );
    }

    public function futureAppointmentsWithSurgeryDetails(): Collection
    {
        // 1) Load future, not-cancelled appointments with patient and bed eager loaded
        $appointments = $this->appointments()
            ->future()
            ->notCancelled()
            ->with(['patient.person', 'patient.bed'])
            ->get();

        // 2) Collect distinct person ids present in these appointments
        $personIds = $appointments->pluck('cd_pessoa_fisica')->filter()->unique()->values()->all();
        if (empty($personIds)) {
            $appointments->each(fn($a) => $a->setAttribute('has_surgery', false) && $a->setAttribute('procedimentos_cirurgicos', []));
            return $appointments;
        }

        // 3) Batch-load future surgeries for those persons but only up to 1 month ahead
        $cutoff = Carbon::now()->addMonth();

        $appointmentBuilder = Appointment::query();
        $surgeryMap = [];

        if (method_exists($appointmentBuilder, 'chaperone')) {
            $loaded = $appointmentBuilder->chaperone('cd_pessoa_fisica', function ($q, array $ids) use ($cutoff) {
                return Appointment::surgeries()
                    ->whereIn('cd_pessoa_fisica', $ids)
                    ->where('dt_agenda', '>=', Carbon::today())
                    ->where('dt_agenda', '<=', $cutoff)
                    ->whereNull('dt_executada')
                    ->orderBy('dt_agenda')
                    ->orderBy('hr_inicio')
                    ->get();
            });

            foreach ($loaded as $personId => $items) {
                $surgeryMap[$personId] = collect($items);
            }
        } else {
            $surgeries = Appointment::surgeries()
                ->whereIn('cd_pessoa_fisica', $personIds)
                ->where('dt_agenda', '>=', Carbon::today())
                ->where('dt_agenda', '<=', $cutoff)
                ->whereNull('dt_executada')
                ->orderBy('dt_agenda')
                ->orderBy('hr_inicio')
                ->get()
                ->groupBy('cd_pessoa_fisica');

            foreach ($surgeries as $personId => $group) {
                $surgeryMap[$personId] = $group;
            }
        }

        // 4) Build per-person summaries and attach flags to appointments
        $appointments->each(function (Appointment $a) use ($surgeryMap) {
            $personId = $a->cd_pessoa_fisica;
            $surgeries = $surgeryMap[$personId] ?? collect();

            $procedimentos = $surgeries->map(function (Appointment $s) {
                $desc = $s->getProcedureDescriptionAttribute() ?? ($s->ds_cirurgia ?? 'Procedimento cirúrgico');
                $date = $s->dt_agenda ? Carbon::parse($s->dt_agenda)->format('d/m/Y') : '';
                $time = $s->hr_inicio ? Carbon::parse($s->hr_inicio)->format('H:i') : '00:00';
                return [
                    'nr_sequencia' => $s->nr_sequencia,
                    'descricao' => $desc,
                    'data_agenda' => $date,
                    'hora_agenda' => $time,
                    'summary' => "[Cir] {$desc} {$date} {$time}",
                ];
            })->values()->all();

            $a->setAttribute('has_surgery', count($procedimentos) > 0);
            $a->setAttribute('procedimentos_cirurgicos', $procedimentos);
        });

        return $appointments;
    }
}
