<?php

namespace App\Models;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NurseHandoverBed extends Model
{
    protected $fillable = ['user_id', 'sector_id', 'bed_code'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
