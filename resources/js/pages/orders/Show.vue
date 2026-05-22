<script setup lang="ts">
import { computed, ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import { index as guestIndex, show as guestShow, pay as guestPay } from '@/routes/orders';
import { 
  index as adminIndex, 
  show as adminShow, 
  edit as adminEdit, 
  cancel as adminCancel, 
  updateStatus as adminUpdateStatus, 
  assign as adminAssignRoute 
} from '@/routes/admin/orders';
import { edit as editProduct } from '@/routes/admin/products';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Wallet, Check, ChevronDown, Loader2, Box } from 'lucide-vue-next';

interface UserProfile { company_name: string | null; region: string | null; }
interface OrderItem {
  id: number;
  quantity: number;
  unit_price: string;
  subtotal: string;
  is_gift: boolean;
  product: { id: number; name: Record<string, string> | string; image_url: string | null; };
}
interface ReturnedMaterial {
  id: number;
  name: string;
  unit: string;
  pivot: { quantity: number; };
}
interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  total_amount: string;
  discount_amount: string;
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
  cancellation_reason: string | null;
  cancelled_at: string | null;
  cancelled_at_human?: string | null;
  cancelled_at_formatted?: string | null;
  canceller: { id: number; name: string } | null;
  created_at: string;
  created_at_human: string;
  created_at_formatted: string;
  courier_id: number | null;
  courier: { id: number; name: string } | null;
  client: { id: number; name: string; email: string; phone: string | null; user_profile: UserProfile | null };
  creator: { name: string } | null;
  items: OrderItem[];
  returned_materials: ReturnedMaterial[];
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

interface ReusableMaterial {
  id: number;
  name: string;
  unit: string;
}

const props = defineProps<{ 
  order: Order; 
  statuses: string[]; 
  reusable_materials?: ReusableMaterial[];
  couriers: CourierOption[]; 
}>();

const adminMode = computed(() => !!usePage().props.adminMode);

const indexRoute = computed(() => adminMode.value ? adminIndex : guestIndex);
const showRoute = computed(() => adminMode.value ? adminShow : guestShow);
const editRoute = computed(() => adminMode.value ? adminEdit : null);
const cancelRoute = computed(() => adminMode.value ? adminCancel : null);
const updateStatusRoute = computed(() => adminMode.value ? adminUpdateStatus : null);
const assignRoute = computed(() => adminMode.value ? adminAssignRoute : null);

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: indexRoute.value().url },
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
const isDeliveryModalOpen = ref(false);
const isCancelModalOpen = ref(false);
const cancelForm = ref({ cancellation_reason: '' });
const cancelError = ref('');
const isCancelled = computed(() => props.order.status === 'cancelled');
const isSelfPickup = computed(() => props.order.delivery_address === 'Self Pickup');
const pendingStatus = ref<string | null>(null);
const isDropdownOpen = ref(false);
const dropdownContainer = ref<HTMLElement | null>(null);

onClickOutside(dropdownContainer, () => {
  isDropdownOpen.value = false;
  pendingStatus.value = null;
});

const statusForm = ref({
  status: '',
  actual_delivery_at: '',
  returned_materials: [] as { raw_material_id: number; quantity: number }[]
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
    cancelForm.value.cancellation_reason = '';
    cancelError.value = '';
    isCancelModalOpen.value = true;
    isDropdownOpen.value = false;
    pendingStatus.value = null;
    return;
  }
  
  if (status === 'delivered') {
    statusForm.value.status = status;
    statusForm.value.actual_delivery_at = new Date().toLocaleString('sv-SE').slice(0, 16).replace(' ', 'T');
    
    // Auto-init returned_materials to empty unless we want to preset rows
    statusForm.value.returned_materials = [];

    isDeliveryModalOpen.value = true;
    isDropdownOpen.value = false;
    pendingStatus.value = null;
  } else {
    router.patch(updateStatusRoute.value!(props.order.id).url, { status }, { 
      preserveScroll: true,
      onSuccess: () => {
        isDropdownOpen.value = false;
        pendingStatus.value = null;
      }
    });
  }
};

const submitStatusUpdate = () => {
  router.patch(updateStatusRoute.value!(props.order.id).url, statusForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isDeliveryModalOpen.value = false;
    }
  });
};

const submitCancellation = () => {
  const reason = cancelForm.value.cancellation_reason.trim();
  if (!reason) {
    cancelError.value = 'Please provide a reason for the cancellation.';
    return;
  }
  cancelError.value = '';
  router.patch(cancelRoute.value!(props.order.id).url, { cancellation_reason: reason }, {
    preserveScroll: true,
    onSuccess: () => {
      isCancelModalOpen.value = false;
      cancelForm.value.cancellation_reason = '';
    },
    onError: (errors: Record<string, string>) => {
      cancelError.value = errors.cancellation_reason ?? 'Could not cancel the order.';
    },
  });
};

