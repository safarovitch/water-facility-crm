<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderCacheObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Order::observe(OrderCacheObserver::class);
    }
}
