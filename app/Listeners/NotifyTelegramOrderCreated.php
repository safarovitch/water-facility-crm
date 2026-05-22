<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\TelegramNotifier;

class NotifyTelegramOrderCreated
{
    public function __construct(private TelegramNotifier $notifier)
    {
    }

    public function handle(OrderCreated $event): void
    {
        $this->notifier->notifyOrderCreated($event->order);
    }
}
