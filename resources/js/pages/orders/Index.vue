<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { index, create, show } from '@/routes/orders';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: index().url },
];

interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  total_amount: string;
  paid_amount: string;
  balance_due: number;
  delivery_date: string | null;
  created_at: string;
  client: { id: number; name: string; email: string };
  creator: { name: string } | null;
}

interface Paginated<T> {
  data: T[];
  links: Record<string, any>;
  meta: Record<string, any>;
}

const props = defineProps<{
  orders: Paginated<Order>;
  statuses: string[];
}>();

const statusFilter = ref('');
const search = ref('');

const applyFilter = () => {
  router.get(index().url, {
    status: statusFilter.value || undefined,
    search: search.value || undefined,
  }, { preserveState: true });
};

const statusBadge: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  in_production: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
  ready: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
</script>

<template>

  <Head title="Orders" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="p-4 flex items-center justify-between flex-wrap gap-3 pb-4 bg-white dark:bg-gray-900">
        <div class="flex gap-2 flex-wrap">
          <Link :href="create().url" class="inline-flex items-center text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:bg-gray-700">
            + New Order
          </Link>
          <select v-model="statusFilter" @change="applyFilter" class="border border-gray-300 rounded-lg text-sm px-3 py-1.5 bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 capitalize">
            <option value="">All Statuses</option>
            <option v-for="s in props.statuses" :key="s" :value="s" class="capitalize">{{ s.replace('_', ' ') }}</option>
          </select>
        </div>
        <div class="relative">
          <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
            <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
            </svg>
          </div>
          <input v-model="search" @keyup.enter="applyFilter" type="text" placeholder="Search client..." class="block p-2 ps-10 text-sm border border-gray-300 rounded-lg w-64 bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
        </div>
      </div>

      <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
          <tr>
            <th class="px-6 py-3">Order #</th>
            <th class="px-6 py-3">Client</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Total</th>
            <th class="px-6 py-3">Balance Due</th>
            <th class="px-6 py-3">Delivery</th>
            <th class="px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in props.orders.data" :key="order.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            <td class="px-6 py-4 font-mono font-semibold text-gray-900 dark:text-white">
              {{ order.order_number }}
            </td>
            <td class="px-6 py-4">
              <div class="font-medium text-gray-900 dark:text-white">{{ order.client?.name }}</div>
              <div class="text-xs text-gray-500">{{ order.client?.email }}</div>
            </td>
            <td class="px-6 py-4">
              <span class="text-xs font-medium px-2.5 py-0.5 rounded-full" :class="statusBadge[order.status] ?? ''">
                {{ order.status.replace('_', ' ') }}
              </span>
            </td>
            <td class="px-6 py-4">{{ order.total_amount }}</td>
            <td class="px-6 py-4" :class="{ 'text-red-600 font-semibold': order.balance_due > 0 }">
              {{ order.balance_due > 0 ? order.balance_due.toFixed(2) : '—' }}
            </td>
            <td class="px-6 py-4">{{ order.delivery_date ?? '—' }}</td>
            <td class="px-6 py-4">
              <Link :href="show(order.id).url" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View</Link>
            </td>
          </tr>
          <tr v-if="props.orders.data.length === 0">
            <td colspan="7" class="px-6 py-10 text-center text-gray-400">No orders found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
