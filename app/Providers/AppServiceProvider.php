<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use App\Repositories\EMR\{
    PatientClinicalRepository,
    PatientCPOERepository,
    PatientScalesRepository
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
        $this->app->bind(PatientCPOERepository::class, PatientCPOERepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
