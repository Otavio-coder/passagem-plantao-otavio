<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;

class MedicalOpinionResponse extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.PARECER_MEDICO';
    protected $primaryKey = ['nr_parecer', 'nr_sequencia'];
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
