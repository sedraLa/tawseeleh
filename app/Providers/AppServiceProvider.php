<?php

namespace App\Providers;

use App\Contracts\DeliveryFeeCalculator;
use App\Services\FixedDeliveryFeeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryFeeCalculator::class, function () {
            return new FixedDeliveryFeeCalculator(
                (float) config('app.delivery_fee', 10.00)
            );
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
