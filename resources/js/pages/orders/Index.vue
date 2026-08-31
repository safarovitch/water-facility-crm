<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { index as guestIndex, show as guestShow } from '@/routes/orders';
import { index as adminIndex, show as adminShow, create as adminCreate, exportMethod as adminExport } from '@/routes/admin/orders';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlusCircle, Search, Eye, ShoppingCart, ChevronUp, ChevronDown, FileSpreadsheet, Package } from 'lucide-vue-next';
import { useTableSort } from '@/composables/useTableSort';
import MapChooser from '@/components/MapChooser.vue';
import { useI18n } from '@/composables/useI18n';

const { t, locale } = useI18n();
const adminMode = computed(() => !!usePage().props.adminMode);
const can = computed(() => usePage().props.auth.can ?? {});

const indexRoute = computed(() => adminMode.value ? adminIndex : guestIndex);
const showRoute = computed(() => adminMode.value ? adminShow : guestShow);
// Creating orders in the admin area needs manager/admin rights — plain
// couriers only work the orders assigned to them.
const createRoute = computed(() => adminMode.value && can.value.manageOrders ? adminCreate : null);

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Orders'), href: indexRoute.value().url },
]);

interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  total_amount: string;
  deposit_charge?: string | number;
  grand_total?: number;
  paid_amount: string;
  balance_due: number;
  delivery_date: string | null;
  delivery_address?: string | null;
  lat?: number | null;
  lng?: number | null;
  scheduled_delivery_at: string | null;
  scheduled_delivery_at_human: string | null;
  scheduled_delivery_at_formatted: string | null;
  created_at: string;
  created_at_human: string;
  created_at_formatted: string;
  client: { id: number; name: string; email: string };
  creator: { name: string } | null;
  items?: { id: number; quantity: number; product: { id: number; name: Record<string, string> | string } | null }[];
  returned_materials?: { id: number; pivot: { quantity: number; deferred_quantity?: number } }[];
}

interface Paginated<T> {
  data: T[];
  links: Record<string, any>;
  meta: Record<string, any>;
}

interface ItemTotals {
  total: number;
  products: { id: number; name: Record<string, string> | string; quantity: number }[];
}

const props = defineProps<{
  orders: Paginated<Order>;
  statuses: string[];
  itemTotals?: ItemTotals;
}>();

const statusFilter = ref('');
const paymentFilter = ref('');
const search = ref('');
const { sort, isSorted, getSortDirection, toggleSort, getSortIcon } = useTableSort();

const applyFilter = () => {
  router.get(indexRoute.value().url, {
    status: statusFilter.value || undefined,
    payment: paymentFilter.value || undefined,
    search: search.value || undefined,
    ...(sort.value && { sort_by: sort.value.column, sort_dir: sort.value.direction }),
  }, { preserveState: true, preserveScroll: true });
};

const handleSort = (column: string) => {
  toggleSort(column);
  const newSort = isSorted(column) ? sort.value : null;
  router.get(indexRoute.value().url, {
    status: statusFilter.value || undefined,
    payment: paymentFilter.value || undefined,
    search: search.value || undefined,
    ...(newSort && { sort_by: newSort.column, sort_dir: newSort.direction }),
  }, { preserveState: true, preserveScroll: true });
};

/**
 * Plain download link — the server builds the .xlsx from the same filters and
 * sort the table is using, so the sheet matches what is on screen. Admin path
 * only: the client-facing order list has no export.
 */
const exportUrl = computed(() => {
  const params = new URLSearchParams();

  if (statusFilter.value) params.set('status', statusFilter.value);
  if (paymentFilter.value) params.set('payment', paymentFilter.value);
  if (search.value) params.set('search', search.value);

  if (sort.value) {
    params.set('sort_by', sort.value.column);
    params.set('sort_dir', sort.value.direction);
  }

  params.set('locale', locale.value);

  return `${adminExport().url}?${params.toString()}`;
});

const statusBadge: Record<string, string> = {
  pending: 'secondary',
  confirmed: 'default',
  in_production: 'default',
  ready: 'default',
  delivered: 'default', // Actually let's use tailwind classes explicitly or map to badge variants
  cancelled: 'destructive',
};

