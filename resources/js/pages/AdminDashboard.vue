<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';
import {
    ShoppingCart, TrendingUp, Users2, Package,
    Clock, CheckCircle, AlertCircle, XCircle,
    Truck, Factory, DollarSign, ArrowUpRight, BarChart3,
    Activity, Clock4, PackageMinus, AlertTriangle
} from 'lucide-vue-next';

interface Stats {
    totalOrders: number;
    todayOrders: number;
    thisMonthOrders: number;
    ordersByStatus: Record<string, number>;
    totalRevenue: number;
    monthRevenue: number;
    totalOutstanding: number;
    totalClients: number;
    newClientsMonth: number;
    totalProducts: number;
    activeProducts: number;
}

interface RecentOrder {
    id: number;
    order_number: string;
    client_name: string;
    status: string;
    total_amount: number;
    paid_amount: number;
    payment_status: string;
    created_at_formatted: string;
}

interface TopProduct {
    id: number;
    name: string;
    total_sold: number;
    total_gifted: number;
}

interface PerformanceStats {
    activeCouriers: number;
    totalCouriers: number;
    avgDeliveryMinutes: number;
    fulfillmentsToday: number;
}

interface UnassignedOrder {
    id: number;
    order_number: string;
    client_name: string;
    status: string;
    created_at_human: string;
}

interface LowStockProduct {
    id: number;
    name: any;
    sku: string;
    quantity: number;
    low_stock_threshold: number;
}

interface LowStockRawMaterial {
    id: number;
    name: string;
    sku: string;
    current_stock: number;
    unit: string;
}

const props = defineProps<{
    stats: Stats;
    recentOrders: RecentOrder[];
    topProducts: TopProduct[];
    performance: PerformanceStats;
    unassignedOrders: UnassignedOrder[];
    lowStockProducts: LowStockProduct[];
    lowStockRawMaterials: LowStockRawMaterial[];
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('Dashboard'), href: dashboard().url },
]);

const { t: tl } = useLocale();

const fmt = (n: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n);
const fmtCurrency = (n: number) => {
    const currency = (usePage().props.currency as string) || 'USD';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
};

const statusConfig = computed((): Record<string, { label: string; color: string; icon: any }> => ({
    pending:      { label: t('Pending'),      color: 'text-amber-500   bg-amber-500/10',   icon: Clock },
    confirmed:    { label: t('Confirmed'),    color: 'text-blue-500    bg-blue-500/10',    icon: CheckCircle },
    in_production:{ label: t('In Production'),color: 'text-purple-500  bg-purple-500/10',  icon: Factory },
    ready:        { label: t('Ready'),        color: 'text-green-500   bg-green-500/10',   icon: ArrowUpRight },
    accepted:     { label: t('Picked up'),    color: 'text-indigo-500  bg-indigo-500/10',  icon: Truck },
    in_transit:   { label: t('On the way'),   color: 'text-sky-500     bg-sky-500/10',     icon: Truck },
    delivered:    { label: t('Delivered'),    color: 'text-emerald-500 bg-emerald-500/10', icon: Truck },
    cancelled:    { label: t('Cancelled'),    color: 'text-red-500     bg-red-500/10',     icon: XCircle },
}));

const allStatuses = ['pending', 'confirmed', 'in_production', 'ready', 'accepted', 'in_transit', 'delivered', 'cancelled'];
const maxStatusCount = Math.max(...allStatuses.map(s => props.stats.ordersByStatus[s] ?? 0), 1);
</script>

