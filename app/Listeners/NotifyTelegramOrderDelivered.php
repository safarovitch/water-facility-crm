<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Services\TelegramNotifier;

class NotifyTelegramOrderDelivered
{
    public function __construct(private TelegramNotifier $notifier)
    {
    }

    public function handle(OrderDelivered $event): void
    {
        $this->notifier->notifyOrderDelivered($event->order);
    }
}
