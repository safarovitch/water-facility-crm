<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\RawMaterial;
use App\Enums\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Admin', 'Manager', 'Operator', 'Currier'])) {
            return redirect()->route('dashboard');
        }

        if (! $request->session()->get('admin_mode', false)) {
            $request->session()->put('admin_mode', true);
        }

        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = Cache::remember('admin_dashboard:stats', 30, function () use ($thisMonth) {
            $totalOrders = Order::count();
            $todayOrders = Order::whereDate('created_at', today())->count();
            $thisMonthOrders = Order::where('created_at', '>=', $thisMonth)->count();

            $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $totalRevenue = (float) Order::sum('paid_amount');
            $totalOutstanding = (float) Order::where('status', '!=', OrderStatus::Cancelled)
                ->whereColumn('paid_amount', '<', 'total_amount')
                ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
                ->value('outstanding');
            $monthRevenue = (float) Order::where('created_at', '>=', $thisMonth)->sum('paid_amount');

            $totalClients = User::role('Client')->count();
            $newClientsMonth = User::role('Client')->where('created_at', '>=', $thisMonth)->count();

            $totalProducts = Product::count();
            $activeProducts = Product::where('status', 'active')->count();

            return [
                'totalOrders' => $totalOrders,
                'todayOrders' => $todayOrders,
                'thisMonthOrders' => $thisMonthOrders,
                'ordersByStatus' => $ordersByStatus,
                'totalRevenue' => $totalRevenue,
                'monthRevenue' => $monthRevenue,
                'totalOutstanding' => $totalOutstanding,
                'totalClients' => $totalClients,
                'newClientsMonth' => $newClientsMonth,
                'totalProducts' => $totalProducts,
                'activeProducts' => $activeProducts,
            ];
        });

        $recentOrders = Cache::remember('admin_dashboard:recent_orders', 15, function () {
            return Order::with(['client:id,name,email', 'items'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'client_name' => $order->contact_name ?: ($order->client?->name ?? '—'),
                    'status' => $order->status->value ?? $order->status,
                    'total_amount' => (float) $order->total_amount,
                    'paid_amount' => (float) $order->paid_amount,
                    'payment_status' => $order->payment_status->value ?? $order->payment_status,
                    'created_at' => $order->created_at,
                    'created_at_formatted' => $order->created_at_formatted,
                ]);
        });

        $topProducts = Cache::remember('admin_dashboard:top_products', 300, function () {
            return DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as total_sold'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get();
        });

        $performance = Cache::remember('admin_dashboard:performance', 60, function () use ($today) {
            $totalCouriers = User::role('Currier')->count();
            $activeCouriers = User::role('Currier')
                ->where(function ($query) {
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

            return [
                'activeCouriers' => $activeCouriers,
                'totalCouriers' => $totalCouriers,
                'avgDeliveryMinutes' => $avgDeliveryMinutes,
                'fulfillmentsToday' => $deliveredToday->count(),
            ];
        });

        $unassignedOrders = Cache::remember('admin_dashboard:unassigned', 15, function () {
            return Order::whereNull('courier_id')
                ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
                ->with('client:id,name')
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'client_name' => $o->contact_name ?: ($o->client?->name ?? '—'),
                    'status' => $o->status->value ?? $o->status,
                    'created_at_human' => $o->created_at_human,
                ]);
        });

        $lowStockProducts = Cache::remember('admin_dashboard:low_stock_products', 120, function () {
            return Product::where('manage_stock', true)
                ->whereColumn('quantity', '<=', 'low_stock_threshold')
                ->select('id', 'name', 'sku', 'quantity', 'low_stock_threshold')
                ->limit(6)
                ->get();
        });

        $lowStockRawMaterials = Cache::remember('admin_dashboard:low_stock_raw', 120, function () {
            return RawMaterial::orderBy('current_stock', 'asc')
                ->select('id', 'name', 'sku', 'current_stock', 'unit')
                ->limit(6)
                ->get();
        });

        return Inertia::render('AdminDashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'performance' => $performance,
            'unassignedOrders' => $unassignedOrders,
            'lowStockProducts' => $lowStockProducts,
            'lowStockRawMaterials' => $lowStockRawMaterials,
        ]);
    }
}
