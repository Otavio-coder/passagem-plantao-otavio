<?php

namespace App\Services;

use App\Repositories\MySQL\UserRepository;

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
}
