<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.MATERIAL';
    protected $primaryKey = 'cd_material';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
