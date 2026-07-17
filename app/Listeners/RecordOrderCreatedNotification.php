<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Support\StaffNotifier;

/**
 * In-app twin of NotifyTelegramOrderCreated: same event, but written to the
 * per-user notification inbox consumed by the mobile management app.
 */
class RecordOrderCreatedNotification
{
    public function handle(OrderCreated $event): void
    {
        StaffNotifier::orderCreated($event->order);
    }
}
