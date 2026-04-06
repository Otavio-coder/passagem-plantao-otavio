<?php

namespace App\Services;

use App\Repositories\EMR\PatientAlertsRepository;
use App\Repositories\EMR\PatientClinicalRepository;
use App\Repositories\EMR\PatientMultidisciplinaryRepository;
use App\Repositories\EMR\PatientPrescricoesRepository;
use App\Repositories\EMR\PatientScalesRepository;
use App\Repositories\EMR\PatientSurgeryRepository;
use App\Repositories\EMR\PatientTherapeuticPlanRepository;
use App\Repositories\MySQL\UserRepository;

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

    /** Repository de usuários (MySQL) */
    public function users(): UserRepository
    {
        return $this->getRepo(UserRepository::class);
    }

    /** EMR repositories accessors */
    public function scales(): PatientScalesRepository
    {
        return $this->getRepo(PatientScalesRepository::class);
    }

    public function clinical(): PatientClinicalRepository
    {
        return $this->getRepo(PatientClinicalRepository::class);
    }

    public function prescricoes(): PatientPrescricoesRepository
    {
        return $this->getRepo(PatientPrescricoesRepository::class);
    }

    public function alerts(): PatientAlertsRepository
    {
        return $this->getRepo(PatientAlertsRepository::class);
    }

    public function surgery(): PatientSurgeryRepository
    {
        return $this->getRepo(PatientSurgeryRepository::class);
    }

    public function multidisciplinary(): PatientMultidisciplinaryRepository
    {
        return $this->getRepo(PatientMultidisciplinaryRepository::class);
    }

    public function therapeuticPlan(): PatientTherapeuticPlanRepository
    {
        return $this->getRepo(PatientTherapeuticPlanRepository::class);
    }
}
