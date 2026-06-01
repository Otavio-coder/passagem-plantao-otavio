<?php

namespace App\Providers;

use App\Repositories\EMR\PatientAlertsRepository;
use App\Repositories\EMR\PatientClinicalRepository;
use App\Repositories\EMR\PatientMultidisciplinaryRepository;
use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Repositories\EMR\PatientScalesRepository;
use App\Repositories\EMR\PatientSurgeryRepository;
use App\Services\Tasy\TasyService;
use App\Services\UserDisplayNameResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;
use Livewire\Facades\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TasyService::class);
        $this->app->singleton(UserDisplayNameResolver::class);

        $this->app->bind(PatientScalesRepository::class, PatientScalesRepository::class);
        $this->app->bind(PatientClinicalRepository::class, PatientClinicalRepository::class);
        $this->app->bind(PatientAlertsRepository::class, PatientAlertsRepository::class);
        $this->app->bind(PatientSurgeryRepository::class, PatientSurgeryRepository::class);
        $this->app->bind(PatientMultidisciplinaryRepository::class, PatientMultidisciplinaryRepository::class);
        $this->app->bind(PatientPrescriptionsRepository::class, PatientPrescriptionsRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blaze::optimize()->in(resource_path('views/components'));

        Blade::anonymousComponentNamespace('sbar.patient.modal', 'sbar-patient-modal');
        Blade::anonymousComponentNamespace('sbar.patient.modal.tabs', 'sbar');

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Autoriza o LogViewer package para usuários com permissão 'ver logs'
        Gate::define('viewLogViewer', fn ($user) => $user->can('ver logs'));

        // Garante que Alpine.js é injetado em todas as páginas, mesmo sem componentes Livewire.
        \Livewire\Livewire::forceAssetInjection();
    }
}
