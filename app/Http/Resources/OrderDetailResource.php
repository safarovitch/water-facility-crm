<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value ?? $this->status,
            'statusLabel' => ucfirst(str_replace('_', ' ', $this->status->value ?? $this->status)),
            'payment_status' => $this->payment_status->value ?? $this->payment_status,
            'total_amount' => $this->total_amount,
            'discount_amount' => $this->discount_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => (float) $this->total_amount - (float) $this->paid_amount,
            'delivery_date' => $this->delivery_date,
            'delivery_address' => $this->delivery_address,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'scheduled_delivery_at' => $this->scheduled_delivery_at,
            'scheduled_delivery_at_human' => $this->scheduled_delivery_at?->diffForHumans(),
            'scheduled_delivery_at_formatted' => $this->scheduled_delivery_at?->format('F j, Y H:i'),
            'actual_delivery_at' => $this->actual_delivery_at,
            'actual_delivery_at_human' => $this->actual_delivery_at?->diffForHumans(),
            'actual_delivery_at_formatted' => $this->actual_delivery_at?->format('F j, Y H:i'),
            'cancelled_at' => $this->cancelled_at,
            'cancelled_at_human' => $this->cancelled_at?->diffForHumans(),
            'cancelled_at_formatted' => $this->cancelled_at?->format('F j, Y H:i'),
            'created_at' => $this->created_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'created_at_formatted' => $this->created_at?->format('F j, Y H:i'),
            'updated_at_human' => $this->updated_at?->diffForHumans(),
            'updated_at_formatted' => $this->updated_at?->format('F j, Y H:i'),
            'client' => $this->whenLoaded('client', fn() => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
            ]),
            'courier' => $this->whenLoaded('courier', fn() => [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'phone' => $this->courier->phone,
            ]),
            'creator' => $this->whenLoaded('creator', fn() => [
                'name' => $this->creator->name,
            ]),
            'canceller' => $this->whenLoaded('canceller', fn() => [
                'name' => $this->canceller->name,
            ]),
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'is_gift' => $item->is_gift ?? false,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
            ])),
        ];
    }
}
