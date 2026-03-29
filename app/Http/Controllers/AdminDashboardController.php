<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\RawMaterial;
use App\Enums\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Only non-client (admin/staff) users with admin mode enabled can access the admin dashboard
        if (! $user->hasAnyRole(['Admin', 'Manager', 'Operator', 'Currier'])) {
            return redirect()->route('dashboard'); // not a staff user → go to /profile
        }

        if (! $request->session()->get('admin_mode', false)) {
            // Staff user but hasn't switched to admin mode → redirect to /profile
            return redirect()->route('dashboard');
        }

        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        // ------- Order stats -------
        $totalOrders = Order::count();
        $todayOrders = Order::whereDate('created_at', today())->count();
        $thisMonthOrders = Order::where('created_at', '>=', $thisMonth)->count();

        $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // ------- Revenue stats -------
        $totalRevenue     = (float) Order::sum('paid_amount');
        $totalOutstanding = (float) Order::whereColumn('paid_amount', '<', 'total_amount')
            ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
            ->value('outstanding');
        $monthRevenue = (float) Order::where('created_at', '>=', $thisMonth)->sum('paid_amount');

        // ------- Client stats -------
        $totalClients      = User::role('Client')->count();
        $newClientsMonth   = User::role('Client')->where('created_at', '>=', $thisMonth)->count();

        // ------- Product stats -------
        $totalProducts     = Product::count();
        $activeProducts    = Product::where('status', 'active')->count();

        // ------- Recent orders (last 10) -------
        $recentOrders = Order::with(['client:id,name,email', 'items'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($order) => [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'client_name'    => $order->client?->name ?? '—',
                'status'         => $order->status->value ?? $order->status,
                'total_amount'   => (float) $order->total_amount,
                'paid_amount'    => (float) $order->paid_amount,
                'payment_status' => $order->payment_status->value ?? $order->payment_status,
                'created_at'     => $order->created_at,
                'created_at_formatted' => $order->created_at_formatted,
            ]);

        // ------- Top products by order count -------
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // ------- Facility Performance -------
        $totalCouriers = User::role('Currier')->count();
        $activeCouriers = User::role('Currier')
            ->where(function ($query) {
                // Tracking online statuses within last hour
                $query->where('last_active_at', '>=', now()->subMinutes(60))
                      ->orWhereHas('lastLocation', function ($q) {
                          $q->where('created_at', '>=', now()->subMinutes(60));
                      });
            })->count();

        $deliveredToday = Order::where('status', OrderStatus::Delivered)
            ->whereDate('actual_delivery_at', '>=', $today)
            ->select('created_at', 'actual_delivery_at')
            ->get();

        $avgDeliveryMinutes = 0;
        if ($deliveredToday->isNotEmpty()) {
            $totalMins = $deliveredToday->sum(function ($order) {
                return $order->actual_delivery_at->diffInMinutes($order->created_at);
            });
            $avgDeliveryMinutes = round($totalMins / $deliveredToday->count());
        }

        // ------- Unassigned Orders (Fast Dispatch) -------
        $unassignedOrders = Order::whereNull('courier_id')
            ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            ->with('client:id,name')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'client_name' => $o->client?->name ?? '—',
                'status' => $o->status->value ?? $o->status,
                'created_at_human' => $o->created_at_human,
            ]);

        // ------- Early Inventory Warnings -------
        $lowStockProducts = Product::where('manage_stock', true)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->select('id', 'name', 'sku', 'quantity', 'low_stock_threshold')
            ->limit(6)
            ->get();

        $lowStockRawMaterials = RawMaterial::orderBy('current_stock', 'asc')
            ->select('id', 'name', 'sku', 'current_stock', 'unit')
            ->limit(6)
            ->get();

        return Inertia::render('AdminDashboard', [
            'stats' => [
                'totalOrders'      => $totalOrders,
                'todayOrders'      => $todayOrders,
                'thisMonthOrders'  => $thisMonthOrders,
                'ordersByStatus'   => $ordersByStatus,
                'totalRevenue'     => $totalRevenue,
                'monthRevenue'     => $monthRevenue,
                'totalOutstanding' => $totalOutstanding,
                'totalClients'     => $totalClients,
                'newClientsMonth'  => $newClientsMonth,
                'totalProducts'    => $totalProducts,
                'activeProducts'   => $activeProducts,
            ],
            'recentOrders' => $recentOrders,
            'topProducts'  => $topProducts,
            'performance' => [
                'activeCouriers' => $activeCouriers,
                'totalCouriers' => $totalCouriers,
                'avgDeliveryMinutes' => $avgDeliveryMinutes,
                'fulfillmentsToday' => $deliveredToday->count(),
            ],
            'unassignedOrders' => $unassignedOrders,
            'lowStockProducts' => $lowStockProducts,
            'lowStockRawMaterials' => $lowStockRawMaterials,
        ]);
    }
}
