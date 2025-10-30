<?php

namespace App\Models\EMR\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $connection = 'tasy';


    protected $table = 'TASY.MEDICO';

    protected $primaryKey = 'cd_pessoa_fisica';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $guarded = [];

    // Relationship: all patients where this professional is the responsible doctor
    public function patients(): HasMany
    {
        return $this->hasMany(\App\Models\EMR\Core\Patient::class, 'cd_medico_resp', 'cd_pessoa_fisica');
    }

    public function getIdAttribute()
    {
        return $this->cd_pessoa_fisica;
    }

    public function getNameAttribute()
    {
        return $this->nm_guerra ;
    }

}
