<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value ?? $this->status,
            'payment_status' => $this->payment_status->value ?? $this->payment_status,
            'total_amount' => $this->total_amount,
            'deposit_charge' => $this->deposit_charge,
            'grand_total' => $this->grand_total,
            'paid_amount' => $this->paid_amount,
            'balance_due' => $this->balance_due,
            'delivery_date' => $this->delivery_date,
            'scheduled_delivery_at_human' => $this->scheduled_delivery_at?->diffForHumans(),
            'scheduled_delivery_at_formatted' => $this->scheduled_delivery_at?->format('F j, Y H:i'),
            'created_at' => $this->created_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'created_at_formatted' => $this->created_at?->format('F j, Y H:i'),
            'client' => $this->whenLoaded('client', fn() => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ]),
            'creator' => $this->whenLoaded('creator', fn() => [
                'name' => $this->creator->name,
            ]),
        ];
    }
}
