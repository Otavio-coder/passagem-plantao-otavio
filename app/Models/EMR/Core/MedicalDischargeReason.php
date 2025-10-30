<?php

namespace App\Models\EMR\Core;

use Illuminate\Database\Eloquent\Model;

class MedicalDischargeReason extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.MOTIVO_ALTA_MEDICA';
    protected $primaryKey = 'cd_motivo_alta_medica';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    public function getDescriptionAttribute(): ?string
    {
        return $this->ds_motivo_alta ?? null;
    }
}
