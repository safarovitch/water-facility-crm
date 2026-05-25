<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class OrderCacheObserver
{
    public function created(Order $order): void
    {
        $this->bustDashboardCache();
    }

    public function updated(Order $order): void
    {
        $this->bustDashboardCache();
    }

    public function deleted(Order $order): void
    {
        $this->bustDashboardCache();
    }

    private function bustDashboardCache(): void
    {
        Cache::forget('admin_dashboard:stats');
        Cache::forget('admin_dashboard:recent_orders');
        Cache::forget('admin_dashboard:performance');
        Cache::forget('admin_dashboard:unassigned');
    }
}
