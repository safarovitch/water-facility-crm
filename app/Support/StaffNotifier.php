<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderEventNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Role-aware fan-out for in-app notifications. The Telegram group gets one
 * broadcast; here every staff member receives a personal copy scoped by
 * role, so the mobile app can show a per-user inbox and unread badge:
 *
 *   - order_created / order_delivered → everyone who manages orders
 *     (admin roles + Currier manager), plus the assigned courier.
 *   - order_assigned → only the courier it was assigned to.
 */
class StaffNotifier
{
    public static function orderCreated(Order $order): void
    {
        self::notify(self::orderManagers($order), 'order_created', $order);
    }

    public static function orderDelivered(Order $order): void
    {
        self::notify(self::orderManagers($order), 'order_delivered', $order);
    }

    public static function orderAssigned(Order $order, User $courier): void
    {
        self::notify(collect([$courier]), 'order_assigned', $order);
    }

    /** Admin roles + courier managers + the order's own courier (if any). */
    private static function orderManagers(Order $order): \Illuminate\Support\Collection
    {
        $managers = User::role(array_merge(User::ADMIN_ROLES, ['Currier manager']))->get();

        if ($order->courier_id && ! $managers->contains('id', $order->courier_id)) {
            $courier = User::find($order->courier_id);
            if ($courier) {
                $managers->push($courier);
            }
        }

        return $managers->unique('id');
    }

    private static function notify(\Illuminate\Support\Collection $users, string $kind, Order $order): void
    {
        try {
            Notification::send($users, new OrderEventNotification($kind, $order));
        } catch (\Throwable $e) {
            // Notifications must never break the business action they follow.
            Log::warning('StaffNotifier: failed to record notifications', [
                'kind'  => $kind,
                'order' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
