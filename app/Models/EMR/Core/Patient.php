<?php

namespace App\Models\EMR\Core;

use App\Models\EMR\CPOE\Appointment;
use App\Services\Tasy\TasyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    protected $connection = 'tasy';

    protected $table = 'TASY.ATENDIMENTO_PACIENTE';

    protected $primaryKey = 'nr_atendimento';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected TasyService $tasyService;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->tasyService = app(TasyService::class);
    }

    public function getIdAttribute(): int
    {
        return (int) $this->nr_atendimento;
    }

    public function getPersonIdAttribute(): ?int
    {
        return $this->cd_pessoa_fisica ?? null;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_pessoa_fisica', 'cd_pessoa_fisica');
    }

    public function bed(): HasOne
    {
        return $this->hasOne(Bed::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'cd_setor_atendimento', 'cd_setor_atendimento');
    }

    public function isPediatric(): bool
    {
        $dob = $this->person?->dt_nascimento ?? null;
        if (! $dob) {
            return false;
        }

        try {
            return Carbon::parse($dob)->age < 18;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getFullPatientDataWithoutCPOE(int $attendanceNumber): ?object
    {
        if (! $attendanceNumber) {
            return null;
        }

        return $this->tasyService->getSbarPatientDetails($attendanceNumber);
    }

    public function clearPatientCache(int $attendanceNumber): void
    {
        $this->tasyService->clearPatientCache($attendanceNumber);
    }
}
