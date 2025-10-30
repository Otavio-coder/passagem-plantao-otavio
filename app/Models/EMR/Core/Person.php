<?php

namespace App\Models\EMR\Core;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $connection = 'tasy';
    // TASY schema-qualified table name
    protected $table = 'TASY.PESSOA_FISICA';
    protected $primaryKey = 'cd_pessoa_fisica';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'dt_nascimento' => 'datetime',
    ];

    // Common accessors to normalize fields used by PatientCoreRepository
    public function getNameAttribute()
    {
        // TASY variants seen in this installation: nm_pessoa_fisica, no_pessoa, no_pessoa_fisica
        return $this->nm_pessoa_fisica ?? $this->no_pessoa ?? $this->no_pessoa_fisica ?? null;
    }

    public function getBirthDateAttribute()
    {
        return $this->dt_nascimento ?? null;
    }

    public function getSexAttribute()
    {
        return $this->ie_sexo ?? null;
    }

    /**
     * Patient record number (prontuário) if present on pessoa_fisica
     */
    public function getRecordNumberAttribute()
    {
        return $this->nr_prontuario ?? null;
    }
}
