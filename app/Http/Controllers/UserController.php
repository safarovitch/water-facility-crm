<?php

namespace App\Http\Controllers;

use App\Enums\UserActivityStatus;
use App\Enums\UserStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Activitylog\Models\Activity;
use App\Enums\OrderStatus;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Models\UserProfile;

class UserController extends Controller
{
    public function index(): Response
    {
        $pagination = request()->has('pagination')
            ? request()->input('pagination')
            : ['limit' => 50, 'page' => 1];

        return Inertia::render('users/Index')->with([
            'users' => User::query()->excludeSelf()->with('roles')->paginate(
                $pagination['limit'],
                ['*'],
                'page',
                $pagination['page']
            ),
            'roles' => Role::all(),
            'statuses' => UserStatus::asArray(),
            'activityStatuses' => UserActivityStatus::asArray(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Create')->with([
            'roles' => Role::all(),
            'statuses' => UserStatus::asArray()
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::create($data);
        
        if (isset($data['phone'])) {
            $user->phones()->create([
                'phone' => $data['phone'],
                'label' => 'Primary',
                'is_default' => true,
            ]);
        }

        if (isset($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        if ($request->file('avatar') != null) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
        }

        return redirect()->route('users.index')->with('success', __('User created successfully'));
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/Edit')->with([
            'roles' => Role::all(),
            'statuses' => UserStatus::asArray(),
            'user' => $user->load('roles')
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        if (isset($data['phone'])) {
            $user->phones()->updateOrCreate(
                ['is_default' => true],
                ['phone' => $data['phone'], 'label' => 'Primary']
            );
        }

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if ($request->file('avatar') != null) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
            Cache::forget('avatar_url' . $user->id);
        }

        if($request->input('avatar_remove') == '1') {
            $user->avatar = null;
            $user->save();
            Cache::forget('avatar_url' . $user->id);
        }

        return redirect()->route('users.index')->with('success', __('User updated successfully'));
    }

    public function show(User $user): Response
    {
        return Inertia::render('users/Show', $this->getProfileData($user));
    }

    /**
     * Get unified profile data for a user.
     * Shared by UserController@show and DashboardController@index.
     */
    public function getProfileData(User $user): array
    {
        $user->load(['roles', 'userProfile', 'addresses', 'phones', 'wallet']);
        
        // Ensure wallet exists for UI
        if ($user->hasRole('Client')) {
            $user->wallet()->firstOrCreate([], ['balance' => 0, 'currency' => 'TJS']);
            $user->load('wallet');
        }

        $isClient = $user->hasRole('Client') && ! $user->hasAnyRole(['Admin', 'Manager', 'Operator', 'Courier']);
        $isCourier = $user->hasRole('Courier');

        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // ── Client profile stats ──────────────────────────────────────────────
        $clientStats = null;
        if ($user->hasRole('Client')) {
            $ordersQuery = Order::where('user_id', $user->id);
            $clientStats = [
                'totalOrders'        => (clone $ordersQuery)->count(),
                'totalSpent'         => (float) (clone $ordersQuery)->sum('total_amount'),
                'totalPaid'          => (float) (clone $ordersQuery)->sum('paid_amount'),
                'pendingOrders'      => (clone $ordersQuery)->whereIn('status', ['pending', 'confirmed', 'in_production', 'ready'])->count(),
                'deliveredOrders'    => (clone $ordersQuery)->where('status', 'delivered')->count(),
                'cancelledOrders'    => (clone $ordersQuery)->where('status', 'cancelled')->count(),
                'thisMonthOrders'    => (clone $ordersQuery)->where('created_at', '>=', $thisMonth)->count(),
            ];
        }

        // ── Staff / Courier performance stats ─────────────────────────────────
        $staffStats = null;
        if (! $isClient) {
            $deliveredBase = Order::where('courier_id', $user->id)->where('status', 'delivered');
            $thisMonthDelivered = (clone $deliveredBase)->where('created_at', '>=', $thisMonth)->count();
            $lastMonthDelivered = (clone $deliveredBase)->whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();

            $totalDelivered       = (clone $deliveredBase)->count();
            $totalRevenue         = (float) Order::where('courier_id', $user->id)->sum('paid_amount');
            $avgOrderValue        = $totalDelivered > 0
                ? round(Order::where('courier_id', $user->id)->where('status', 'delivered')->avg('total_amount'), 2)
                : 0;

            $deliveryTrend = $lastMonthDelivered > 0
                ? round((($thisMonthDelivered - $lastMonthDelivered) / $lastMonthDelivered) * 100, 1)
                : ($thisMonthDelivered > 0 ? 100 : 0);

            $sixMonthsAgo = now()->subMonths(6)->startOfMonth();
            $monthlyDeliveries = Order::where('courier_id', $user->id)
                ->where('status', 'delivered')
                ->where('created_at', '>=', $sixMonthsAgo)
                ->get(['created_at'])
                ->groupBy(fn($o) => $o->created_at->format('M Y'))
                ->map(fn($group, $month) => (object) ['month' => $month, 'total' => $group->count()])
                ->sortBy(fn($item) => \Carbon\Carbon::createFromFormat('M Y', $item->month)->startOfMonth())
                ->values();

            $recentActivity = Order::where('courier_id', $user->id)
                ->with(['client:id,name'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn($o) => [
                    'id'           => $o->id,
                    'order_number' => $o->order_number,
                    'client_name'  => $o->client?->name ?? '—',
                    'status'       => $o->status->value ?? $o->status,
                    'statusLabel'  => $o->statusLabel ?? $o->status,
                    'total_amount' => (float) $o->total_amount,
                    'created_at'   => $o->created_at_formatted,
                ]);

            $staffStats = [
                'totalDelivered'      => $totalDelivered,
                'thisMonthDelivered'  => $thisMonthDelivered,
                'lastMonthDelivered'  => $lastMonthDelivered,
                'deliveryTrend'       => $deliveryTrend,
                'totalRevenue'        => $totalRevenue,
                'avgOrderValue'       => $avgOrderValue,
                'monthlyDeliveries'   => $monthlyDeliveries,
                'recentActivity'      => $recentActivity,
            ];
        }

        // ── Call history (from Spatie activity log) ───────────────────────────
        $phoneNumbers = $user->phones->pluck('phone')->toArray();
        if (!empty($user->phone)) $phoneNumbers[] = $user->phone;

        $callHistory = collect();
        if (!empty($phoneNumbers)) {
            $callHistory = Activity::where('log_name', 'call')
                ->where(function ($query) use ($phoneNumbers, $user) {
                    $query->where(fn($q) => $q->where('causer_id', $user->id)->where('causer_type', User::class));
                    foreach ($phoneNumbers as $phone) {
                        $query->orWhereJsonContains('properties->phone', $phone);
                    }
                })
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'phone'         => $a->properties->get('phone', '—'),
                    'duration'      => $a->properties->get('duration', 0),
                    'direction'     => $a->properties->get('direction', '—'),
                    'status'        => $a->properties->get('status', '—'),
                    'recording_url' => $a->properties->get('recording_url'),
                    'description'   => $a->description,
                    'created_at'    => $a->created_at->format('d M Y, H:i'),
                ]);
        }

        // ── Financials & Orders ─────────────────────────────────────────────
        $transactions = $user->wallet ? $user->wallet->transactions()->latest()->take(20)->get() : collect();
        $orders = $user->orders()->with(['items.product'])->latest()->paginate(15);

        return [
            'profileUser' => [
                ...$user->toArray(),
                'roles'       => $user->getRoleNames(),
                'avatar_url'  => $user->avatar_url,
                'is_client'   => $user->hasRole('Client'),
                'is_courier'  => $isCourier,
                'is_staff'    => $user->hasAnyRole(['Admin', 'Manager', 'Operator', 'Courier']),
                'is_self'     => auth()->id() === $user->id,
            ],
            'clientStats'  => $clientStats,
            'staffStats'   => $staffStats,
            'callHistory'  => $callHistory,
            'transactions' => $transactions,
            'orders'       => $orders,
            'statuses'     => OrderStatus::asArray(),
        ];
    }

    public function checkEmail(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists'    => true,
            'is_client' => $user->isClient(),
            'name'      => $user->name,
            'phone'     => $user->phone,
        ]);
    }
}
