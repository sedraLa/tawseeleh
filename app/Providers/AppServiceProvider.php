<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Throw an exception if a relationship is accessed without being
        // eager-loaded. Only enabled outside production so it surfaces N+1
        // issues during development and testing without affecting live traffic.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
