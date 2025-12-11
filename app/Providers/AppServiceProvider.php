<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\OrderHasPaid;
use App\Observers\OrderHasPaidObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        OrderHasPaid::observe(OrderHasPaidObserver::class);
    }
}
