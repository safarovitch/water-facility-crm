<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) counterpart of the Telegram group alerts, delivered
 * per-user so each role only sees what concerns them. The payload carries
 * structured facts, not prose — the mobile app renders localized text from
 * `kind` + params, so wording changes never require a data migration.
 *
 * Kinds: order_created, order_delivered, order_assigned.
 */
class OrderEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $kind,
        private Order $order,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->order->loadMissing(['client', 'courier', 'items.product']);

        return [
            'kind'         => $this->kind,
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'client'       => $this->order->contact_name ?: ($this->order->client?->name ?? null),
            'courier'      => $this->order->courier?->name,
            'address'      => $this->order->delivery_address,
            'total'        => (float) $this->order->total_amount + (float) ($this->order->deposit_charge ?? 0),
            'status'       => $this->order->status->value ?? $this->order->status,
        ];
    }
}