const statusBadgeClass: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-100/80 dark:bg-yellow-900/40 dark:text-yellow-400',
  confirmed: 'bg-blue-100 text-blue-800 hover:bg-blue-100/80 dark:bg-blue-900/40 dark:text-blue-400',
  in_production: 'bg-purple-100 text-purple-800 hover:bg-purple-100/80 dark:bg-purple-900/40 dark:text-purple-400',
  ready: 'bg-cyan-100 text-cyan-800 hover:bg-cyan-100/80 dark:bg-cyan-900/40 dark:text-cyan-400',
  accepted: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-100/80 dark:bg-indigo-900/40 dark:text-indigo-400',
  in_transit: 'bg-sky-100 text-sky-800 hover:bg-sky-100/80 dark:bg-sky-900/40 dark:text-sky-400',
  delivered: 'bg-green-100 text-green-800 hover:bg-green-100/80 dark:bg-green-900/40 dark:text-green-400',
  cancelled: 'bg-red-100 text-red-800 hover:bg-red-100/80 dark:bg-red-900/40 dark:text-red-400',
};

const orderGrandTotal = (order: Order): number =>
  order.grand_total ?? Number(order.total_amount) + Number(order.deposit_charge ?? 0);

const formatAmount = (value: number): string => value.toFixed(2);

const totalItemCount = (order: Order): number =>
  (order.items ?? []).reduce((sum, item) => sum + Number(item.quantity ?? 0), 0);

const totalDeferredCount = (order: Order): number =>
  (order.returned_materials ?? []).reduce((sum, rm) => sum + Number(rm.pivot?.deferred_quantity ?? 0), 0);

const availableLocales = (usePage().props.available_locales as string[]) ?? [];

// Delivery date + 24h time, derived from the raw ISO timestamp so the table
// shows an unambiguous "15 Jun 2026" / "14:30" rather than a relative phrase.
const deliveryDate = (order: Order): string | null =>
  order.scheduled_delivery_at
    ? new Date(order.scheduled_delivery_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    : null;

const deliveryTime = (order: Order): string | null =>
  order.scheduled_delivery_at
    ? new Date(order.scheduled_delivery_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false })
    : null;

// The date the order was placed — the list's default sort. Formatted like the
// delivery column so the two dates read the same way.
const orderDate = (order: Order): string =>
  new Date(order.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

const orderTime = (order: Order): string =>
  new Date(order.created_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });


const productName = (name: Record<string, string> | string | null | undefined): string => {
  if (!name) return t('Item');
  if (typeof name === 'string') return name;
  for (const locale of availableLocales) {
    if (name[locale]) return name[locale];
  }
  return Object.values(name)[0] || t('Item');
};

// Unit totals for the whole filtered result set, not just this page — the
// server aggregates them so the badges stay honest across pagination.
const totalUnits = computed(() => props.itemTotals?.total ?? 0);

const productTotals = computed(() => props.itemTotals?.products ?? []);

const formatCount = (value: number): string => value.toLocaleString(locale.value === 'ru' ? 'ru-RU' : 'en-US');

const statusLabel = computed((): Record<string, string> => ({
  pending: t('Pending'),
  confirmed: t('Confirmed'),
  in_production: t('In Production'),
  ready: t('Ready'),
  accepted: t('Picked up'),
  in_transit: t('On the way'),
  delivered: t('Delivered'),
  cancelled: t('Cancelled'),
}));
</script>

