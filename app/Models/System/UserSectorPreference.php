<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSectorPreference extends Model
{
    protected $table = 'user_sector_preferences';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'sector_code',
        'sector_name',
        'hospital_code',
        'hospital_name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
