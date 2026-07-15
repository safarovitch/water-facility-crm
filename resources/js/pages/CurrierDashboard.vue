<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';
import {
    ShoppingCart, Truck, DollarSign, CalendarClock,
    Clock, CheckCircle, XCircle, Factory, ArrowUpRight, MapPin
} from 'lucide-vue-next';

interface CourierStats {
    activeOrders: number;
    deliveredToday: number;
    deliveredMonth: number;
    revenueToday: number;
    revenueMonth: number;
}

interface UpcomingDelivery {
    id: number;
    order_number: string;
    client_name: string;
    delivery_address: string | null;
    status: string;
    total_amount: number;
    paid_amount: number;
    scheduled_delivery_at: string | null;
    created_at_human: string;
}

defineProps<{
    stats: CourierStats;
    upcomingDeliveries: UpcomingDelivery[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const { t } = useLocale();

const fmt = (n: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n);
const fmtCurrency = (n: number) => {
    const currency = (usePage().props.currency as string) || 'USD';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
};

const fmtDate = (iso: string | null) => {
    if (!iso) return t('Not scheduled');
    return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const statusConfig: Record<string, { label: string; color: string; icon: any }> = {
    pending:       { label: 'Pending',       color: 'text-amber-500 bg-amber-500/10',   icon: Clock },
    confirmed:     { label: 'Confirmed',     color: 'text-blue-500 bg-blue-500/10',     icon: CheckCircle },
    in_production: { label: 'In Production', color: 'text-purple-500 bg-purple-500/10', icon: Factory },
    ready:         { label: 'Ready',         color: 'text-green-500 bg-green-500/10',   icon: ArrowUpRight },
    accepted:      { label: 'Picked up',     color: 'text-indigo-500 bg-indigo-500/10', icon: Truck },
    in_transit:    { label: 'On the way',    color: 'text-sky-500 bg-sky-500/10',       icon: Truck },
    delivered:     { label: 'Delivered',     color: 'text-emerald-500 bg-emerald-500/10', icon: Truck },
    cancelled:     { label: 'Cancelled',     color: 'text-red-500 bg-red-500/10',       icon: XCircle },
};
</script>

<template>
    <Head title="My Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">

            <!-- KPI cards: strictly the courier's own workload -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('My Active Orders') }}</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.activeOrders) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ t('assigned to you') }}</p>
                        </div>
                        <div class="rounded-lg bg-primary/10 p-2.5">
                            <ShoppingCart class="h-5 w-5 text-primary" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Delivered Today') }}</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmt(stats.deliveredToday) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ fmt(stats.deliveredMonth) }} {{ t('this month') }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-500/10 p-2.5">
                            <Truck class="h-5 w-5 text-emerald-500" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Collected Today') }}</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmtCurrency(stats.revenueToday) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ t('from your deliveries') }}</p>
                        </div>
                        <div class="rounded-lg bg-green-500/10 p-2.5">
                            <DollarSign class="h-5 w-5 text-green-500" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('Collected This Month') }}</p>
                            <p class="mt-2 text-3xl font-bold text-foreground">{{ fmtCurrency(stats.revenueMonth) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ t('from your deliveries') }}</p>
                        </div>
                        <div class="rounded-lg bg-sky-500/10 p-2.5">
                            <DollarSign class="h-5 w-5 text-sky-500" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming deliveries -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex items-center justify-between border-b border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div class="flex items-center gap-2">
                        <CalendarClock class="h-5 w-5 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">{{ t('Upcoming Deliveries') }}</h2>
                    </div>
                    <Link href="/admin/orders/index" class="text-sm text-primary hover:underline">{{ t('View all') }}</Link>
                </div>

                <div v-if="upcomingDeliveries.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                    {{ t('No upcoming deliveries assigned to you.') }}
                </div>

                <ul v-else class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                    <li v-for="order in upcomingDeliveries" :key="order.id">
                        <Link :href="`/admin/orders/${order.id}`" class="flex items-start justify-between gap-3 p-4 transition-colors hover:bg-muted/50">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-foreground">#{{ order.order_number }}</span>
                                    <span
                                        v-if="statusConfig[order.status]"
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="statusConfig[order.status].color"
                                    >
                                        <component :is="statusConfig[order.status].icon" class="h-3 w-3" />
                                        {{ t(statusConfig[order.status].label) }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm text-muted-foreground">{{ order.client_name }}</p>
                                <p v-if="order.delivery_address" class="mt-0.5 flex items-center gap-1 truncate text-xs text-muted-foreground">
                                    <MapPin class="h-3 w-3 shrink-0" />
                                    {{ order.delivery_address }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold text-foreground">{{ fmtCurrency(order.total_amount) }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ fmtDate(order.scheduled_delivery_at) }}</p>
                            </div>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
