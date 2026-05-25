<?php

namespace App\Services;

use App\Enums\DeliveryTimeSlot;
use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function generate(Subscription $subscription): Order
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->loadMissing('items.product', 'client');

            $totalAmount = $subscription->items->sum(function ($item) {
                $price = (float) ($item->product->sale_price > 0 ? $item->product->sale_price : $item->product->price);
                return $price * $item->quantity;
            });

            $scheduledAt = $this->buildScheduledDeliveryTime($subscription);

            $order = Order::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'delivery_address' => $subscription->delivery_address,
                'scheduled_delivery_at' => $scheduledAt,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'notes' => $subscription->notes ? "Auto-generated from subscription. {$subscription->notes}" : 'Auto-generated from subscription.',
            ]);

            foreach ($subscription->items as $item) {
                $price = (float) ($item->product->sale_price > 0 ? $item->product->sale_price : $item->product->price);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $price,
                    'subtotal' => $price * $item->quantity,
                ]);
            }

            $subscription->update([
                'last_generated_at' => now(),
                'next_delivery_at' => $this->calculateNextDeliveryDate($subscription),
            ]);

            return $order;
        });
    }

    public function calculateNextDeliveryDate(Subscription $subscription): Carbon
    {
        $base = $subscription->next_delivery_at ?? now();

        return match ($subscription->frequency) {
            SubscriptionFrequency::Weekly => $base->copy()->addWeek(),
            SubscriptionFrequency::Biweekly => $base->copy()->addWeeks(2),
            SubscriptionFrequency::Monthly => $this->nextMonthlyDate($base, $subscription->day_of_month),
            SubscriptionFrequency::Custom => $base->copy()->addDays($subscription->interval_days ?? 7),
        };
    }

    public function pause(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Paused,
            'paused_at' => now(),
        ]);
    }

    public function resume(Subscription $subscription): void
    {
        $nextDate = $this->calculateNextDeliveryDate($subscription);

        if ($nextDate->isPast()) {
            $nextDate = $this->calculateFreshNextDate($subscription);
        }

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'paused_at' => null,
            'next_delivery_at' => $nextDate,
        ]);
    }

    public function cancel(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'next_delivery_at' => null,
        ]);
    }

    private function buildScheduledDeliveryTime(Subscription $subscription): Carbon
    {
        $date = now()->startOfDay();

        $hour = $subscription->time_slot?->hour() ?? 9;

        return $date->setHour($hour);
    }

    private function nextMonthlyDate(Carbon $base, ?int $dayOfMonth): Carbon
    {
        $next = $base->copy()->addMonth();
        $day = $dayOfMonth ?? $base->day;
        $maxDay = $next->daysInMonth;

        return $next->setDay(min($day, $maxDay));
    }

    private function calculateFreshNextDate(Subscription $subscription): Carbon
    {
        $now = now();

        return match ($subscription->frequency) {
            SubscriptionFrequency::Weekly => $subscription->day_of_week !== null
                ? $now->copy()->next($subscription->day_of_week)
                : $now->copy()->addWeek(),
            SubscriptionFrequency::Biweekly => $now->copy()->addWeeks(2),
            SubscriptionFrequency::Monthly => $this->nextMonthlyDate($now, $subscription->day_of_month),
            SubscriptionFrequency::Custom => $now->copy()->addDays($subscription->interval_days ?? 7),
        };
    }
}
