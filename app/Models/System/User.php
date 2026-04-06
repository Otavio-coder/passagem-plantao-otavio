<?php

namespace App\Models\System;

use App\Services\MSGraph\GetUserPhoto;
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
 */
class User extends Authenticatable implements LdapAuthenticatable
{
    use AuthenticatesWithLdap, GetUserPhoto, HasFactory, HasLdapUser, HasRoles, Notifiable;

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
}
