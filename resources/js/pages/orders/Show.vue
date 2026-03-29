<script setup lang="ts">
import { computed, ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import { index, edit, cancel, updateStatus, assign as assignRoute } from '@/routes/orders';
import { edit as editProduct } from '@/routes/products';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Wallet, Check, ChevronDown, Loader2, Box } from 'lucide-vue-next';

interface UserProfile { company_name: string | null; region: string | null; }
interface OrderItem {
  id: number;
  quantity: number;
  unit_price: string;
  subtotal: string;
  product: { id: number; name: Record<string, string> | string; image_url: string | null; };
}
interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  total_amount: string;
  paid_amount: string;
  balance_due: number;
  scheduled_delivery_at: string | null;
  scheduled_delivery_at_human: string | null;
  scheduled_delivery_at_formatted: string | null;
  actual_delivery_at: string | null;
  actual_delivery_at_human: string | null;
  actual_delivery_at_formatted: string | null;
  delivery_address: string | null;
  notes: string | null;
  created_at: string;
  created_at_human: string;
  created_at_formatted: string;
  courier_id: number | null;
  courier: { id: number; name: string } | null;
  client: { id: number; name: string; email: string; phone: string | null; user_profile: UserProfile | null };
  creator: { name: string } | null;
  items: OrderItem[];
}

interface CourierOption {
  id: number;
  name: string;
  avatar_url: string;
  status: string;
  statusLabel: string;
  statusHtmlClass: string;
  orders_count: number;
  last_active_at: string | null;
}

const props = defineProps<{ 
  order: Order; 
  statuses: string[]; 
  couriers: CourierOption[]; 
}>();

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

const isUpdatingStatus = ref(false);
const pendingStatus = ref<string | null>(null);
const isDropdownOpen = ref(false);
const dropdownContainer = ref<HTMLElement | null>(null);

onClickOutside(dropdownContainer, () => {
  isDropdownOpen.value = false;
  pendingStatus.value = null;
});

const statusForm = ref({
  status: '',
  actual_delivery_at: ''
});

const toggleDropdown = () => {
  isDropdownOpen.value = !isDropdownOpen.value;
  pendingStatus.value = null;
};

const selectStatus = (status: string) => {
  pendingStatus.value = status;
};

const confirmStatusUpdate = (status: string) => {
  if (status === 'cancelled') {
    if (!confirm('Cancel this order?')) {
      pendingStatus.value = null;
      isDropdownOpen.value = false;
      return;
    }
    router.patch(cancel(props.order.id).url, {}, { 
      preserveScroll: true,
      onSuccess: () => {
        isDropdownOpen.value = false;
        pendingStatus.value = null;
      }
    });
    return;
  }
  
  if (status === 'delivered') {
    statusForm.value.status = status;
    statusForm.value.actual_delivery_at = new Date().toLocaleString('sv-SE').slice(0, 16).replace(' ', 'T');
    isUpdatingStatus.value = true;
    isDropdownOpen.value = false;
    pendingStatus.value = null;
  } else {
    router.patch(updateStatus(props.order.id).url, { status }, { 
      preserveScroll: true,
      onSuccess: () => {
        isDropdownOpen.value = false;
        pendingStatus.value = null;
      }
    });
  }
};

const submitStatusUpdate = () => {
  router.patch(updateStatus(props.order.id).url, statusForm.value, { 
    preserveScroll: true,
    onSuccess: () => {
      isUpdatingStatus.value = false;
    }
  });
};

const payWithWallet = () => {
  const currency = (usePage().props.currency as string) || 'USD';
  if (!confirm(`Pay ${props.order.balance_due.toFixed(2)} ${currency} from wallet?`)) return;
  router.post(`/orders/${props.order.id}/pay`, {}, {
    preserveScroll: true,
  });
};

const isAssignModalOpen = ref(false);

const isCurrierOnline = (courier: CourierOption) => {
  const lastActive = courier.last_active_at ? new Date(courier.last_active_at) : null;
  if (!lastActive) return false;
  // Online if active within the last 5 minutes
  return (new Date().getTime() - lastActive.getTime()) < 5 * 60 * 1000;
};

