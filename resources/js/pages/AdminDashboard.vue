<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';
import {
    ShoppingCart, TrendingUp, Users2, Package,
    Clock, CheckCircle, AlertCircle, XCircle,
    Truck, Factory, DollarSign, ArrowUpRight, BarChart3,
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
    created_at: string;
}

interface TopProduct {
    id: number;
    name: string;
    total_sold: number;
}

const props = defineProps<{
    stats: Stats;
    recentOrders: RecentOrder[];
    topProducts: TopProduct[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const { t } = useLocale();

const fmt = (n: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n);
const fmtCurrency = (n: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'TJS', maximumFractionDigits: 0 }).format(n);

const statusConfig: Record<string, { label: string; color: string; icon: any }> = {
    pending:      { label: 'Pending',      color: 'text-amber-500   bg-amber-500/10',   icon: Clock },
    confirmed:    { label: 'Confirmed',    color: 'text-blue-500    bg-blue-500/10',    icon: CheckCircle },
    in_production:{ label: 'In Production',color: 'text-purple-500  bg-purple-500/10',  icon: Factory },
    ready:        { label: 'Ready',        color: 'text-green-500   bg-green-500/10',   icon: ArrowUpRight },
    delivered:    { label: 'Delivered',    color: 'text-emerald-500 bg-emerald-500/10', icon: Truck },
    cancelled:    { label: 'Cancelled',    color: 'text-red-500     bg-red-500/10',     icon: XCircle },
};

const allStatuses = ['pending', 'confirmed', 'in_production', 'ready', 'delivered', 'cancelled'];
const maxStatusCount = Math.max(...allStatuses.map(s => props.stats.ordersByStatus[s] ?? 0), 1);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">

            <!-- KPI Cards Row 1 -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <!-- Total Orders -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Total Orders</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.totalOrders) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ stats.todayOrders }} today · {{ stats.thisMonthOrders }} this month</p>
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
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Revenue Collected</p>
                            <p class="mt-2 text-2xl font-bold text-foreground">{{ fmtCurrency(stats.totalRevenue) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ fmtCurrency(stats.monthRevenue) }} this month</p>
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
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Outstanding</p>
                            <p class="mt-2 text-2xl font-bold text-foreground">{{ fmtCurrency(stats.totalOutstanding) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">unpaid balance</p>
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
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Clients</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.totalClients) }}</p>
                            <p class="mt-1 text-xs text-emerald-500">+{{ stats.newClientsMonth }} this month</p>
                        </div>
                        <div class="rounded-lg bg-blue-500/10 p-2.5">
                            <Users2 class="h-5 w-5 text-blue-500" />
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
                        <h3 class="text-sm font-semibold text-foreground">Orders by Status</h3>
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
                                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Products</p>
                                <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.totalProducts) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ stats.activeProducts }} active</p>
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
                            <h3 class="text-sm font-semibold text-foreground">Top Products</h3>
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
                                <span class="flex-1 truncate text-xs font-medium text-foreground">{{ t(product.name) }}</span>
                                <span class="text-xs text-muted-foreground">{{ product.total_sold }} sold</span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">No sales data yet.</p>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Table -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex items-center gap-2 border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                    <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold text-foreground">Recent Orders</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Order #</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Client</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">Total</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">Paid</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">Date</th>
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
                                <td class="px-5 py-3 text-right text-xs text-muted-foreground">{{ order.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!recentOrders.length" class="px-5 py-8 text-center text-sm text-muted-foreground">No orders yet.</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
