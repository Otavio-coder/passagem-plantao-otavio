<?php

namespace App\Models\EMR\Core;

use App\Models\EMR\CPOE\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Sector extends Model
{
    private const ALLOWED_FOR_PREFERENCES_CACHE_KEY = 'allowed_sectors_for_preferences_v1';

    private const ALLOWED_FOR_PREFERENCES_CACHE_TTL = 3600;

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

    /**
     * Scope para filtrar apenas setores permitidos para o usuário atual
     * Baseado nas preferências do usuário em user_sector_preferences
     */
    public function scopeAllowed($query)
    {
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Obtém os códigos de setores preferidos do usuário
        $preferredSectorCodes = $user->sectorPreferences()
            ->pluck('sector_code')
            ->map(fn ($code) => (string) $code)
            ->toArray();

        if (empty($preferredSectorCodes)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($this->getTable().'.cd_setor_atendimento', $preferredSectorCodes);
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
            $appointments->each(fn ($a) => $a->setAttribute('has_surgery', false) && $a->setAttribute('procedimentos_cirurgicos', []));

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
            $surgeryDescription = null;

            if (! empty($a->nr_atendimento)) {
                try {
                    $result = DB::connection('tasy')->selectOne(
                        'SELECT TASY.OBTER_CIRURGIA_PACIENTE(:attendance_id, :ie_opcao) AS descricao FROM dual',
                        [
                            'attendance_id' => $a->nr_atendimento,
                            'ie_opcao' => 'AA',
                        ]
                    );

                    $descricao = trim((string) ($result->descricao ?? ''));
                    $surgeryDescription = $descricao !== '' ? $descricao : null;
                } catch (\Throwable) {
                    $surgeryDescription = null;
                }
            }

            $procedimentos = $surgeries->map(function (Appointment $s) use ($surgeryDescription) {
                $desc = $surgeryDescription ?? ($s->ds_cirurgia ?? 'Procedimento cirúrgico');
                $date = $s->dt_agenda ? Carbon::parse($s->dt_agenda)->format('d/m/Y') : '';
                $time = $s->hr_inicio ? Carbon::parse($s->hr_inicio)->format('H:i') : '00:00';
                $local = trim((string) ($s->ds_cirurgia ?? ''));
                $sala = trim((string) ($s->nr_seq_sala ?? ''));

                return [
                    'nr_sequencia' => $s->nr_sequencia,
                    'descricao' => $desc,
                    'descricao_padronizada' => $desc,
                    'procedimento' => $desc,
                    'data_agenda' => $date,
                    'hora_agenda' => $time,
                    'local' => $local !== '' ? $local : '-',
                    'sala' => $sala !== '' ? $sala : '-',
                    'summary' => trim("[Cir] {$desc} {$date} {$time}".($local !== '' ? " {$local}" : '').($sala !== '' ? " Sala {$sala}" : '')),
                ];
            })->values()->all();

            $a->setAttribute('has_surgery', count($procedimentos) > 0);
            $a->setAttribute('procedimentos_cirurgicos', $procedimentos);
        });

        return $appointments;
    }

    /**
     * Retorna setores permitidos para configuração de preferências.
     *
     * Estrutura de retorno:
     * [
     *   ['sector_code' => string, 'sector_name' => string,
     *    'hospital_code' => string, 'hospital_name' => string],
     *   ...
     * ]
     *
     * @return array<int, array{sector_code: string, sector_name: string, hospital_code: string, hospital_name: string}>
     */
    public static function allowedForPreferences(): array
    {
        return Cache::remember(self::ALLOWED_FOR_PREFERENCES_CACHE_KEY, self::ALLOWED_FOR_PREFERENCES_CACHE_TTL, function (): array {
            $allowedHospitalIds = array_map('intval', (array) config('hospitals.allowed_ids', []));

            if (empty($allowedHospitalIds)) {
                return [];
            }

            $hospitals = Hospital::query()
                ->whereIn('nr_sequencia', $allowedHospitalIds)
                ->where('ie_situacao', 'A')
                ->get()
                ->mapWithKeys(fn (Hospital $hospital) => [(string) $hospital->nr_sequencia => $hospital->ds_agrupamento]);

            $sectors = self::query()
                ->whereIn('nr_seq_agrupamento', $allowedHospitalIds)
                ->whereIn('cd_classif_setor', [1, 3, 4])
                ->where('ie_situacao', 'A')
                ->orderByRaw('NVL(ds_prescricao, ds_setor_atendimento)')
                ->get();

            if ($sectors->isEmpty()) {
                return [];
            }

            $codesWithBeds = Bed::query()
                ->whereIn('cd_setor_atendimento', $sectors->pluck('cd_setor_atendimento')->all())
                ->where('ie_situacao', 'A')
                ->distinct()
                ->pluck('cd_setor_atendimento')
                ->flip()
                ->toArray();

            return $sectors
                ->filter(fn (Sector $sector) => isset($codesWithBeds[$sector->cd_setor_atendimento]))
                ->map(fn (Sector $sector) => [
                    'sector_code' => (string) $sector->cd_setor_atendimento,
                    'sector_name' => (string) ($sector->ds_prescricao ?? $sector->ds_setor_atendimento),
                    'hospital_code' => (string) $sector->nr_seq_agrupamento,
                    'hospital_name' => (string) $hospitals->get((string) $sector->nr_seq_agrupamento, 'Hospital'),
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * @return array<string, array<int, array{sector_code: string, sector_name: string, hospital_code: string, hospital_name: string}>>
     */
    public static function allowedForPreferencesGroupedByHospital(): array
    {
        return collect(self::allowedForPreferences())
            ->groupBy('hospital_code')
            ->map(fn (Collection $rows) => $rows->values()->toArray())
            ->toArray();
    }
}