const assignCurrier = (courierId: string | number | null) => {
  router.patch(assignRoute({ order: props.order.id }).url, {
    courier_id: courierId ? Number(courierId) : null,
  }, {
    preserveScroll: true,
    onSuccess: () => isAssignModalOpen.value = false,
  });
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
    <div class="space-y-6">

      <!-- Header -->
      <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono">{{ order.order_number }}</h1>
            <p class="text-sm text-gray-500 mt-1">
              Created <span class="font-medium text-gray-700 dark:text-gray-300">{{ order.created_at_human }}</span>
              <span class="text-xs ml-1">({{ order.created_at_formatted }})</span> by {{ order.creator?.name ?? 'System' }}
            </p>
          </div>
          <div class="flex items-center gap-3 flex-wrap" v-if="!isUpdatingStatus">
            <div class="relative" ref="dropdownContainer">
              <button 
                @click="toggleDropdown"
                class="group flex items-center gap-2 px-4 py-2 rounded-xl font-bold uppercase text-xs transition-all border shadow-sm"
                :class="[
                  statusBadge[order.status],
                  isDropdownOpen ? 'ring-2 ring-primary ring-offset-2 scale-[1.02]' : ''
                ]"
              >
                <span>{{ order.status.replace('_', ' ') }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 group-hover:opacity-100 transition-opacity" />
              </button>

              <!-- Status Dropdown -->
              <div v-if="isDropdownOpen" class="absolute right-0 mt-2 w-56 origin-top-right rounded-2xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 py-2 z-50 animate-in fade-in zoom-in duration-100">
                <div class="px-4 py-2 border-b border-gray-50 dark:border-gray-700/50 mb-1">
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Update Order Status</p>
                </div>
                <div class="px-1">
                  <button 
                    v-for="status in statuses" 
                    :key="status"
                    @click="status === pendingStatus ? confirmStatusUpdate(status) : selectStatus(status)"
                    class="flex w-full items-center justify-between px-3 py-2.5 rounded-xl text-sm font-semibold transition-all mb-0.5"
                    :class="[
                      status === pendingStatus 
                        ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30 active:scale-[0.98]' 
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50'
                    ]"
                  >
                    <span class="capitalize">{{ status.replace('_', ' ') }}</span>
                    <div v-if="status === pendingStatus" class="flex items-center gap-1 bg-white/20 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tight">
                      <Check class="w-3 h-3" />
                      Confirm
                    </div>
                    <Check v-else-if="status === order.status" class="w-4 h-4 text-green-500" />
                  </button>
                </div>
              </div>
            </div>

            <Link v-if="['pending', 'confirmed'].includes(order.status)" :href="edit(order.id).url">
              <Button variant="outline" size="sm" class="rounded-xl h-10 px-4">
                <Edit class="w-4 h-4 mr-2" />
                Edit Order
              </Button>
            </Link>
          </div>

          <div v-else class="flex items-center gap-3 bg-blue-50 dark:bg-blue-900/20 p-3 rounded-xl border border-blue-100 dark:border-blue-800">
            <div class="grid gap-1">
              <label class="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400">Actual Delivery Time</label>
              <input type="datetime-local" v-model="statusForm.actual_delivery_at" class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="flex gap-2 self-end">
              <Button size="sm" @click="submitStatusUpdate">Save Delivered</Button>
              <Button size="sm" variant="ghost" @click="isUpdatingStatus = false">Cancel</Button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Client info -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Client</h2>
          <p class="font-semibold text-gray-900 dark:text-white">
            <Link :href="`/clients/${order.client.id}`" class="hover:underline hover:text-blue-600 transition-colors">
              {{ order.client.name }}
            </Link>
          </p>
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
            <span class="font-medium uppercase text-[10px] tracking-wider text-gray-400 block mb-1">Scheduled:</span>
            <span v-if="order.scheduled_delivery_at_human" class="block font-semibold text-base">{{ order.scheduled_delivery_at_human }}</span>
            <span class="text-xs text-gray-500">{{ order.scheduled_delivery_at_formatted ?? order.scheduled_delivery_at ?? '—' }}</span>
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-4" v-if="order.actual_delivery_at">
            <span class="font-medium uppercase text-[10px] tracking-wider text-gray-400 block mb-1">Actual:</span>
            <span v-if="order.actual_delivery_at_human" class="block font-semibold text-base text-green-600">{{ order.actual_delivery_at_human }}</span>
            <span class="text-xs text-gray-500">{{ order.actual_delivery_at_formatted ?? order.actual_delivery_at }}</span>
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
            <span class="font-medium">Address:</span> {{ order.delivery_address ?? '—' }}
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-2" v-if="order.notes">
            <span class="font-medium">Notes:</span> {{ order.notes }}
          </p>

          <div class="mt-4 pt-4 border-t dark:border-gray-700">
            <label class="text-[10px] uppercase font-bold text-gray-400 block mb-2 tracking-wider font-mono">Currier Assignment</label>
            <div class="flex items-center gap-2">
              <Button @click="isAssignModalOpen = true" variant="outline" class="w-full justify-between font-normal h-12" :class="order.courier ? 'text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-900/20 shadow-none' : ''">
                <span v-if="order.courier" class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-sm shadow-blue-500/40"></span>
                  Assigned to: <span class="font-bold">{{ order.courier.name }}</span>
                </span>
                <span v-else>Click to assign currier...</span>
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </div>
          </div>
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
                {{ order.balance_due.toFixed(2) }} <span class="text-[10px] uppercase ml-1 opacity-60">{{ ($page.props.currency as string) || 'USD' }}</span>
              </span>
            </div>
          </div>
          <div class="mt-2">
            <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize" :class="order.payment_status === 'paid'
              ? 'bg-green-100 text-green-700'
              : order.payment_status === 'partial'
                ? 'bg-yellow-100 text-yellow-700'
                : 'bg-red-100 text-red-700'">
              {{ order.payment_status }}
            </span>
          </div>

          <div class="mt-4 pt-4 border-t dark:border-gray-700" v-if="order.balance_due > 0 && order.payment_status !== 'paid'">
            <button @click="payWithWallet" class="w-full flex items-center justify-center gap-2 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-bold shadow-lg shadow-green-500/20 transition-all active:scale-[0.98]">
              <Wallet class="size-5" />
              Pay with Wallet
            </button>
            <p class="text-[10px] text-center text-gray-400 mt-2 uppercase tracking-widest">Instant payment from your balance</p>
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
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div v-if="item.product.image_url" class="w-10 h-10 rounded-md overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0">
                    <img :src="item.product.image_url" :alt="resolveProductName(item.product.name)" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-10 h-10 rounded-md bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-200 border border-gray-200 dark:border-gray-700 shrink-0">
                    {{ resolveProductName(item.product.name).charAt(0).toUpperCase() }}
                  </div>
                  <Link :href="editProduct(item.product.id).url" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                    {{ resolveProductName(item.product.name) }}
                  </Link>
                </div>
              </td>
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

      <!-- Courier Assignment Modal -->
      <Dialog v-model:open="isAssignModalOpen">
        <DialogContent class="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">Assign Currier</DialogTitle>
            <DialogDescription>
              Select an available currier to process this order.
            </DialogDescription>
          </DialogHeader>
          
          <div class="py-2 space-y-2 lg:max-h-[60vh] sm:max-h-[80vh] overflow-y-auto pr-2">
            <!-- Unassign option -->
            <button 
              @click="assignCurrier(null)"
              class="w-full flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:bg-red-50 dark:hover:bg-red-900/20 hover:border-red-200 dark:hover:border-red-800 transition-all text-left"
              :class="{ 'opacity-50 pointer-events-none': !order.courier_id }"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <Box class="w-5 h-5 text-gray-400" />
                </div>
                <div>
                  <p class="font-bold text-gray-900 dark:text-gray-100">Unassign Order</p>
                  <p class="text-xs text-gray-500">Remove currently assigned currier.</p>
                </div>
              </div>
            </button>

            <!-- Currier list -->
            <button 
              v-for="courier in couriers" 
              :key="courier.id"
              @click="assignCurrier(courier.id)"
              class="w-full flex items-center justify-between p-4 rounded-xl border transition-all text-left relative overflow-hidden group"
              :class="order.courier_id === courier.id ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 dark:bg-gray-800/50 dark:hover:bg-gray-800'"
            >
              <!-- Selection indicator -->
              <div v-if="order.courier_id === courier.id" class="absolute top-0 right-0 w-12 h-12 flex items-start justify-end p-1.5 opacity-20 bg-blue-500 rounded-bl-[100%]">
                <Check class="w-4 h-4 text-white translate-x-1 -translate-y-1" />
              </div>

              <div class="flex items-center gap-4 relative z-10 w-full">
                <!-- Avatar with status indicator -->
                <div class="relative">
                  <img :src="courier.avatar_url" :alt="courier.name" class="w-12 h-12 rounded-full object-cover border-2 shadow-sm" :class="order.courier_id === courier.id ? 'border-blue-500' : 'border-white dark:border-gray-900'" />
                  <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white dark:border-gray-800" :class="isCurrierOnline(courier) ? 'bg-green-500' : 'bg-gray-400'" :title="isCurrierOnline(courier) ? 'Online' : 'Offline'"></span>
                </div>
                
                <div class="flex-1 min-w-0">
                  <p class="font-bold text-gray-900 dark:text-gray-100 truncate flex items-center gap-2">
                    {{ courier.name }}
                    <span v-if="order.courier_id === courier.id" class="text-[10px] uppercase font-black tracking-wider text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/50 px-2 py-0.5 rounded text-center">Current</span>
                  </p>
                  <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                      <div class="w-2 h-2 rounded-full" :class="isCurrierOnline(courier) ? 'bg-green-500' : 'bg-gray-400'"></div>
                      {{ isCurrierOnline(courier) ? 'Online' : 'Offline' }}
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex items-center gap-1" :class="courier.orders_count > 0 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                      <Box class="w-3 h-3" />
                      {{ courier.orders_count }} active tasks
                    </span>
                  </div>
                </div>
              </div>
            </button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
