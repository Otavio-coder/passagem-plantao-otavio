<?php

namespace App\Services;

use App\Repositories\MySQL\UserRepository;
use App\Repositories\EMR\{
    PatientCoreRepository,
    PatientClinicalRepository,
    PatientCPOERepository,
    PatientScalesRepository
};

trait UsesRepositories
{
    /**
     * Local cache for resolved repository instances.
     * Avoids repeated container resolution overhead.
     *
     * @var array<string, mixed>
     */
    protected array $resolvedRepos = [];

    private function getRepo(string $class)
    {
        return $this->resolvedRepos[$class] ??= app($class);
    }

    /**
     * Método utilizado apenas para deixar a sintaxe mais legível
     *
     * @return $this
     */
    public function repository()
    {
        return $this;
    }

    /**
     * Repository de usuários (MySQL)
     */
    public function users(): UserRepository
    {
        return $this->getRepo(UserRepository::class);
    }

    /**
     * EMR repositories accessors
     */
    public function scales(): PatientScalesRepository
    {
        return $this->getRepo(PatientScalesRepository::class);
    }

    public function clinical(): PatientClinicalRepository
    {
        return $this->getRepo(PatientClinicalRepository::class);
    }

    public function cpoe(): PatientCPOERepository
    {
        return $this->getRepo(PatientCPOERepository::class);
    }

}
