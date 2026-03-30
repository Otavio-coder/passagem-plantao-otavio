<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;
use App\Repositories\EMR\{
    PatientAlertsRepository,
    PatientClinicalRepository,
    PatientExamsRepository,
    PatientMultidisciplinaryRepository,
    PatientPrescricoesRepository,
    PatientScalesRepository,
    PatientSurgeryRepository,
    PatientTherapeuticPlanRepository
};

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\TasyService::class);

        $this->app->bind(PatientScalesRepository::class, PatientScalesRepository::class);
        $this->app->bind(PatientClinicalRepository::class, PatientClinicalRepository::class);
        $this->app->bind(PatientPrescricoesRepository::class, PatientPrescricoesRepository::class);
        $this->app->bind(PatientAlertsRepository::class, PatientAlertsRepository::class);
        $this->app->bind(PatientSurgeryRepository::class, PatientSurgeryRepository::class);
        $this->app->bind(PatientMultidisciplinaryRepository::class, PatientMultidisciplinaryRepository::class);
        $this->app->bind(PatientExamsRepository::class, PatientExamsRepository::class);
        $this->app->bind(PatientTherapeuticPlanRepository::class, PatientTherapeuticPlanRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blaze::optimize()->in(resource_path('views/components'));

        Blade::anonymousComponentNamespace('sbar.patient.modal', 'patient-modal');
        Blade::anonymousComponentNamespace('sbar.patient.modal.tabs', 'sbar');

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Autoriza o LogViewer package para usuários com permissão 'ver logs'
        Gate::define('viewLogViewer', fn ($user) => $user->can('ver logs'));
    }
}