<template>
  <Head :title="t('Orders')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-4 md:space-y-6 container mx-auto px-4 md:px-0">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">{{ t('Orders') }}</h1>
          <p class="text-sm text-muted-foreground mt-1">{{ t('Manage processing, ready, and delivered orders.') }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
          <Button v-if="adminMode" as-child variant="outline" class="w-full sm:w-auto gap-2 shadow-sm font-semibold rounded-xl h-11 md:h-10">
            <a :href="exportUrl" download>
              <FileSpreadsheet class="h-4 w-4" /> {{ t('Export to Excel') }}
            </a>
          </Button>
          <Link v-if="createRoute" :href="createRoute().url" class="w-full sm:w-auto">
            <Button class="w-full sm:w-auto gap-2 shadow-sm font-semibold rounded-xl h-11 md:h-10">
              <PlusCircle class="h-4 w-4" /> {{ t('New Order') }}
            </Button>
          </Link>
        </div>
      </div>

      <!-- Unit totals for the filter in effect. Aggregated server-side over
           every matching order, so the numbers do not change as you page. -->
      <div v-if="totalUnits > 0" class="flex flex-wrap items-center gap-2">
        <Badge variant="outline" class="h-7 gap-1.5 rounded-lg border-transparent bg-primary/10 px-3 text-primary">
          <Package />
          <span class="font-medium">{{ t('Total units') }}</span>
          <span class="font-bold tabular-nums">{{ formatCount(totalUnits) }}</span>
        </Badge>
        <Badge
          v-for="product in productTotals"
          :key="product.id"
          variant="outline"
          class="h-7 gap-1.5 rounded-lg px-3"
        >
          <span class="max-w-[10rem] truncate font-medium text-foreground" :title="productName(product.name)">{{ productName(product.name) }}</span>
          <span class="font-bold tabular-nums text-muted-foreground">×{{ formatCount(product.quantity) }}</span>
        </Badge>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 grid grid-cols-1 md:flex md:flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-full md:w-64 max-w-sm">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Search') }}</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="search" :placeholder="t('Order # or client...')" class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="applyFilter" />
                </div>
                <div class="space-y-1 w-full md:w-48">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Status') }}</Label>
                    <select v-model="statusFilter" @change="applyFilter" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option value="">{{ t('All Statuses') }}</option>
                        <option v-for="s in props.statuses" :key="s" :value="s" class="capitalize">{{ statusLabel[s] ?? s.replace('_', ' ') }}</option>
                    </select>
                </div>
                <div class="space-y-1 w-full md:w-48">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Payment') }}</Label>
                    <select v-model="paymentFilter" @change="applyFilter" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option value="">{{ t('All Orders') }}</option>
                        <option value="unpaid">{{ t('Unpaid') }}</option>
                    </select>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <Button @click="applyFilter" variant="secondary" size="sm" class="h-10 md:h-9 flex-1 md:flex-none">{{ t('Apply Filters') }}</Button>
                </div>
            </div>

            <!-- Table (Desktop) -->
            <div class="hidden md:block relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('order_number')">
                                <div class="flex items-center gap-2">{{ t('Order #') }} <span class="text-xs">{{ isSorted('order_number') ? getSortIcon('order_number') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('created_at')">
                                <div class="flex items-center gap-2">{{ t('Order date') }} <span class="text-xs">{{ isSorted('created_at') ? getSortIcon('created_at') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('client_id')">
                                <div class="flex items-center gap-2">{{ t('Client') }} <span class="text-xs">{{ isSorted('client_id') ? getSortIcon('client_id') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold">{{ t('Address') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Items') }}</th>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('status')">
                                <div class="flex items-center gap-2">{{ t('Status') }} <span class="text-xs">{{ isSorted('status') ? getSortIcon('status') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold text-right cursor-pointer hover:text-foreground transition-colors" @click="handleSort('total_amount')">
                                <div class="flex items-center justify-end gap-2">{{ t('Total') }} <span class="text-xs">{{ isSorted('total_amount') ? getSortIcon('total_amount') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold text-right cursor-pointer hover:text-foreground transition-colors" @click="handleSort('balance_due')">
                                <div class="flex items-center justify-end gap-2">{{ t('Balance Due') }} <span class="text-xs">{{ isSorted('balance_due') ? getSortIcon('balance_due') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('scheduled_delivery_at')">
                                <div class="flex items-center gap-2">{{ t('Delivery') }} <span class="text-xs">{{ isSorted('scheduled_delivery_at') ? getSortIcon('scheduled_delivery_at') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="order in orders.data" :key="order.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4 font-mono font-bold text-gray-900 dark:text-white">
                                {{ order.order_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900 dark:text-white">{{ orderDate(order) }}</div>
                                <div class="text-xs text-muted-foreground mt-0.5 tabular-nums">{{ orderTime(order) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900 dark:text-white">{{ order.contact_name || order.client?.name }}</div>
                                <div class="text-xs text-muted-foreground mt-0.5">{{ order.contact_phone || order.client?.phone || '—' }}</div>
                            </td>
                            <td class="px-6 py-4 max-w-[18rem] text-sm">
                                <MapChooser :lat="order.lat" :lng="order.lng" :address="order.delivery_address" />
                            </td>
                            <td class="px-6 py-4 max-w-[16rem]">
                                <div v-if="order.items && order.items.length" class="space-y-0.5">
                                    <div v-for="item in order.items" :key="item.id" class="text-sm text-gray-700 dark:text-gray-300 truncate" :title="productName(item.product?.name)">
                                        {{ productName(item.product?.name) }} <span class="text-muted-foreground">×{{ item.quantity }}</span>
                                    </div>
                                    <div class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ totalItemCount(order) }} {{ t('total') }}</div>
                                    <div v-if="totalDeferredCount(order) > 0" class="text-[10px] text-amber-600 dark:text-amber-400 font-bold">⏳ {{ totalDeferredCount(order) }} {{ t('to pickup') }}</div>
                                </div>
                                <span v-else class="text-sm text-muted-foreground">—</span>
                            </td>
                            <td class="px-6 py-4">
                                <Badge variant="outline" class="capitalize border-transparent relative font-semibold" :class="statusBadgeClass[order.status]">
                                    {{ statusLabel[order.status] ?? order.status.replace('_', ' ') }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatAmount(orderGrandTotal(order)) }}</span>
                                <span class="text-[10px] text-muted-foreground ml-1">{{ $page.props.currency }}</span>
                                <div
                                    v-if="Number(order.deposit_charge ?? 0) > 0"
                                    class="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5"
                                >
                                    {{ t('incl.') }} {{ formatAmount(Number(order.deposit_charge)) }} {{ t('deposit') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="font-bold" :class="order.balance_due > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                                    {{ order.balance_due > 0 ? order.balance_due.toFixed(2) : '—' }}
                                </span>
                                <span v-if="order.balance_due > 0" class="text-[10px] text-muted-foreground ml-1">{{ $page.props.currency }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <template v-if="deliveryDate(order)">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ deliveryDate(order) }}</div>
                                    <div class="text-xs text-muted-foreground mt-0.5 tabular-nums">{{ deliveryTime(order) }}</div>
                                </template>
                                <div v-else class="text-xs text-muted-foreground">{{ order.delivery_date ?? '—' }}</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="showRoute(order.id).url">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40" :title="t('View Order')">
                                          <Eye class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="md:hidden divide-y divide-border/60">
                <div v-for="order in orders.data" :key="order.id" class="p-4 bg-white dark:bg-background active:bg-muted/30 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex flex-col">
                            <span class="font-mono font-bold text-primary">{{ order.order_number }}</span>
                            <span class="text-[10px] text-muted-foreground tabular-nums">{{ orderDate(order) }} · {{ orderTime(order) }}</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white mt-1">{{ order.contact_name || order.client?.name }}</span>
                            <span class="text-[10px] text-muted-foreground">{{ order.contact_phone || order.client?.phone || '—' }}</span>
                        </div>
                        <Badge variant="outline" class="capitalize whitespace-nowrap text-[10px] h-5 px-1.5 shrink-0 font-semibold" :class="statusBadgeClass[order.status]">
                            {{ statusLabel[order.status] ?? order.status.replace('_', ' ') }}
                        </Badge>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Total') }}</span>
                            <span class="font-bold text-sm text-gray-900 dark:text-white">{{ formatAmount(orderGrandTotal(order)) }} <span class="text-[10px] font-normal">{{ $page.props.currency }}</span></span>
                            <span
                                v-if="Number(order.deposit_charge ?? 0) > 0"
                                class="text-[10px] text-blue-600 dark:text-blue-400"
                            >
                                {{ t('incl.') }} {{ formatAmount(Number(order.deposit_charge)) }} {{ t('deposit') }}
                            </span>
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Balance Due') }}</span>
                            <span class="font-bold text-sm" :class="order.balance_due > 0 ? 'text-red-500' : 'text-gray-900 dark:text-white'">
                                {{ order.balance_due > 0 ? order.balance_due.toFixed(2) : t('Paid') }} 
                                <span v-if="order.balance_due > 0" class="text-[10px] font-normal">{{ $page.props.currency }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-dashed border-border/60 space-y-3">
                        <div v-if="order.items && order.items.length" class="flex flex-col">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Items') }} ({{ totalItemCount(order) }})</span>
                            <div class="text-xs text-gray-700 dark:text-gray-300 space-y-0.5 mt-0.5">
                                <div v-for="item in order.items" :key="item.id">
                                    {{ productName(item.product?.name) }} <span class="text-muted-foreground">×{{ item.quantity }}</span>
                                </div>
                            </div>
                            <div v-if="totalDeferredCount(order) > 0" class="text-[10px] text-amber-600 dark:text-amber-400 font-bold mt-1">⏳ {{ totalDeferredCount(order) }} {{ t('to pickup') }}</div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Address') }}</span>
                            <MapChooser :lat="order.lat" :lng="order.lng" :address="order.delivery_address" class="text-xs" />
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Delivery') }}</span>
                                <span class="text-xs text-gray-700 dark:text-gray-300">
                                    <template v-if="deliveryDate(order)">{{ deliveryDate(order) }} · <span class="tabular-nums">{{ deliveryTime(order) }}</span></template>
                                    <template v-else>{{ t('Not scheduled') }}</template>
                                </span>
                            </div>
                            <Link :href="showRoute(order.id).url">
                              <Button variant="secondary" size="sm" class="h-9 px-4 rounded-lg shadow-sm border border-border/50">
                                  <Eye class="h-4 w-4 mr-1.5" /> {{ t('View') }}
                              </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="orders.data.length === 0" class="px-6 py-12 text-center text-muted-foreground">
                <div class="flex flex-col items-center justify-center opacity-60">
                    <ShoppingCart class="h-10 w-10 mb-3 text-gray-400" />
                    <p class="font-medium text-sm">{{ t('No orders found.') }}</p>
                </div>
            </div>

            <!-- Pagination -->
            <Pagination :paginator="orders" />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
