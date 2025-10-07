<?php

namespace App\Services;

use App\Repositories\MySQL\UserRepository;
use App\Repositories\EMR\PatientScales;
use App\Repositories\EMR\PatientClinical;
use App\Repositories\EMR\PatientCPOE;

trait UsesRepositories
{
    /**
     * Metodo utilizado apenas para deixar a sintaxe mais legível
     *
     * @return $this
     */
    public function repository()
    {
        return $this;
    }

    public function users()
    {
        return new UserRepository();
    }

    public function scales()
    {
        return new PatientScales();
    }

    public function clinical()
    {
        return new PatientClinical();
    }

    public function cpoe()
    {
        return new PatientCPOE();
    }
}
