<?php

namespace App\Models\EMR\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.AGRUPAMENTO_SETOR';
    protected $primaryKey = 'nr_sequencia';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    public function sectors(): HasMany
    {
        return $this->hasMany(\App\Models\EMR\Core\Sector::class, 'nr_seq_agrupamento', 'nr_sequencia');
    }

}
