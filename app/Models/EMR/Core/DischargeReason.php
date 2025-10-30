<?php

namespace App\Models\EMR\Core;

use Illuminate\Database\Eloquent\Model;

class DischargeReason extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.MOTIVO_ALTA';
    protected $primaryKey = 'cd_motivo_alta';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public function getIdAttribute()
    {
        return $this->cd_motivo_alta ?? null;
    }

    public function getNameAttribute()
    {
        return $this->ds_motivo_alta ?? $this->no_motivo_alta ?? null;
    }
}

