<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return $this->clientHome($user);
    }

    /**
     * Data shown on the simplified client landing page.
     *
     *   - profile contact info (name, phones, default address)
     *   - active order (if status is in the "in progress" window) with courier
     *   - order history (last 20, read-only)
     */
    private function clientHome($user): Response
    {
        $user->load(['phones', 'addresses', 'userProfile']);

        $activeStatuses = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
            OrderStatus::InProduction,
            OrderStatus::Ready,
            OrderStatus::Accepted,
            OrderStatus::InTransit,
        ];

        $activeOrder = Order::where('user_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->with(['items.product', 'courier'])
            ->latest()
            ->first();

        $orderHistory = Order::where('user_id', $user->id)
            ->with(['items.product'])
            ->latest()
            ->limit(20)
            ->get();

        $defaultAddress = $user->addresses->firstWhere('is_default', true) ?? $user->addresses->first();
        $defaultPhone = $user->phones->firstWhere('is_default', true) ?? $user->phones->first();

        return Inertia::render('ClientHome', [
            'profile' => [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'phone'   => $defaultPhone?->phone,
                'phones'  => $user->phones,
                'address' => $defaultAddress,
            ],
            'activeOrder'  => $activeOrder,
            'orderHistory' => $orderHistory,
        ]);
    }
}
