<?php
namespace App\Models\EMR\Core;

use App\Models\EMR\CPOE\Appointment;
use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

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

    // Basic helpers
    public function getIdAttribute(): int
    {
        return (int) $this->nr_atendimento;
    }

    public function getPersonIdAttribute(): ?int
    {
        return $this->cd_pessoa_fisica ?? null;
    }

    // Core relations
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cd_pessoa_fisica', 'cd_pessoa_fisica');
    }

    public function bed(): HasOne
    {
        return $this->hasOne(Bed::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'cd_medico_resp', 'cd_pessoa_fisica');
    }

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'cd_medico_atendimento', 'cd_pessoa_fisica');
    }

    public function motivoAlta(): BelongsTo
    {
        return $this->belongsTo(DischargeReason::class, 'cd_motivo_alta', 'cd_motivo_alta');
    }

    public function motivoAltaMedica(): BelongsTo
    {
        return $this->belongsTo(MedicalDischargeReason::class, 'cd_motivo_alta_medica', 'cd_motivo_alta_medica');
    }

    // CPOE relations
    public function prescriptions(): HasMany
    {
        return $this->hasMany(\App\Models\EMR\CPOE\Prescription::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function hemotherapies(): HasMany
    {
        return $this->hasMany(\App\Models\EMR\CPOE\Hemotherapy::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'nr_atendimento', 'nr_atendimento');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'cd_setor_atendimento', 'cd_setor_atendimento');
    }

    /**
     * Helper para detectar paciente pediátrico
     */
    public function isPediatric(): bool
    {
        $dob = $this->person?->dt_nascimento ?? null;
        if (!$dob) return false;

        try {
            return Carbon::parse($dob)->age < 18;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ✅ Busca dados completos do paciente SEM CPOE
     * Delega para TasyService
     */
    public function getFullPatientDataWithoutCPOE(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) {
            return null;
        }

        // Usa o método do TasyService que já existe
        return $this->tasyService->getPatientBasicData($attendanceNumber);
    }

    /**
     * ✅ Busca APENAS dados CPOE
     * Delega para TasyService
     */
    public function getPatientCPOEOnly(int $attendanceNumber): ?object
    {
        if (!$attendanceNumber) {
            return null;
        }

        // Usa o método do TasyService que já existe
        return $this->tasyService->getPatientCPOEData($attendanceNumber);
    }

    /**
     * Limpa cache do paciente (incluindo CPOE)
     */
    public function clearPatientCache(int $attendanceNumber): void
    {
        // Delega para TasyService que já tem essa lógica
        $this->tasyService->clearPatientCache($attendanceNumber);

        // Adiciona limpeza de cache específico do modal
        $additionalKeys = [
            "patient_basic_modal_{$attendanceNumber}",
            "patient_cpoe_modal_{$attendanceNumber}",
            "patient_full_data_without_cpoe_{$attendanceNumber}",
        ];

        foreach ($additionalKeys as $key) {
            Cache::forget($key);
        }
    }
}
