<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'type', 'message', 'details'
    ];

    protected $casts = [
        'created_at' => 'datetime'
    ];

}
