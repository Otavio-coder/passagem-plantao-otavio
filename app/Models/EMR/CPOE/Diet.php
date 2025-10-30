<?php

namespace App\Models\EMR\CPOE;

use Illuminate\Database\Eloquent\Model;

class Diet extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.DIETA';
    protected $primaryKey = 'cd_dieta';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
