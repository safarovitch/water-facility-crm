<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("client.{$this->order->user_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value ?? $this->order->status,
            'courier' => $this->order->courier ? [
                'name' => $this->order->courier->name,
                'phone' => $this->order->courier->phone,
            ] : null,
            'scheduled_delivery_at_human' => $this->order->scheduled_delivery_at?->diffForHumans(),
        ];
    }
}
