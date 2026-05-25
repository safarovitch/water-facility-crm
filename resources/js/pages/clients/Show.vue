<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { edit } from '@/routes/admin/clients';
import { useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useIntersectionObserver, onClickOutside } from '@vueuse/core';
import axios from 'axios';
import { Phone, Edit, MapPin, Building, User as UserIcon, Calendar, Clock, Wallet, Plus, X, RotateCcw, ShoppingBag, Package, Loader2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';

const isDepositModalOpen = ref(false);

const depositForm = useForm({
    amount: 100,
    notes: 'Admin deposit',
});

const submitDeposit = () => {
  depositForm.post(`/admin/users/${props.client.id}/wallet/deposit`, {
    onSuccess: () => {
      isDepositModalOpen.value = false;
      depositForm.reset();
    },
  });
};

interface UserAddress {
  id: number;
  label: string;
  address_line: string;
  city: string | null;
  region: string | null;
  is_default: boolean;
}

interface ClientPhone {
  id: number;
  label: string;
  phone: string;
  is_default: boolean;
}

interface ActivityLog {
  id: number;
  description: string;
  created_at: string;
  properties: {
    duration?: number;
    direction?: 'inbound' | 'outbound';
    phone?: string;
    status?: string;
    recording_url?: string;
  };
}

interface OrderItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: string | number;
  subtotal: string | number;
  product?: {
    id: number;
    name: string;
  };
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  total_amount: string | number;
  scheduled_delivery_at: string | null;
  created_at: string;
  items: OrderItem[];
}

interface PaginatedOrders {
  data: Order[];
  next_page_url: string | null;
  current_page: number;
  last_page: number;
  total: number;
}

interface Transaction {
  id: number;
  type: string;
  amount: string | number;
  status: string;
  notes?: string;
  meta: any;
  created_at: string;
}

interface Client {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string;
  user_profile: {
    type: string;
    company_name: string | null;
    region: string | null;
    notes: string | null;
  } | null;
  phones: ClientPhone[];
  addresses: UserAddress[];
  wallet?: {
    balance: string | number;
    currency: string;
  };
}

const props = defineProps<{
  client: Client;
  calls: ActivityLog[];
  transactions: Transaction[];
  orders: PaginatedOrders;
  statuses: string[];
}>();

const allOrders = ref<Order[]>([...props.orders.data]);
const nextPageUrl = ref<string | null>(props.orders.next_page_url);
const isLoadingMore = ref(false);
const loadMoreTrigger = ref<HTMLElement | null>(null);

const loadMoreOrders = async () => {
  if (!nextPageUrl.value || isLoadingMore.value) return;

  isLoadingMore.value = true;
  try {
    const response = await axios.get(nextPageUrl.value);
    const data = response.data;
    
    allOrders.value.push(...data.data);
    nextPageUrl.value = data.next_page_url;
  } catch (error) {
    console.error('Failed to load more orders:', error);
  } finally {
    isLoadingMore.value = false;
  }
};

useIntersectionObserver(
  loadMoreTrigger,
  ([{ isIntersecting }]) => {
    if (isIntersecting) {
      loadMoreOrders();
    }
  }
);

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clients', href: '/admin/clients/index' },
  { title: props.client.name, href: '#' },
];

const isCompany = computed(() => props.client.user_profile?.type === 'company');

const initiateCall = (phone: string | null) => {
  if (phone && typeof window !== 'undefined' && window.initiateAsteriskCall) {
    window.initiateAsteriskCall(phone);
  }
};