<template>
    <Head :title="t('Dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">

            <!-- KPI Cards Row 1 -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <!-- Total Orders -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Total Orders') }}</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.totalOrders) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ stats.todayOrders }} {{ t('today') }} · {{ stats.thisMonthOrders }} {{ t('this month') }}</p>
                        </div>
                        <div class="rounded-lg bg-primary/10 p-2.5">
                            <ShoppingCart class="h-5 w-5 text-primary" />
                        </div>
                    </div>
                </div>

                <!-- Revenue Collected -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Revenue Collected') }}</p>
                            <p class="mt-2 text-2xl font-bold text-foreground">{{ fmtCurrency(stats.totalRevenue) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ fmtCurrency(stats.monthRevenue) }} {{ t('this month') }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-500/10 p-2.5">
                            <TrendingUp class="h-5 w-5 text-emerald-500" />
                        </div>
                    </div>
                </div>

                <!-- Outstanding -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Outstanding') }}</p>
                            <p class="mt-2 text-2xl font-bold text-foreground">{{ fmtCurrency(stats.totalOutstanding) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ t('unpaid balance') }}</p>
                        </div>
                        <div class="rounded-lg bg-red-500/10 p-2.5">
                            <AlertCircle class="h-5 w-5 text-red-500" />
                        </div>
                    </div>
                </div>

                <!-- Clients -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Clients') }}</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.totalClients) }}</p>
                            <p class="mt-1 text-xs text-emerald-500">+{{ stats.newClientsMonth }} {{ t('this month') }}</p>
                        </div>
                        <div class="rounded-lg bg-blue-500/10 p-2.5">
                            <Users2 class="h-5 w-5 text-blue-500" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operations Metrics Row -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <!-- Delivery Success -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Fulfillments Today') }}</p>
                            <p class="mt-2 text-3xl font-bold text-emerald-500">{{ fmt(performance.fulfillmentsToday) }}</p>
                            <p class="mt-1 text-[10px] text-muted-foreground uppercase text-emerald-500/80 font-semibold tracking-wider">{{ t('Orders successfully delivered') }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-500/10 p-2.5">
                            <Truck class="h-5 w-5 text-emerald-500" />
                        </div>
                    </div>
                </div>

                <!-- Active Fleet -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Active Couriers') }}</p>
                            <div class="mt-2 flex items-baseline gap-2">
                                <p class="text-3xl font-bold text-blue-500">{{ performance.activeCouriers }}</p>
                                <p class="text-sm font-bold text-muted-foreground">/ {{ performance.totalCouriers }}</p>
                            </div>
                            <div class="mt-1 flex items-center gap-1.5"><div class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></div><p class="text-[10px] text-muted-foreground uppercase font-semibold tracking-wider">{{ t('Fleet Online Now') }}</p></div>
                        </div>
                        <div class="rounded-lg bg-blue-500/10 p-2.5">
                            <Activity class="h-5 w-5 text-blue-500" />
                        </div>
                    </div>
                </div>
                
                <!-- Avg Delivery Time -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Avg. Delivery Pace') }}</p>
                            <div class="mt-2 flex items-baseline gap-1">
                                <p class="text-3xl font-bold text-purple-500">{{ performance.avgDeliveryMinutes > 0 ? performance.avgDeliveryMinutes : '--' }}</p>
                                <p v-if="performance.avgDeliveryMinutes > 0" class="text-sm font-bold text-purple-500/70">{{ t('min') }}</p>
                            </div>
                            <p class="mt-1 text-[10px] text-muted-foreground uppercase text-purple-500/80 font-semibold tracking-wider">{{ t('Average time to fulfill') }}</p>
                        </div>
                        <div class="rounded-lg bg-purple-500/10 p-2.5">
                            <Clock4 class="h-5 w-5 text-purple-500" />
                        </div>
                    </div>
                </div>

                <!-- Unassigned Load -->
                <div class="rounded-xl border border-primary/20 bg-primary/5 p-5 shadow-inner dark:border-primary/20">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-primary shadow-sm">{{ t('Pending Dispatch') }}</p>
                            <p class="mt-2 text-3xl font-bold text-primary">{{ unassignedOrders.length }}</p>
                            <p class="mt-1 text-[10px] text-primary/70 uppercase font-semibold tracking-wider">{{ t('Awaiting Courier Assignment') }}</p>
                        </div>
                        <div class="rounded-lg bg-primary/20 p-2.5">
                            <PackageMinus class="h-5 w-5 text-primary" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders by Status + Products Row -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                <!-- Orders by Status -->
                <div class="col-span-1 rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border md:col-span-2">
                    <div class="mb-4 flex items-center gap-2">
                        <BarChart3 class="h-4 w-4 text-muted-foreground" />
                        <h3 class="text-sm font-semibold text-foreground">{{ t('Orders by Status') }}</h3>
                    </div>
                    <div class="space-y-3">
                        <div v-for="status in allStatuses" :key="status" class="flex items-center gap-3">
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-xs"
                                :class="statusConfig[status].color"
                            >
                                <component :is="statusConfig[status].icon" class="h-3.5 w-3.5" />
                            </div>
                            <div class="flex-1">
                                <div class="mb-1 flex justify-between">
                                    <span class="text-xs font-medium text-foreground">{{ statusConfig[status].label }}</span>
                                    <span class="text-xs text-muted-foreground">{{ stats.ordersByStatus[status] ?? 0 }}</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="statusConfig[status].color.split(' ')[0].replace('text-', 'bg-')"
                                        :style="{ width: `${((stats.ordersByStatus[status] ?? 0) / maxStatusCount) * 100}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products & Top Products -->
                <div class="flex flex-col gap-4">
                    <!-- Product summary card -->
                    <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Products') }}</p>
                                <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.totalProducts) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ stats.activeProducts }} {{ t('active') }}</p>
                            </div>
                            <div class="rounded-lg bg-violet-500/10 p-2.5">
                                <Package class="h-5 w-5 text-violet-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Top products -->
                    <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <div class="mb-3 flex items-center gap-2">
                            <DollarSign class="h-4 w-4 text-muted-foreground" />
                            <h3 class="text-sm font-semibold text-foreground">{{ t('Top Products') }}</h3>
                        </div>
                        <div v-if="topProducts.length" class="space-y-2">
                            <div
                                v-for="(product, idx) in topProducts"
                                :key="product.id"
                                class="flex items-center gap-3"
                            >
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold text-muted-foreground">
                                    {{ idx + 1 }}
                                </span>
                                <span class="flex-1 truncate text-xs font-medium text-foreground">{{ tl(product.name) }}</span>
                                <div class="flex gap-2 text-xs text-muted-foreground">
                                    <span>{{ product.total_sold }} {{ t('sold') }}</span>
                                    <span class="text-emerald-500">{{ product.total_gifted }} {{ t('gifted') }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">{{ t('No sales data yet.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Operational Alerts & Queues Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Fast Delivery Dispatch Queue -->
                <div class="rounded-xl border border-primary/20 bg-primary/5 dark:border-primary/20">
                    <div class="flex items-center justify-between border-b border-primary/10 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <Truck class="h-4 w-4 text-primary" />
                            <h3 class="text-sm font-semibold text-primary">{{ t('Pending Dispatch Queue') }}</h3>
                        </div>
                        <a href="/orders/assignments" class="text-xs font-bold uppercase tracking-wider text-primary hover:underline">{{ t('Dispatch All') }} &rarr;</a>
                    </div>
                    <div class="p-0">
                        <!-- Desktop Queue -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-primary/10">
                                        <th class="px-5 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-primary/70">{{ t('Order') }}</th>
                                        <th class="px-5 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-primary/70">{{ t('Client') }}</th>
                                        <th class="px-5 py-2 text-right text-[10px] font-bold uppercase tracking-wider text-primary/70">{{ t('Wait Time') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="order in unassignedOrders" :key="order.id" class="border-b border-primary/5 transition-colors last:border-0 hover:bg-primary/10">
                                        <td class="px-5 py-2"><a :href="`/orders/${order.id}`" class="font-mono text-xs font-bold text-primary">{{ order.order_number }}</a></td>
                                        <td class="px-5 py-2 text-xs font-medium text-primary/90">{{ order.client_name }}</td>
                                        <td class="px-5 py-2 text-right text-[10px] font-bold text-amber-600 dark:text-amber-500">{{ order.created_at_human }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Queue -->
                        <div class="md:hidden divide-y divide-primary/10">
                            <div v-for="order in unassignedOrders" :key="order.id" class="p-4 flex items-center justify-between bg-primary/5 active:bg-primary/10 transition-colors">
                                <div class="flex flex-col">
                                    <a :href="`/orders/${order.id}`" class="font-mono text-sm font-bold text-primary">{{ order.order_number }}</a>
                                    <span class="text-xs font-medium text-primary/80 mt-0.5">{{ order.client_name }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full border border-amber-200/50">{{ order.created_at_human }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="!unassignedOrders.length" class="flex flex-col items-center justify-center py-8 opacity-60">
                            <CheckCircle class="h-8 w-8 text-primary mb-2" />
                            <p class="text-xs font-bold text-primary">{{ t('All active orders are currently dispatched!') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Inventory Warnings -->
                <div class="rounded-xl border border-red-500/30 bg-red-50 dark:bg-red-950/20 dark:border-red-500/20">
                    <div class="flex items-center gap-2 border-b border-red-500/10 px-5 py-4">
                        <AlertTriangle class="h-4 w-4 text-red-500" />
                        <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">{{ t('Early Inventory Warnings') }}</h3>
                    </div>
                    <div class="p-0 flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-red-500/10">
                        <!-- Finished Products -->
                        <div class="flex-1 p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-red-500/80 mb-3">{{ t('Critical Products') }}</p>
                            <div v-if="lowStockProducts.length" class="space-y-3">
                                <div v-for="item in lowStockProducts" :key="item.id" class="flex items-center justify-between bg-white dark:bg-sidebar rounded-lg p-2 border border-red-500/20 shadow-sm">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <p class="text-[11px] font-bold text-foreground truncate">{{ tl(item.name) }}</p>
                                        <p class="text-[9px] font-mono text-muted-foreground mt-0.5">{{ item.sku }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-black text-red-600 dark:text-red-400">{{ item.quantity }}</p>
                                        <p class="text-[8px] uppercase tracking-wider text-red-500/60 font-medium">{{ t('Qty Left') }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-xs font-medium text-emerald-600 dark:text-emerald-500 flex items-center"><CheckCircle class="w-3 h-3 mr-1" /> {{ t('All product stock is healthy.') }}</p>
                        </div>
                        
                        <!-- Raw Materials -->
                        <div class="flex-1 p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-600/80 dark:text-amber-500/80 mb-3">{{ t('Lowest Raw Materials') }}</p>
                            <div v-if="lowStockRawMaterials.length" class="space-y-3">
                                <div v-for="item in lowStockRawMaterials" :key="item.id" class="flex items-center justify-between bg-white dark:bg-sidebar rounded-lg p-2 border border-amber-500/20 shadow-sm">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <p class="text-[11px] font-bold text-foreground truncate">{{ item.name }}</p>
                                        <p class="text-[9px] font-mono text-muted-foreground mt-0.5">{{ item.sku }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-black text-amber-600 dark:text-amber-500">{{ item.current_stock }} {{ item.unit }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-xs font-medium text-emerald-600 dark:text-emerald-500 flex items-center"><CheckCircle class="w-3 h-3 mr-1" /> {{ t('No raw materials tracked.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Table -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex items-center gap-2 border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                    <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold text-foreground">{{ t('Recent Orders') }}</h3>
                    <a href="/orders" class="ml-auto text-xs font-bold uppercase tracking-wider text-primary hover:underline">{{ t('View All') }} &rarr;</a>
                </div>
                
                <!-- Desktop Recent Orders -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Order #') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Client') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Status') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Total') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Paid') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in recentOrders"
                                :key="order.id"
                                class="border-b border-sidebar-border/40 transition-colors last:border-0 hover:bg-muted/30 dark:border-sidebar-border/30"
                            >
                                <td class="px-5 py-3">
                                    <a
                                        :href="`/orders/${order.id}`"
                                        class="font-mono text-xs font-medium text-primary hover:underline"
                                    >
                                        {{ order.order_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-xs text-foreground">{{ order.client_name }}</td>
                                <td class="px-5 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="statusConfig[order.status]?.color ?? 'text-muted-foreground bg-muted'"
                                    >
                                        <component
                                            v-if="statusConfig[order.status]"
                                            :is="statusConfig[order.status].icon"
                                            class="h-3 w-3"
                                        />
                                        {{ statusConfig[order.status]?.label ?? order.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs font-medium text-foreground">{{ fmtCurrency(order.total_amount) }}</td>
                                <td class="px-5 py-3 text-right text-xs">
                                    <span :class="order.paid_amount >= order.total_amount ? 'text-emerald-500' : 'text-amber-500'">
                                        {{ fmtCurrency(order.paid_amount) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-muted-foreground">{{ order.created_at_formatted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Recent Orders -->
                <div class="md:hidden divide-y divide-sidebar-border/40">
                    <div v-for="order in recentOrders" :key="order.id" class="p-4 active:bg-muted/30 transition-colors">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex flex-col">
                                <a :href="`/orders/${order.id}`" class="font-mono text-sm font-bold text-primary">{{ order.order_number }}</a>
                                <span class="text-xs font-medium text-foreground mt-0.5">{{ order.client_name }}</span>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                :class="statusConfig[order.status]?.color ?? 'text-muted-foreground bg-muted'"
                            >
                                {{ statusConfig[order.status]?.label ?? order.status }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-dashed border-sidebar-border/30">
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-widest">{{ t('Total') }}</span>
                                <span class="text-sm font-bold text-foreground">{{ fmtCurrency(order.total_amount) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-muted-foreground">{{ order.created_at_formatted }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="!recentOrders.length" class="px-5 py-8 text-center text-sm text-muted-foreground">{{ t('No orders yet.') }}</p>
            </div>

        </div>
    </AppLayout>
</template>
