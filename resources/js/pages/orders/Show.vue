<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import { index, edit, cancel, updateStatus } from '@/routes/orders';
import { computed } from 'vue';

interface UserProfile { company_name: string | null; region: string | null; }
interface OrderItem {
  id: number;
  quantity: number;
  unit_price: string;
  subtotal: string;
  product: { name: Record<string, string> | string };
}
interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  total_amount: string;
  paid_amount: string;
  balance_due: number;
  delivery_date: string | null;
  delivery_address: string | null;
  notes: string | null;
  created_at: string;
  client: { id: number; name: string; email: string; phone: string | null; user_profile: UserProfile | null };
  creator: { name: string } | null;
  items: OrderItem[];
}

const props = defineProps<{ order: Order; statuses: string[]; }>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: index().url },
  { title: props.order.order_number, href: '#' },
];

const statusBadge: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  in_production: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
  ready: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const validTransitions: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['in_production', 'cancelled'],
  in_production: ['ready', 'cancelled'],
  ready: ['delivered'],
  delivered: [],
  cancelled: [],
};

const nextStatuses = computed(() => validTransitions[props.order.status] ?? []);

const changeStatus = (status: string) => {
  if (status === 'cancelled') {
    if (!confirm('Cancel this order?')) return;
    router.patch(cancel(props.order.id).url, {}, { preserveScroll: true });
  } else {
    router.patch(updateStatus(props.order.id).url, { status }, { preserveScroll: true });
  }
};

const resolveProductName = (name: Record<string, string> | string): string => {
  if (typeof name === 'string') return name;
  return name?.['en'] ?? name?.['uz'] ?? Object.values(name)[0] ?? '—';
};

const statusButtonClass = (s: string) => {
  if (s === 'cancelled') return 'bg-red-600 hover:bg-red-700 text-white';
  if (s === 'delivered') return 'bg-green-600 hover:bg-green-700 text-white';
  return 'bg-blue-600 hover:bg-blue-700 text-white';
};
</script>

<template>

  <Head :title="order.order_number" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 p-4">

      <!-- Header -->
      <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono">{{ order.order_number }}</h1>
            <p class="text-sm text-gray-500 mt-1">Created {{ order.created_at }} by {{ order.creator?.name ?? 'System' }}</p>
          </div>
          <div class="flex items-center gap-3 flex-wrap">
            <span class="text-sm font-medium px-3 py-1 rounded-full" :class="statusBadge[order.status]">
              {{ order.status.replace('_', ' ') }}
            </span>
            <!-- Status transitions -->
            <button v-for="s in nextStatuses" :key="s" @click="changeStatus(s)" class="text-sm px-3 py-1 rounded-lg font-medium capitalize transition-colors" :class="statusButtonClass(s)">
              {{ s === 'in_production' ? 'Start Production' : s.replace('_', ' ') }}
            </button>
            <Link v-if="['pending', 'confirmed'].includes(order.status)" :href="edit(order.id).url" class="text-sm px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 font-medium">
              Edit
            </Link>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Client info -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Client</h2>
          <p class="font-semibold text-gray-900 dark:text-white">{{ order.client.name }}</p>
          <p class="text-sm text-gray-500">{{ order.client.email }}</p>
          <p class="text-sm text-gray-500" v-if="order.client.phone">{{ order.client.phone }}</p>
          <p class="text-sm text-gray-500 mt-1" v-if="order.client.user_profile?.region">
            📍 {{ order.client.user_profile.region }}
          </p>
        </div>

        <!-- Delivery -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Delivery</h2>
          <p class="text-sm text-gray-700 dark:text-gray-300">
            <span class="font-medium">Date:</span> {{ order.delivery_date ?? '—' }}
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
            <span class="font-medium">Address:</span> {{ order.delivery_address ?? '—' }}
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-1" v-if="order.notes">
            <span class="font-medium">Notes:</span> {{ order.notes }}
          </p>
        </div>

        <!-- Payment summary -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Payment</h2>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">Total</span>
              <span class="font-semibold text-gray-900 dark:text-white">{{ order.total_amount }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Paid</span>
              <span class="text-green-600 font-medium">{{ order.paid_amount }}</span>
            </div>
            <div class="flex justify-between border-t dark:border-gray-700 pt-1 mt-1">
              <span class="text-gray-700 dark:text-gray-300 font-medium">Balance due</span>
              <span :class="order.balance_due > 0 ? 'text-red-600 font-bold' : 'text-green-600 font-bold'">
                {{ order.balance_due.toFixed(2) }}
              </span>
            </div>
          </div>
          <div class="mt-2">
            <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="order.payment_status === 'paid'
              ? 'bg-green-100 text-green-700'
              : order.payment_status === 'partial'
                ? 'bg-yellow-100 text-yellow-700'
                : 'bg-red-100 text-red-700'">
              {{ order.payment_status }}
            </span>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b dark:border-gray-700">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Items</h2>
        </div>
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th class="px-6 py-3">Product</th>
              <th class="px-6 py-3 text-right">Qty</th>
              <th class="px-6 py-3 text-right">Unit Price</th>
              <th class="px-6 py-3 text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id" class="border-b dark:border-gray-700">
              <td class="px-6 py-3 text-gray-900 dark:text-white">{{ resolveProductName(item.product.name) }}</td>
              <td class="px-6 py-3 text-right">{{ item.quantity }}</td>
              <td class="px-6 py-3 text-right">{{ item.unit_price }}</td>
              <td class="px-6 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ item.subtotal }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 dark:bg-gray-700">
              <td colspan="3" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total</td>
              <td class="px-6 py-3 text-right font-bold text-gray-900 dark:text-white">{{ order.total_amount }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

    </div>
  </AppLayout>
</template>
