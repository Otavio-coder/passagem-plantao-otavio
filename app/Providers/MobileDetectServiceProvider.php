<?php

namespace App\Providers;

use Detection\MobileDetect;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class MobileDetectServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton(MobileDetect::class, function () {
            return new MobileDetect;
        });
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            $detect = app(MobileDetect::class);
            $view->with('isMobileOrTablet', $detect->isMobile() || $detect->isTablet());
        });
    }
}
