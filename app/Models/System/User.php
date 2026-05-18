<?php

namespace App\Models\System;

use App\Models\NurseHandoverBed;
use App\Services\MSGraph\GetUserPhoto;
use App\Services\MSGraph\GetUserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\HasLdapUser;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @method \Illuminate\Support\Collection getRoleNames()
 * @method string getUserPhoto(string $size = "64x64")
 * @method string getUserRole(bool $forceRefresh = false)
 */
class User extends Authenticatable implements LdapAuthenticatable
{
    use AuthenticatesWithLdap, GetUserPhoto, GetUserRole, HasFactory, HasLdapUser, HasRoles, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'status',
        'last_access_at',
        'password',
        'guid',
        'domain',
        'photo',
        'role',
        'role_synced_at',
        'is_nurse',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_access_at' => 'datetime',
            'role_synced_at' => 'datetime',
            'is_nurse' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getStatusNameAttribute(): string
    {

        $status = [
            'A' => 'Ativo',
            'I' => 'Inativo',
        ];

        return $status[$this->status];
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status === 'A' ? 'teal' : 'red';
    }

    public function getDisplayPhotoAttribute(): ?string
    {
        return $this->hasValidPhoto() ? $this->photo : null;
    }

    public function getAvatarInitialsAttribute(): string
    {
        $words = preg_split('/[\s.]+/', trim($this->name ?: 'U')) ?: ['U'];
        $initials = strtoupper(substr((string) ($words[0] ?? 'U'), 0, 1));

        if (count($words) > 1) {
            $initials .= strtoupper(substr((string) end($words), 0, 1));
        }

        return $initials;
    }

    public function getAvatarBgColorAttribute(): string
    {
        $palette = ['4F46E5', '0891B2', '059669', 'B45309', '7C3AED', '0284C7', 'BE185D', '0F766E'];

        return '#'.$palette[abs(crc32($this->name ?: 'U')) % count($palette)];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getSectorsByHospitalAttribute(): array
    {
        return $this->sectorPreferences
            ->groupBy('hospital_name')
            ->map(fn ($preferences) => $preferences->pluck('sector_name')->filter()->unique()->values()->all())
            ->toArray();
    }

    public function getHasSectorsAttribute(): bool
    {
        return ! empty($this->sectors_by_hospital);
    }

    /**
     * Check if user has a valid photo (not error data)
     */
    public function hasValidPhoto(): bool
    {
        if (empty($this->photo)) {
            return false;
        }

        // Check if the photo data contains error information
        $decoded = base64_decode($this->photo, true);
        if ($decoded === false) {
            return false;
        }

        // Check if it's JSON error data from MS Graph
        $jsonData = json_decode($decoded, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['error'])) {
            return false;
        }

        // Additional check for error strings in the data
        if (strpos($decoded, '"error":{') !== false || strpos($decoded, 'ErrorBadRequestInvalidTargetIdentity') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Get user photo - wrapper method to use trait or return stored photo
     */
    public function getUserPhoto($size = '64x64')
    {
        // Se já tem foto válida no banco, retorna
        if ($this->hasValidPhoto()) {
            return $this->getOriginal('photo');
        }

        // Usa o trait para buscar nova foto
        return $this->photo($size);
    }

    public function getUserRole($forceRefresh = false): string
    {
        return $this->role($forceRefresh);
    }

    public function getDisplayNameWithRoleAttribute(): string
    {
        $name = trim((string) ($this->name ?: 'Usuário'));
        $role = trim($this->getUserRole());

        return $role !== '' ? $name.' - '.$role : $name;
    }

    /**
     * Check if user has a photo
     */
    public function hasPhoto(): bool
    {
        return $this->hasValidPhoto();
    }

    /**
     * Get photo for display in templates
     */
    public function getPhotoAttribute($value)
    {
        return $value;
    }

    public function sectorPreferences(): HasMany
    {
        return $this->hasMany(UserSectorPreference::class, 'user_id');
    }

    public function hasConfiguredSectors(): bool
    {
        return $this->sectorPreferences()->exists();
    }

    public function isNurse(): bool
    {
        return (bool) $this->is_nurse
            || $this->hasRole('Administrador')
            || $this->hasRole('Enfermeiro');
    }

    /** @return HasMany<NurseHandoverBed> */
    public function handoverBeds(): HasMany
    {
        return $this->hasMany(NurseHandoverBed::class);
    }

    /** @return array<string> bed_codes for a given sector */
    public function handoverBedCodesForSector(int $sectorId): array
    {
        return $this->handoverBeds()
            ->where('sector_id', $sectorId)
            ->pluck('bed_code')
            ->all();
    }
}