const payWithWallet = () => {
  const currency = (usePage().props.currency as string) || 'USD';
  if (!confirm(`Pay ${props.order.balance_due.toFixed(2)} ${currency} from wallet?`)) return;
  const url = adminMode.value ? `/admin/orders/${props.order.id}/pay` : `/orders/${props.order.id}/pay`;
  router.post(url, {}, {
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
  router.patch(assignRoute.value!({ order: props.order.id }).url, {
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
          <div class="flex items-center gap-3 flex-wrap">
            <!-- Static status badge for clients; admin gets the editable dropdown. -->
            <span
              v-if="!adminMode"
              class="inline-flex items-center px-4 py-2 rounded-xl font-bold uppercase text-xs border shadow-sm"
              :class="statusBadge[order.status]"
            >
              {{ order.status.replace('_', ' ') }}
            </span>
            <div v-else class="relative" ref="dropdownContainer">
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

            <Link v-if="editRoute && ['pending', 'confirmed'].includes(order.status)" :href="editRoute(order.id).url">
              <Button variant="outline" size="sm" class="rounded-xl h-10 px-4">
                <Edit class="w-4 h-4 mr-2" />
                Edit Order
              </Button>
            </Link>
          </div>


        </div>
      </div>

      <!-- Cancellation banner -->
      <div v-if="isCancelled" class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-900/50 px-5 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-red-700 dark:text-red-300">Order cancelled</div>
            <p class="mt-1 text-sm text-red-900 dark:text-red-100 whitespace-pre-line">
              {{ order.cancellation_reason || 'No reason was recorded.' }}
            </p>
            <p class="mt-2 text-xs text-red-700/80 dark:text-red-300/80">
              <span v-if="order.canceller">By {{ order.canceller.name }}</span>
              <span v-if="order.cancelled_at_human"> · {{ order.cancelled_at_human }}</span>
              <span v-if="order.cancelled_at_formatted" class="ml-1 opacity-70">({{ order.cancelled_at_formatted }})</span>
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Client info -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Client</h2>
          <p class="font-semibold text-gray-900 dark:text-white">
            <Link :href="adminMode ? `/admin/clients/${order.client.id}` : '#'" :class="adminMode ? 'hover:underline hover:text-blue-600 transition-colors' : 'cursor-default'">
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
            <span class="font-medium">Address:</span>
            <span v-if="order.delivery_address === 'Self Pickup'" class="ml-1 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wide bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 px-2 py-0.5 rounded">
              🏬 Self Pickup
            </span>
            <span v-else>{{ order.delivery_address ?? '—' }}</span>
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-2" v-if="order.notes">
            <span class="font-medium">Notes:</span> {{ order.notes }}
          </p>

          <div v-if="adminMode && isSelfPickup && !isCancelled" class="mt-4 pt-4 border-t dark:border-gray-700">
            <div class="flex items-center gap-2 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40 rounded-lg px-3 py-2">
              🏬 Self-pickup order — no courier needed.
            </div>
          </div>
          <div v-else-if="adminMode && !isCancelled" class="mt-4 pt-4 border-t dark:border-gray-700">
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

        <!-- Payment summary — admin only. Clients see no monetary totals. -->
        <div v-if="adminMode" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Payment</h2>
          <div class="space-y-1 text-sm">
            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between">
              <span class="text-gray-500">Subtotal</span>
              <span class="text-gray-700 dark:text-gray-300">{{ (Number(order.total_amount) + Number(order.discount_amount)).toFixed(2) }}</span>
            </div>
            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between">
              <span class="text-pink-600 dark:text-pink-300 font-medium">Discount</span>
              <span class="text-pink-600 dark:text-pink-300 font-medium">-{{ Number(order.discount_amount).toFixed(2) }}</span>
            </div>
            <div v-else-if="Number(order.discount_amount) < 0" class="flex justify-between">
              <span class="text-orange-600 dark:text-orange-300 font-medium">Surcharge</span>
              <span class="text-orange-600 dark:text-orange-300 font-medium">+{{ Math.abs(Number(order.discount_amount)).toFixed(2) }}</span>
            </div>
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

          <div class="mt-4 pt-4 border-t dark:border-gray-700" v-if="!isCancelled && order.balance_due > 0 && order.payment_status !== 'paid'">
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
              <th v-if="adminMode" class="px-6 py-3 text-right">Unit Price</th>
              <th v-if="adminMode" class="px-6 py-3 text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id" class="border-b dark:border-gray-700" :class="item.is_gift ? 'bg-pink-50/40 dark:bg-pink-900/10' : ''">
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div v-if="item.product.image_url" class="w-10 h-10 rounded-md overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0">
                    <img :src="item.product.image_url" :alt="resolveProductName(item.product.name)" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-10 h-10 rounded-md bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-200 border border-gray-200 dark:border-gray-700 shrink-0">
                    {{ resolveProductName(item.product.name).charAt(0).toUpperCase() }}
                  </div>
                  <div class="flex items-center gap-2">
                    <Link :href="editProduct(item.product.id).url" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                      {{ resolveProductName(item.product.name) }}
                    </Link>
                    <span v-if="item.is_gift" class="text-[10px] uppercase tracking-wide bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300 px-2 py-0.5 rounded font-bold">
                      Gift
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3 text-right">{{ item.quantity }}</td>
              <td v-if="adminMode" class="px-6 py-3 text-right" :class="item.is_gift ? 'line-through text-gray-400' : ''">{{ item.unit_price }}</td>
              <td v-if="adminMode" class="px-6 py-3 text-right font-semibold" :class="item.is_gift ? 'text-pink-600 dark:text-pink-300' : 'text-gray-900 dark:text-white'">
                {{ item.is_gift ? 'Free' : item.subtotal }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="adminMode">
            <tr class="bg-gray-50 dark:bg-gray-700">
              <td colspan="3" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total</td>
              <td class="px-6 py-3 text-right font-bold text-gray-900 dark:text-white">{{ order.total_amount }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Returned Materials -->
      <div v-if="order.returned_materials?.length > 0" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden border border-blue-100 dark:border-blue-900/50">
        <div class="px-4 py-4 sm:px-6 border-b border-blue-100 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-900/20 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-blue-800 dark:text-blue-300 uppercase tracking-wider flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-check"><path d="m16 16 2 2 4-4"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="m7.5 4.27 9 5.15"/><polyline points="3.29 7.08 12 12 20.71 7.08"/><line x1="12" x2="12" y1="22" y2="12"/></svg>
            Returned Materials Log
          </h2>
          <span class="text-xs bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 py-0.5 px-2.5 rounded-full font-bold">Successfully Collected</span>
        </div>
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-white dark:bg-gray-800 dark:text-gray-400">
            <tr>
              <th class="px-6 py-3 font-semibold">Material Model</th>
              <th class="px-6 py-3 text-right font-semibold">Quantity Returned</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.returned_materials" :key="item.id" class="border-t border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/20">
              <td class="px-6 py-3.5 font-medium text-gray-900 dark:text-white flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-blue-500 shadow shadow-blue-500/40"></div>
                {{ item.name }}
              </td>
              <td class="px-6 py-3.5 text-right font-black text-gray-900 dark:text-gray-100 text-base">
                +{{ item.pivot.quantity }} <span class="text-[10px] uppercase font-bold text-gray-400 ml-0.5">{{ item.unit }}</span>
              </td>
            </tr>
          </tbody>
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

      <!-- Delivery Confirmation Modal -->
      <!-- Cancellation Modal -->
      <Dialog v-model:open="isCancelModalOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">Cancel Order</DialogTitle>
            <DialogDescription>
              Record why this order is being cancelled. The note is kept for later statistics.
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-3 py-2">
            <label class="text-xs uppercase font-bold text-gray-500 block">Reason *</label>
            <textarea
              v-model="cancelForm.cancellation_reason"
              rows="4"
              maxlength="1000"
              placeholder="e.g. Client unreachable, duplicate order, wrong address…"
              class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-500 outline-none"
            ></textarea>
            <p v-if="cancelError" class="text-xs text-red-600">{{ cancelError }}</p>

            <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700 mt-2">
              <Button type="button" variant="outline" @click="isCancelModalOpen = false">Keep Order</Button>
              <Button type="button" class="bg-red-600 hover:bg-red-700 text-white" @click="submitCancellation">
                Cancel Order
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog v-model:open="isDeliveryModalOpen">
        <DialogContent class="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">Confirm Delivery</DialogTitle>
            <DialogDescription>
              Record the actual delivery time and log any bottles or containers returned by the client.
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4 py-2">
            <div>
                <label class="text-xs uppercase font-bold text-gray-500 block mb-1">Actual Delivery Time</label>
                <input type="datetime-local" v-model="statusForm.actual_delivery_at" class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 w-full" />
            </div>

            <div v-if="(props.reusable_materials ?? []).length > 0" class="flex flex-col gap-2 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <label class="text-xs uppercase font-bold text-gray-500">Returned Containers</label>
                
                <div v-if="statusForm.returned_materials.length === 0" class="text-xs text-gray-400 py-3 italic text-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                    No items marked for return. Tracking none.
                </div>
                
                <div v-for="(rm, index) in statusForm.returned_materials" :key="index" class="flex items-center gap-2 mb-1">
                    <select v-model="statusForm.returned_materials[index].raw_material_id" class="flex-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-sm rounded-lg px-3 py-2 outline-none">
                      <option disabled :value="0">Select material</option>
                      <option v-for="material in props.reusable_materials" :key="material.id" :value="material.id">{{ material.name }}</option>
                    </select>
                    <input type="number" min="1" v-model="statusForm.returned_materials[index].quantity" class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 w-24 text-sm outline-none" placeholder="Qty" />
                    <button type="button" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/40 p-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 transition-colors" @click="statusForm.returned_materials.splice(index, 1)">✕</button>
                </div>
                
                <Button variant="outline" type="button" @click="statusForm.returned_materials.push({ raw_material_id: 0, quantity: 1 })" class="mt-2 w-full border-dashed border-2 bg-transparent">
                  + Add returned material
                </Button>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t mt-4">
              <Button type="button" variant="outline" @click="isDeliveryModalOpen = false">Cancel</Button>
              <Button type="button" class="bg-green-600 hover:bg-green-700 text-white" @click="submitStatusUpdate">Save Delivery</Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
