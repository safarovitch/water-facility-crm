<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryTimeSlot;
use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service)
    {
    }

    public function index(Request $request)
    {
        $query = Subscription::with('client:id,name,email', 'items.product:id,name')
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return Inertia::render('subscriptions/Index', [
            'subscriptions' => $query->paginate(20),
            'statuses' => SubscriptionStatus::getValues(),
            'frequencies' => collect(SubscriptionFrequency::cases())->map(fn($f) => [
                'value' => $f->value,
                'label' => $f->label(),
            ]),
        ]);
    }

    public function create()
    {
        $clients = User::role('Client')
            ->select('id', 'name', 'email')
            ->with('addresses:id,user_id,label,address_line')
            ->orderBy('name')
            ->get();

        $products = Product::where('status', 'active')
            ->select('id', 'name', 'price', 'sale_price')
            ->orderBy('name')
            ->get();

        return Inertia::render('subscriptions/Create', [
            'clients' => $clients,
            'products' => $products,
            'frequencies' => collect(SubscriptionFrequency::cases())->map(fn($f) => [
                'value' => $f->value,
                'label' => $f->label(),
            ]),
            'timeSlots' => collect(DeliveryTimeSlot::cases())->map(fn($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'frequency' => 'required|in:' . implode(',', SubscriptionFrequency::getValues()),
            'interval_days' => 'nullable|integer|min:1|max:365',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'time_slot' => 'nullable|in:' . implode(',', DeliveryTimeSlot::getValues()),
            'delivery_address' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $subscription = Subscription::create([
            'user_id' => $validated['user_id'],
            'status' => SubscriptionStatus::Active,
            'frequency' => $validated['frequency'],
            'interval_days' => $validated['interval_days'] ?? null,
            'day_of_week' => $validated['day_of_week'] ?? null,
            'day_of_month' => $validated['day_of_month'] ?? null,
            'time_slot' => $validated['time_slot'] ?? null,
            'delivery_address' => $validated['delivery_address'],
            'notes' => $validated['notes'] ?? null,
            'next_delivery_at' => $this->service->calculateNextDeliveryDate(
                new Subscription(['frequency' => $validated['frequency'], 'interval_days' => $validated['interval_days'] ?? null, 'day_of_week' => $validated['day_of_week'] ?? null, 'day_of_month' => $validated['day_of_month'] ?? null, 'next_delivery_at' => now()])
            ),
        ]);

        foreach ($validated['items'] as $item) {
            $subscription->items()->create($item);
        }

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Subscription created successfully.');
    }

    public function show(Subscription $subscription)
    {
        $subscription->load('client:id,name,email,phone', 'items.product:id,name,price,sale_price', 'orders');

        return Inertia::render('subscriptions/Show', [
            'subscription' => $subscription,
            'recentOrders' => $subscription->orders()->latest()->limit(10)->get(),
        ]);
    }

    public function pause(Subscription $subscription)
    {
        $this->service->pause($subscription);

        return back()->with('success', 'Subscription paused.');
    }

    public function resume(Subscription $subscription)
    {
        $this->service->resume($subscription);

        return back()->with('success', 'Subscription resumed.');
    }

    public function cancel(Subscription $subscription)
    {
        $this->service->cancel($subscription);

        return back()->with('success', 'Subscription cancelled.');
    }
}
