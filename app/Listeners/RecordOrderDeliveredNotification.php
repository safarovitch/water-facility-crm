<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Support\StaffNotifier;

/**
 * In-app twin of NotifyTelegramOrderDelivered — see RecordOrderCreatedNotification.
 */
class RecordOrderDeliveredNotification
{
    public function handle(OrderDelivered $event): void
    {
        StaffNotifier::orderDelivered($event->order);
    }
}