const formatDuration = (seconds?: number) => {
  if (seconds === undefined) return '';
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = (seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
};
</script>

<template>

  <Head :title="client.name" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-8">

      <!-- Header Section -->
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex items-start gap-4">
          <div class="h-16 w-16 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-sm flex-shrink-0">
            <Building v-if="isCompany" class="w-8 h-8" />
            <UserIcon v-else class="w-8 h-8" />
          </div>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ client.name }}</h1>
              <span class="px-2.5 py-1 text-xs font-semibold rounded-md shadow-sm border bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 uppercase tracking-wider">
                {{ client.user_profile?.type || 'Individual' }}
              </span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2">
              <Calendar class="w-4 h-4 opacity-70" />
              Customer since {{ new Date(client.created_at).getFullYear() }}
            </p>
          </div>
        </div>

        <Link :href="edit(client.id).url">
          <Button variant="outline" class="flex items-center gap-2">
            <Edit class="w-4 h-4" />
            Edit Profile
          </Button>
        </Link>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

        <!-- Sidebar Details -->
        <div class="space-y-6">

          <!-- Contact Details -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b pb-2 dark:border-gray-700">Contact Details</h2>

            <div class="space-y-4">
              <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Email</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ client.email }}</p>
              </div>

              <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Phone Numbers</p>
                <div v-if="client.phones && client.phones.length > 0" class="space-y-2">
                  <div v-for="phone in client.phones" :key="phone.id" class="flex items-center justify-between group py-1.5 px-3 -mx-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-3">
                      <div class="bg-primary/10 p-1.5 rounded-full text-primary">
                        <Phone class="w-4 h-4" />
                      </div>
                      <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                          {{ phone.phone }}
                          <span v-if="phone.is_default" class="text-[10px] bg-green-100 text-green-700 px-1.5 rounded uppercase font-bold tracking-wider">Primary</span>
                        </p>
                        <p class="text-xs text-gray-500 truncate">{{ phone.label }}</p>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity rounded-full text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/30 dark:text-green-400" @click="initiateCall(phone.phone)" title="Call">
                      <Phone class="w-4 h-4 fill-current" />
                    </Button>
                  </div>
                </div>
                <p v-else class="text-sm text-gray-500 italic">No phone numbers assigned.</p>
              </div>
            </div>
          </div>

          <!-- Addresses -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b pb-2 dark:border-gray-700">Addresses</h2>
            <div v-if="client.addresses && client.addresses.length > 0" class="space-y-4">
              <div v-for="address in client.addresses" :key="address.id" class="flex gap-3 items-start border-l-2 border-gray-100 dark:border-gray-700 pl-3 py-1">
                <MapPin class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <div>
                  <div class="flex items-center gap-2 mb-0.5">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ address.label }}</p>
                    <span v-if="address.is_default" class="text-[10px] bg-blue-100 text-blue-700 px-1.5 rounded uppercase font-bold tracking-wider">Default</span>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ address.address_line }}</p>
                  <p v-if="address.city || address.region" class="text-xs text-gray-500 mt-0.5">{{ address.city }}{{ address.city && address.region ? ', ' : '' }}{{ address.region }}</p>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No addresses assigned.</p>
          </div>

          <!-- Financial Overview (Wallet, Credit, Transactions) -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-900/20">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <Wallet class="w-4 h-4 text-gray-400" />
                Financial Overview
              </h2>
            </div>
            
            <div class="p-6 space-y-6">
              <!-- Wallet Balance -->
              <div class="p-4 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-900/40 space-y-3">
                <div>
                  <p class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-1">Available Balance</p>
                  <p class="text-xl font-black text-gray-900 dark:text-white">
                    {{ client.wallet?.balance ?? '0.00' }} <span class="text-xs font-medium opacity-70">{{ client.wallet?.currency ?? 'TJS' }}</span>
                  </p>
                </div>
                <Button variant="outline" size="sm" class="w-full bg-white dark:bg-gray-800 border-blue-200 dark:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-blue-600 dark:text-blue-400 gap-2 h-9 shadow-sm" @click="isDepositModalOpen = true">
                  <Plus class="w-4 h-4" />
                  Add Funds
                </Button>
              </div>

              <div>
                <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-3">Recent Transactions</h3>
                <div class="p-0 max-h-[250px] overflow-y-auto -mx-6 px-6">
                  <div v-if="transactions.length === 0" class="py-4 text-center text-xs text-gray-500">
                    No transactions found.
                  </div>
                  <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800 border-t dark:border-gray-700">
                    <li v-for="tx in transactions" :key="tx.id" class="py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group flex items-center justify-between">
                      <div class="flex items-center gap-3">
                        <div class="p-1.5 rounded-full" :class="{
                          'bg-green-100 text-green-600 dark:bg-green-900/30': tx.type === 'deposit' || tx.type === 'refund',
                          'bg-red-100 text-red-600 dark:bg-red-900/30': tx.type === 'withdrawal' || tx.type === 'payment'
                        }">
                          <Plus v-if="tx.type === 'deposit'" class="w-3 h-3" />
                          <X v-else-if="tx.type === 'withdrawal' || tx.type === 'payment'" class="w-3 h-3" />
                          <RotateCcw v-else class="w-3 h-3" />
                        </div>
                        <div>
                          <p class="text-xs font-bold text-gray-900 dark:text-white capitalize">{{ tx.type }}</p>
                          <p class="text-[10px] text-gray-500">{{ formatDate(tx.created_at) }}</p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="text-xs font-black" :class="tx.type === 'deposit' ? 'text-green-600' : 'text-red-500'">
                          {{ tx.type === 'deposit' ? '+' : '-' }}{{ tx.amount }}
                        </p>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 uppercase font-bold">{{ tx.status }}</span>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Internal Notes (if any) -->
          <div v-if="client.user_profile?.notes" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b pb-2 dark:border-gray-700">Internal Notes</h2>
            <p class="text-sm text-gray-700 dark:text-gray-300 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg border border-yellow-100 dark:border-yellow-900/40">
              {{ client.user_profile.notes }}
            </p>
          </div>

        </div>

        <!-- Main Content (History) -->
        <div class="xl:col-span-2 space-y-8">

          <!-- Order History -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <ShoppingBag class="w-5 h-5 text-gray-400" />
                Order History
              </h2>
              <Link v-if="orders.total > 0" href="/admin/orders/index" class="text-sm text-blue-600 hover:underline">View All</Link>
            </div>
            <div class="p-0">
              <div v-if="orders.total === 0" class="p-8 text-center text-gray-500">
                No orders found for this client.
              </div>
              <div v-else>
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                  <table class="w-full text-left">
                    <thead>
                      <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Order #</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                      <tr v-for="order in allOrders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4">
                          <Link :href="`/admin/orders/${order.id}`" class="text-sm font-bold text-blue-600 hover:underline">
                            {{ order.order_number }}
                          </Link>
                        </td>
                        <td class="px-6 py-4">
                          <p class="text-sm text-gray-900 dark:text-white">{{ formatDate(order.created_at) }}</p>
                        </td>
                        <td class="px-6 py-4">
                          <span class="px-2 py-1 text-[10px] font-bold uppercase rounded-md" :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400': order.status === 'delivered',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400': order.status === 'pending' || order.status === 'confirmed',
                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': order.status === 'processing',
                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400': order.status === 'cancelled',
                          }">
                            {{ order.status }}
                          </span>
                        </td>
                        <td class="px-6 py-4">
                          <div class="flex items-center gap-1 text-xs text-gray-500">
                            <Package class="w-3.5 h-3.5" />
                            <span>{{ order.items.length }} items</span>
                            <span class="mx-1">•</span>
                            <span class="truncate max-w-[150px]">
                              {{ order.items.map(i => i.product?.name).filter(Boolean).join(', ') }}
                            </span>
                          </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                          <p class="text-sm font-black text-gray-900 dark:text-white">{{ order.total_amount }} TJS</p>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-100 dark:divide-gray-800">
                  <Link v-for="order in allOrders" :key="order.id" :href="`/admin/orders/${order.id}`" class="block p-4 active:bg-gray-50 dark:active:bg-gray-700/50 transition-colors">
                    <div class="flex items-start justify-between mb-2">
                      <div class="flex flex-col">
                        <span class="font-mono text-sm font-bold text-blue-600">{{ order.order_number }}</span>
                        <span class="text-xs text-muted-foreground mt-0.5">{{ formatDate(order.created_at) }}</span>
                      </div>
                      <span class="px-2 py-1 text-[10px] font-bold uppercase rounded-md shrink-0" :class="{
                        'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400': order.status === 'delivered',
                        'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400': order.status === 'pending' || order.status === 'confirmed',
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': order.status === 'processing',
                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400': order.status === 'cancelled',
                      }">
                        {{ order.status }}
                      </span>
                    </div>
                    <div class="flex items-center justify-between mt-3 pt-2 border-t border-dashed border-gray-100 dark:border-gray-800">
                      <div class="flex items-center gap-1 text-xs text-gray-500">
                        <Package class="w-3.5 h-3.5" />
                        <span>{{ order.items.length }} items</span>
                      </div>
                      <span class="text-sm font-black text-gray-900 dark:text-white">{{ order.total_amount }} TJS</span>
                    </div>
                  </Link>
                </div>
              </div>
              
              <!-- Load More Trigger -->
              <div v-if="nextPageUrl" ref="loadMoreTrigger" class="py-6 flex justify-center border-t border-gray-100 dark:border-gray-700">
                <div v-if="isLoadingMore" class="flex items-center gap-2 text-sm text-gray-500">
                  <Loader2 class="w-4 h-4 animate-spin" />
                  <span>Loading more orders...</span>
                </div>
                <div v-else class="h-4"></div>
              </div>
            </div>
          </div>

          <!-- Call History -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <Phone class="w-5 h-5 text-gray-400" />
                Call History
              </h2>
            </div>
            <div class="p-0 max-h-[400px] overflow-y-auto">
              <div v-if="calls.length === 0" class="p-8 text-center text-gray-500">
                No call history found for this client.
              </div>
              <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                <li v-for="call in calls" :key="call.id" class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group flex flex-col">
                  <div class="flex justify-between items-center w-full">
                    <div class="flex items-center space-x-4">
                      <div class="rounded-full w-10 h-10 flex items-center justify-center shrink-0 border" :class="{
                        'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-900/30 dark:border-blue-900': call.properties.direction === 'inbound' && call.properties.status === 'answered',
                        'bg-green-50 text-green-600 border-green-100 dark:bg-green-900/30 dark:border-green-900': call.properties.direction === 'outbound' && call.properties.status === 'answered',
                        'bg-red-50 text-red-600 border-red-100 dark:bg-red-900/30 dark:border-red-900': call.properties.status !== 'answered',
                        'bg-gray-50 text-gray-600 border-gray-200': !call.properties.status // Legacy calls
                      }">
                        <Phone class="w-4 h-4" />
                      </div>
                      <div>
                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 mb-0.5">
                          {{ call.description }}
                        </div>
                        <div class="text-xs text-gray-500 flex flex-wrap items-center gap-3">
                          <span class="flex items-center gap-1">
                            <Clock class="w-3.5 h-3.5" />
                            {{ formatDate(call.created_at) }}
                          </span>
                          <span class="flex items-center gap-1 font-mono bg-gray-100 dark:bg-gray-800 px-1.5 rounded" :class="call.properties.status === 'answered' ? 'text-gray-600 dark:text-gray-300' : 'text-red-600 dark:text-red-400'">
                            {{ call.properties.status === 'answered' ? formatDuration(call.properties.duration) : '00:03' }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity text-green-600 hover:text-green-700 rounded-full hover:bg-green-50" @click="initiateCall(call.properties.phone || null)">
                      <Phone class="w-4 h-4 fill-current" />
                    </Button>
                  </div>

                  <!-- Full Width Audio Recording Player -->
                  <div v-if="call.properties.recording_url" class="mt-3 w-full">
                    <audio controls preload="none" class="h-9 w-full" :src="call.properties.recording_url"></audio>
                  </div>
                </li>
              </ul>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Admin Deposit Modal -->
    <div v-if="isDepositModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <Wallet class="size-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">Add Funds (Admin)</h3>
                </div>
                <button @click="isDepositModalOpen = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <X class="size-5" />
                </button>
            </div>
            <div class="p-6 space-y-4 text-left">
                <div class="space-y-2">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Amount ({{ client.wallet?.currency ?? 'TJS' }})</label>
                    <input v-model="depositForm.amount" type="number" step="1" min="1" class="w-full text-3xl font-black p-4 bg-gray-50 dark:bg-gray-900 border-2 border-gray-100 dark:border-gray-700 rounded-xl focus:border-blue-500 outline-none transition-all dark:text-white" />
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Internal Notes</label>
                    <input v-model="depositForm.notes" type="text" placeholder="e.g., Cash payment received" class="w-full p-3 bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700 rounded-xl focus:border-blue-500 outline-none transition-all dark:text-white" />
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-900/30">
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        Funds will be added to <strong>{{ client.name }}</strong>'s wallet instantly.
                    </p>
                </div>
                <button @click="submitDeposit" :disabled="depositForm.processing" class="w-full py-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl font-bold shadow-lg shadow-blue-500/20 transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                    <Plus v-if="!depositForm.processing" class="size-5" />
                    {{ depositForm.processing ? 'Processing...' : 'Confirm Admin Deposit' }}
                </button>
            </div>
        </div>
    </div>
  </AppLayout>
</template>
