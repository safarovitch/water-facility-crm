<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import { index as guestIndex, show as guestShow, pay as guestPay, payFromBalance as guestPayFromBalance } from '@/routes/orders';
import {
  index as adminIndex,
  show as adminShow,
  edit as adminEdit,
  cancel as adminCancel,
  updateStatus as adminUpdateStatus,
  assign as adminAssignRoute,
  payFromBalance as adminPayFromBalance
} from '@/routes/admin/orders';
import { edit as editProduct } from '@/routes/admin/products';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import RepeatOrderModal from '@/components/RepeatOrderModal.vue';
import MapChooser from '@/components/MapChooser.vue';
import { Wallet, Check, ChevronDown, Loader2, Box, Trash2, Phone, RotateCcw } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

interface UserProfile { company_name: string | null; region: string | null; }
interface OrderItem {
  id: number;
  quantity: number;
  delivered_quantity: number | null;
  unit_price: string;
  subtotal: string;
  is_gift: boolean;
  product: { id: number; name: Record<string, string> | string; image_url: string | null; };
}
interface OrderRef { id: number; order_number: string; status: string; total_amount?: string; scheduled_delivery_at?: string | null; }
interface ReturnedMaterial {
  id: number;
  name: string;
  unit: string;
  pivot: { quantity: number; deferred_quantity?: number; };
}
interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  total_amount: string;
  discount_amount: string;
  deposit_charge: string;
  paid_amount: string;
  balance_due: number;
  scheduled_delivery_at: string | null;
  scheduled_delivery_at_human: string | null;
  scheduled_delivery_at_formatted: string | null;
  actual_delivery_at: string | null;
  actual_delivery_at_human: string | null;
  actual_delivery_at_formatted: string | null;
  delivery_address: string | null;
  lat: number | null;
  lng: number | null;
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
  client: { id: number; name: string; email: string; phone: string | null; user_profile: UserProfile | null; wallet: { balance: string; currency: string } | null };
  creator: { name: string } | null;
  items: OrderItem[];
  returned_materials: ReturnedMaterial[];
  parent_order_id: number | null;
  parent_order: OrderRef | null;
  backorders: OrderRef[];
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

interface ReusableSummaryRow {
  raw_material: { id: number; name: string; unit: string; deposit_price: string };
  expected: number;
  returned: number;
  deferred: number;
  missing: number;
  chargeable: number;
  charge: number;
}

const props = defineProps<{
  order: Order;
  statuses: string[];
  reusable_materials?: ReusableMaterial[];
  reusable_summary?: ReusableSummaryRow[];
  couriers: CourierOption[];
}>();

const { t } = useI18n();
const adminMode = computed(() => !!usePage().props.adminMode);
const can = computed(() => usePage().props.auth.can ?? {});

const indexRoute = computed(() => adminMode.value ? adminIndex : guestIndex);
const showRoute = computed(() => adminMode.value ? adminShow : guestShow);
// Editing, cancelling and (re)assigning need manager/admin rights; plain
// couriers can only update status / collect payment on their own orders.
const editRoute = computed(() => adminMode.value && can.value.manageOrders ? adminEdit : null);
const cancelRoute = computed(() => adminMode.value && can.value.manageOrders ? adminCancel : null);
const updateStatusRoute = computed(() => adminMode.value ? adminUpdateStatus : null);
const assignRoute = computed(() => adminMode.value && can.value.assignCurriers ? adminAssignRoute : null);

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Orders'), href: indexRoute.value().url },
  { title: props.order.order_number, href: '#' },
]);

const statusBadge: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  in_production: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
  ready: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
  accepted: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
  in_transit: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

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

const validTransitions: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['in_production', 'cancelled'],
  in_production: ['ready', 'cancelled'],
  ready: ['delivered'],
  delivered: [],
  cancelled: [],
};

// Cancelling goes through the manager-only cancel route, so drop that
// transition for users without it (plain couriers).
const nextStatuses = computed(() =>
  (validTransitions[props.order.status] ?? []).filter(s => s !== 'cancelled' || !!cancelRoute.value)
);

const isUpdatingStatus = ref(false);
const isDeliveryModalOpen = ref(false);
const isCancelModalOpen = ref(false);
const isWalletPayModalOpen = ref(false);
const walletPayProcessing = ref(false);
const payFromBalanceProcessing = ref(false);
const isOverpaymentRefundModalOpen = ref(false);
const overpaymentRefundProcessing = ref(false);

const walletBalance = computed(() => Number(props.order.client.wallet?.balance ?? 0));
const payFromBalanceRoute = computed(() => adminMode.value ? adminPayFromBalance : guestPayFromBalance);

const page = usePage();
const overpaymentOnOrder = computed(() => {
  const paid = Number(props.order.paid_amount);
  const grand = Number(props.order.total_amount) + Number(props.order.deposit_charge ?? 0);
  const diff = paid - grand;
  return diff > 0.005 ? Number(diff.toFixed(2)) : 0;
});

watch(
  () => (page.props.flash as { pending_overpayment_refund?: { amount: number } })?.pending_overpayment_refund,
  (pending) => {
    // Refunding to wallet is an admin-tier route; don't offer the modal to
    // courier staff.
    if (pending && adminMode.value && can.value.manageAccounting) {
      isOverpaymentRefundModalOpen.value = true;
    }
  },
  { immediate: true },
);
const isDeleteModalOpen = ref(false);
const deleteProcessing = ref(false);
const deleteConfirmText = ref('');
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

interface DeliveredLine {
  order_item_id: number;
  delivered_quantity: number;
  shortfall_action: 'dismiss' | 'backorder';
}

const statusForm = ref({
  status: '',
  actual_delivery_at: '',
  returned_materials: [] as { raw_material_id: number; quantity: number; deferred_quantity: number }[],
  delivered_items: [] as DeliveredLine[],
});

const productName = (name: Record<string, string> | string | undefined): string => {
  if (!name) return '—';
  if (typeof name === 'string') return name;
  return name['en'] ?? name['uz'] ?? name['ru'] ?? Object.values(name)[0] ?? '—';
};

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

    // Seed one returned-container row per reusable material expected in this
    // order. The admin records how many empties were collected now vs. left to
    // collect later; whatever remains is charged at the deposit price.
    statusForm.value.returned_materials = (props.reusable_summary ?? []).map(r => ({
      raw_material_id: r.raw_material.id,
      quantity: r.returned ?? 0,
      deferred_quantity: r.deferred ?? 0,
    }));

    // Seed delivered_items with "fully delivered" for every line. The
    // admin can lower the count per row; rows where they go below
    // ordered_quantity get a dismiss/backorder choice.
    statusForm.value.delivered_items = props.order.items.map(i => ({
      order_item_id: i.id,
      delivered_quantity: i.delivered_quantity ?? i.quantity,
      shortfall_action: 'dismiss' as const,
    }));

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

const reusableSummaryById = computed(() => {
  const map: Record<number, ReusableSummaryRow> = {};
  for (const r of props.reusable_summary ?? []) map[r.raw_material.id] = r;
  return map;
});

// Live deposit for a row = whatever is neither collected now nor deferred.
const lineCharge = (row: { raw_material_id: number; quantity: number; deferred_quantity: number }) => {
  const s = reusableSummaryById.value[row.raw_material_id];
  if (!s) return 0;
  const remaining = Math.max(s.expected - (Number(row.quantity) || 0) - (Number(row.deferred_quantity) || 0), 0);
  return remaining * Number(s.raw_material.deposit_price);
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
    cancelError.value = t('Please provide a reason for the cancellation.');
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
      cancelError.value = errors.cancellation_reason ?? t('Could not cancel the order.');
    },
  });
};

const openWalletPayModal = () => {
  isWalletPayModalOpen.value = true;
};

const confirmWalletPay = () => {
  walletPayProcessing.value = true;
  const url = adminMode.value ? `/admin/orders/${props.order.id}/pay` : `/orders/${props.order.id}/pay`;
  router.post(url, {}, {
    preserveScroll: true,
    onFinish: () => {
      walletPayProcessing.value = false;
      isWalletPayModalOpen.value = false;
    },
  });
};

const confirmPayFromBalance = () => {
  payFromBalanceProcessing.value = true;
  router.post(payFromBalanceRoute.value(props.order.id).url, {}, {
    preserveScroll: true,
    onFinish: () => {
      payFromBalanceProcessing.value = false;
      isWalletPayModalOpen.value = false;
    },
  });
};

const confirmOverpaymentRefund = () => {
  overpaymentRefundProcessing.value = true;
  router.post(`/admin/orders/${props.order.id}/refund-overpayment`, {}, {
    preserveScroll: true,
    onFinish: () => {
      overpaymentRefundProcessing.value = false;
      isOverpaymentRefundModalOpen.value = false;
    },
  });
};

const isRepeatModalOpen = ref(false);

const repeatItems = computed(() =>
  props.order.items.map((item) => ({
    product_id: item.product.id,
    name: resolveProductName(item.product.name),
    quantity: item.quantity,
    is_gift: item.is_gift,
  })),
);

const deleteConfirmExpected = computed(() => props.order.order_number);
const canConfirmDelete = computed(() => deleteConfirmText.value.trim() === deleteConfirmExpected.value);

const confirmDelete = () => {
  if (!canConfirmDelete.value) return;
  deleteProcessing.value = true;
  router.delete(`/admin/orders/${props.order.id}`, {
    onFinish: () => {
      deleteProcessing.value = false;
    },
  });
};

const collectingDeferred = ref<number[]>([]);

const collectDeferred = (rawMaterialId: number) => {
  collectingDeferred.value.push(rawMaterialId);
  router.patch(
    `/admin/orders/${props.order.id}/collect-deferred`,
    { raw_material_ids: [rawMaterialId] },
    {
      preserveScroll: true,
      onFinish: () => {
        collectingDeferred.value = collectingDeferred.value.filter(id => id !== rawMaterialId);
      },
    },
  );
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
              {{ t('Created') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ order.created_at_human }}</span>
              <span class="text-xs ml-1">({{ order.created_at_formatted }})</span> {{ t('by') }} {{ order.creator?.name ?? t('System') }}
            </p>
          </div>
          <div class="flex items-center gap-3 flex-wrap">
            <!-- Static status badge for clients; admin gets the editable dropdown. -->
            <span
              v-if="!adminMode"
              class="inline-flex items-center px-4 py-2 rounded-xl font-bold uppercase text-xs border shadow-sm"
              :class="statusBadge[order.status]"
            >
              {{ statusLabel[order.status] ?? order.status }}
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
                <span>{{ statusLabel[order.status] ?? order.status }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 group-hover:opacity-100 transition-opacity" />
              </button>

              <!-- Mobile backdrop (sm+: hidden). Inside dropdownContainer so its
                   click is treated as "outside the button area" — we close
                   explicitly to make the intent clear. -->
              <div
                v-if="isDropdownOpen"
                @click="isDropdownOpen = false"
                class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm sm:hidden"
              ></div>

              <!-- Status Dropdown — bottom sheet on mobile, anchored popover on sm+. -->
              <div
                v-if="isDropdownOpen"
                class="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl border border-b-0 border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl py-2 animate-in slide-in-from-bottom duration-150
                       sm:absolute sm:inset-x-auto sm:bottom-auto sm:right-0 sm:mt-2 sm:w-56 sm:rounded-2xl sm:border-b sm:origin-top-right sm:animate-none mb-8"
              >
                <!-- Mobile drag handle -->
                <div class="mx-auto mb-2 h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700 sm:hidden"></div>

                <div class="px-4 py-2 border-b border-gray-50 dark:border-gray-700/50 mb-1">
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('Update Order Status') }}</p>
                </div>
                <div class="px-1 pb-safe sm:pb-16">
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
                    <span class="capitalize">{{ statusLabel[status] ?? status.replace('_', ' ') }}</span>
                    <div v-if="status === pendingStatus" class="flex items-center gap-1 bg-white/20 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tight">
                      <Check class="w-3 h-3" />
                      {{ t('Confirm') }}
                    </div>
                    <Check v-else-if="status === order.status" class="w-4 h-4 text-green-500" />
                  </button>
                </div>
              </div>
            </div>

            <Button
              v-if="adminMode && can.manageOrders"
              type="button"
              variant="outline"
              size="sm"
              class="rounded-xl h-10 px-4"
              @click="isRepeatModalOpen = true"
            >
              <RotateCcw class="w-4 h-4 mr-2" />
              {{ t('Repeat Order') }}
            </Button>

            <!-- Editing a cancelled order would double-restore stock (cancel already
                 +1'd inventory; OrderController::update would +1 again). All other
                 statuses, including delivered, are safe to edit. -->
            <Link v-if="editRoute && order.status !== 'cancelled'" :href="editRoute(order.id).url">
              <Button variant="outline" size="sm" class="rounded-xl h-10 px-4">
                <Edit class="w-4 h-4 mr-2" />
                {{ t('Edit Order') }}
              </Button>
            </Link>
            <Button
              v-if="adminMode && can.deleteOrders"
              type="button"
              variant="outline"
              size="sm"
              class="rounded-xl h-10 px-4 border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-900/20"
              @click="isDeleteModalOpen = true"
            >
              <Trash2 class="w-4 h-4 mr-2" />
              {{ t('Delete') }}
            </Button>
          </div>


        </div>
      </div>

      <!-- Backorder lineage: this order is a child spun off from a parent
           short-delivery, or has children spun off from itself. -->
      <div
        v-if="order.parent_order || (order.backorders?.length ?? 0) > 0"
        class="rounded-xl border border-blue-200 bg-blue-50/60 dark:bg-blue-900/15 dark:border-blue-900/50 px-5 py-4 space-y-2"
      >
        <p v-if="order.parent_order" class="text-sm text-blue-900 dark:text-blue-100">
          <span class="text-[10px] uppercase tracking-widest font-bold text-blue-700 dark:text-blue-300 mr-2">{{ t('Backorder of') }}</span>
          <Link :href="showRoute(order.parent_order.id).url" class="font-semibold hover:underline">
            #{{ order.parent_order.order_number }}
          </Link>
          <span class="text-xs text-blue-700/80 dark:text-blue-300/80 ml-1 capitalize">({{ statusLabel[order.parent_order.status] ?? order.parent_order.status.replace('_', ' ') }})</span>
        </p>
        <div v-if="(order.backorders?.length ?? 0) > 0">
          <p class="text-[10px] uppercase tracking-widest font-bold text-blue-700 dark:text-blue-300 mb-1">{{ t('Backorders from this delivery') }}</p>
          <ul class="space-y-1">
            <li v-for="b in order.backorders" :key="b.id" class="text-sm flex items-center gap-2 flex-wrap">
              <Link :href="showRoute(b.id).url" class="font-semibold text-blue-900 dark:text-blue-100 hover:underline">
                #{{ b.order_number }}
              </Link>
              <span class="text-xs text-blue-700/80 dark:text-blue-300/80 capitalize">{{ statusLabel[b.status] ?? b.status.replace('_', ' ') }}</span>
              <span v-if="b.total_amount" class="text-xs text-blue-700/80 dark:text-blue-300/80 ml-auto font-mono">{{ Number(b.total_amount).toFixed(2) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Cancellation banner -->
      <div v-if="isCancelled" class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-900/50 px-5 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-red-700 dark:text-red-300">{{ t('Order cancelled') }}</div>
            <p class="mt-1 text-sm text-red-900 dark:text-red-100 whitespace-pre-line">
              {{ order.cancellation_reason || t('No reason was recorded.') }}
            </p>
            <p class="mt-2 text-xs text-red-700/80 dark:text-red-300/80">
              <span v-if="order.canceller">{{ t('By') }} {{ order.canceller.name }}</span>
              <span v-if="order.cancelled_at_human"> · {{ order.cancelled_at_human }}</span>
              <span v-if="order.cancelled_at_formatted" class="ml-1 opacity-70">({{ order.cancelled_at_formatted }})</span>
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Client info -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">{{ t('Client') }}</h2>
          <p class="font-semibold text-gray-900 dark:text-white">
            <Link :href="adminMode ? `/admin/clients/${order.client.id}` : '#'" :class="adminMode ? 'hover:underline hover:text-blue-600 transition-colors' : 'cursor-default'">
              {{ order.contact_name || order.client.name }}
            </Link>
          </p>
          <p class="text-sm text-gray-500 flex items-center gap-2" v-if="order.contact_phone">
            {{ order.contact_phone }}
            <a :href="`tel:${order.contact_phone}`" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">
              <Phone class="w-4 h-4" />
            </a>
          </p>
          <p v-if="order.contact_name && order.client.name !== order.contact_name" class="text-[11px] text-gray-400 mt-1">
            {{ t('Linked to account') }}: {{ order.client.name }}
          </p>
          <p class="text-sm text-gray-500" :class="{ 'mt-2': order.contact_name }">{{ order.client.email }}</p>
          <p class="text-sm text-gray-500 flex items-center gap-2" v-if="order.client.phone && order.client.phone !== order.contact_phone">
            {{ order.client.phone }}
            <a :href="`tel:${order.client.phone}`" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">
              <Phone class="w-4 h-4" />
            </a>
          </p>
          <p class="text-sm text-gray-500 mt-1" v-if="order.client.user_profile?.region">
            📍 {{ order.client.user_profile.region }}
          </p>
        </div>

        <!-- Delivery -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">{{ t('Delivery') }}</h2>
          <p class="text-sm text-gray-700 dark:text-gray-300">
            <span class="font-medium uppercase text-[10px] tracking-wider text-gray-400 block mb-1">{{ t('Scheduled') }}:</span>
            <span v-if="order.scheduled_delivery_at_human" class="block font-semibold text-base">{{ order.scheduled_delivery_at_human }}</span>
            <span class="text-xs text-gray-500">{{ order.scheduled_delivery_at_formatted ?? order.scheduled_delivery_at ?? '—' }}</span>
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-4" v-if="order.actual_delivery_at">
            <span class="font-medium uppercase text-[10px] tracking-wider text-gray-400 block mb-1">{{ t('Actual') }}:</span>
            <span v-if="order.actual_delivery_at_human" class="block font-semibold text-base text-green-600">{{ order.actual_delivery_at_human }}</span>
            <span class="text-xs text-gray-500">{{ order.actual_delivery_at_formatted ?? order.actual_delivery_at }}</span>
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
            <span class="font-medium">{{ t('Address') }}:</span>
            <span v-if="order.delivery_address === 'Self Pickup'" class="ml-1 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wide bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 px-2 py-0.5 rounded">
              🏬 {{ t('Self Pickup') }}
            </span>
            <MapChooser v-else :lat="order.lat" :lng="order.lng" :address="order.delivery_address" class="ml-1" />
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-300 mt-2" v-if="order.notes">
            <span class="font-medium">{{ t('Notes') }}:</span> {{ order.notes }}
          </p>

          <div v-if="adminMode && isSelfPickup && !isCancelled" class="mt-4 pt-4 border-t dark:border-gray-700">
            <div class="flex items-center gap-2 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40 rounded-lg px-3 py-2">
              🏬 {{ t('Self-pickup order — no courier needed.') }}
            </div>
          </div>
          <div v-else-if="adminMode && !isCancelled && assignRoute" class="mt-4 pt-4 border-t dark:border-gray-700">
            <label class="text-[10px] uppercase font-bold text-gray-400 block mb-2 tracking-wider font-mono">{{ t('Currier Assignment') }}</label>
            <div class="flex items-center gap-2">
              <Button @click="isAssignModalOpen = true" variant="outline" class="w-full justify-between font-normal h-12" :class="order.courier ? 'text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-900/20 shadow-none' : ''">
                <span v-if="order.courier" class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-sm shadow-blue-500/40"></span>
                  {{ t('Assigned to') }}: <span class="font-bold">{{ order.courier.name }}</span>
                </span>
                <span v-else>{{ t('Click to assign currier...') }}</span>
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </div>
          </div>
          <!-- Couriers see who the order is assigned to but cannot reassign. -->
          <div v-else-if="adminMode && !isCancelled && order.courier" class="mt-4 pt-4 border-t dark:border-gray-700">
            <label class="text-[10px] uppercase font-bold text-gray-400 block mb-2 tracking-wider font-mono">{{ t('Currier Assignment') }}</label>
            <p class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
              <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-sm shadow-blue-500/40"></span>
              {{ t('Assigned to') }}: <span class="font-bold">{{ order.courier.name }}</span>
            </p>
          </div>
        </div>

        <!-- Payment summary — admin only. Clients see no monetary totals. -->
        <div v-if="adminMode" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">{{ t('Payment') }}</h2>
          <div class="space-y-1 text-sm">
            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between">
              <span class="text-gray-500">{{ t('Subtotal') }}</span>
              <span class="text-gray-700 dark:text-gray-300">{{ (Number(order.total_amount) + Number(order.discount_amount)).toFixed(2) }}</span>
            </div>
            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between">
              <span class="text-pink-600 dark:text-pink-300 font-medium">{{ t('Discount') }}</span>
              <span class="text-pink-600 dark:text-pink-300 font-medium">-{{ Number(order.discount_amount).toFixed(2) }}</span>
            </div>
            <div v-else-if="Number(order.discount_amount) < 0" class="flex justify-between">
              <span class="text-orange-600 dark:text-orange-300 font-medium">{{ t('Surcharge') }}</span>
              <span class="text-orange-600 dark:text-orange-300 font-medium">+{{ Math.abs(Number(order.discount_amount)).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">{{ t('Total') }}</span>
              <span class="font-semibold text-gray-900 dark:text-white">{{ order.total_amount }}</span>
            </div>
            <div v-if="Number(order.deposit_charge) > 0" class="flex justify-between">
              <span class="text-blue-700 dark:text-blue-300 font-medium">{{ t('Bottle deposit') }}</span>
              <span class="text-blue-700 dark:text-blue-300 font-medium">+{{ Number(order.deposit_charge).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">{{ t('Paid') }}</span>
              <span class="text-green-600 font-medium">{{ order.paid_amount }}</span>
            </div>
            <div class="flex justify-between border-t dark:border-gray-700 pt-1 mt-1">
              <span class="text-gray-700 dark:text-gray-300 font-medium">{{ t('Balance due') }}</span>
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
              {{ t(order.payment_status) }}
            </span>
          </div>

          <div class="mt-4 pt-4 border-t dark:border-gray-700" v-if="!isCancelled && order.balance_due > 0 && order.payment_status !== 'paid'">
            <button @click="openWalletPayModal" class="w-full flex items-center justify-center gap-2 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-bold shadow-lg shadow-green-500/20 transition-all active:scale-[0.98]">
              <Wallet class="size-5" />
              {{ t('Pay') }}
            </button>
            <p class="text-[10px] text-center text-gray-400 mt-2 uppercase tracking-widest">{{ t('Use wallet balance or record another payment') }}</p>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b dark:border-gray-700">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ t('Items') }}</h2>
        </div>
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th class="px-6 py-3">{{ t('Product') }}</th>
              <th class="px-6 py-3 text-right">{{ t('Qty') }}</th>
              <th v-if="adminMode" class="px-6 py-3 text-right">{{ t('Unit Price') }}</th>
              <th v-if="adminMode" class="px-6 py-3 text-right">{{ t('Subtotal') }}</th>
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
                      {{ t('Gift') }}
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3 text-right">
                <span>{{ item.quantity }}</span>
                <span
                  v-if="item.delivered_quantity != null && item.delivered_quantity !== item.quantity"
                  class="ml-2 inline-flex items-center gap-1 rounded-md bg-amber-50 dark:bg-amber-900/30 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300"
                  :title="t('Delivered {delivered} of {ordered}', { delivered: item.delivered_quantity, ordered: item.quantity })"
                >
                  {{ t('Delivered') }} {{ item.delivered_quantity }}
                </span>
              </td>
              <td v-if="adminMode" class="px-6 py-3 text-right" :class="item.is_gift ? 'line-through text-gray-400' : ''">{{ item.unit_price }}</td>
              <td v-if="adminMode" class="px-6 py-3 text-right font-semibold" :class="item.is_gift ? 'text-pink-600 dark:text-pink-300' : 'text-gray-900 dark:text-white'">
                {{ item.is_gift ? t('Free') : item.subtotal }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="adminMode">
            <tr class="bg-gray-50 dark:bg-gray-700">
              <td colspan="3" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">{{ t('Items total') }}</td>
              <td class="px-6 py-3 text-right font-bold text-gray-900 dark:text-white">{{ order.total_amount }}</td>
            </tr>
            <tr v-if="Number(order.deposit_charge) > 0" class="bg-gray-50 dark:bg-gray-700">
              <td colspan="3" class="px-6 py-3 text-right font-semibold text-blue-700 dark:text-blue-300">{{ t('Bottle deposit') }}</td>
              <td class="px-6 py-3 text-right font-bold text-blue-700 dark:text-blue-300">+{{ Number(order.deposit_charge).toFixed(2) }}</td>
            </tr>
            <tr v-if="Number(order.deposit_charge) > 0" class="bg-gray-50 dark:bg-gray-700 border-t dark:border-gray-600">
              <td colspan="3" class="px-6 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ t('Grand total') }}</td>
              <td class="px-6 py-3 text-right font-bold text-gray-900 dark:text-white">
                {{ (Number(order.total_amount) + Number(order.deposit_charge)).toFixed(2) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Returned Materials -->
      <div v-if="order.returned_materials?.length > 0" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden border border-blue-100 dark:border-blue-900/50">
        <div class="px-4 py-4 sm:px-6 border-b border-blue-100 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-900/20 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-blue-800 dark:text-blue-300 uppercase tracking-wider flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-check"><path d="m16 16 2 2 4-4"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="m7.5 4.27 9 5.15"/><polyline points="3.29 7.08 12 12 20.71 7.08"/><line x1="12" x2="12" y1="22" y2="12"/></svg>
            {{ t('Returned Materials Log') }}
          </h2>
          <span class="text-xs bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 py-0.5 px-2.5 rounded-full font-bold">{{ t('Collected & pending') }}</span>
        </div>
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-white dark:bg-gray-800 dark:text-gray-400">
            <tr>
              <th class="px-6 py-3 font-semibold">{{ t('Material Model') }}</th>
              <th class="px-6 py-3 text-right font-semibold">{{ t('Collected') }}</th>
              <th class="px-6 py-3 text-right font-semibold">{{ t('To collect later') }}</th>
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
              <td class="px-6 py-3.5 text-right">
                <div v-if="(item.pivot.deferred_quantity || 0) > 0" class="inline-flex items-center gap-2">
                  <span class="font-bold text-amber-600 dark:text-amber-400 text-base">⏳ {{ item.pivot.deferred_quantity }}</span>
                  <Button
                    size="sm"
                    variant="outline"
                    class="h-7 text-xs border-green-500 text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-950"
                    :disabled="collectingDeferred.includes(item.id)"
                    @click="collectDeferred(item.id)"
                  >
                    <Loader2 v-if="collectingDeferred.includes(item.id)" class="h-3 w-3 animate-spin" />
                    <Check v-else class="h-3 w-3" />
                    {{ t('Collected') }}
                  </Button>
                </div>
                <span v-else class="text-gray-300 dark:text-gray-600">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Courier Assignment Modal -->
      <Dialog v-model:open="isAssignModalOpen">
        <DialogContent class="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ t('Assign Currier') }}</DialogTitle>
            <DialogDescription>
              {{ t('Select an available currier to process this order.') }}
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
                  <p class="font-bold text-gray-900 dark:text-gray-100">{{ t('Unassign Order') }}</p>
                  <p class="text-xs text-gray-500">{{ t('Remove currently assigned currier.') }}</p>
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
                  <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white dark:border-gray-800" :class="isCurrierOnline(courier) ? 'bg-green-500' : 'bg-gray-400'" :title="isCurrierOnline(courier) ? t('Online') : t('Offline')"></span>
                </div>
                
                <div class="flex-1 min-w-0">
                  <p class="font-bold text-gray-900 dark:text-gray-100 truncate flex items-center gap-2">
                    {{ courier.name }}
                    <span v-if="order.courier_id === courier.id" class="text-[10px] uppercase font-black tracking-wider text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/50 px-2 py-0.5 rounded text-center">{{ t('Current') }}</span>
                  </p>
                  <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                      <div class="w-2 h-2 rounded-full" :class="isCurrierOnline(courier) ? 'bg-green-500' : 'bg-gray-400'"></div>
                      {{ isCurrierOnline(courier) ? t('Online') : t('Offline') }}
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex items-center gap-1" :class="courier.orders_count > 0 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                      <Box class="w-3 h-3" />
                      {{ courier.orders_count }} {{ t('active tasks') }}
                    </span>
                  </div>
                </div>
              </div>
            </button>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Overpayment refund confirmation -->
      <Dialog v-model:open="isOverpaymentRefundModalOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ t('Return overpayment to wallet?') }}</DialogTitle>
            <DialogDescription>
              {{ t("Credit {amount} to {name}'s wallet and align the order's paid amount with the new total.", { amount: (overpaymentOnOrder || (page.props.flash as any)?.pending_overpayment_refund?.amount || 0).toFixed(2), name: order.client.name }) }}
            </DialogDescription>
          </DialogHeader>
          <div class="flex justify-end gap-3 pt-2">
            <Button type="button" variant="outline" :disabled="overpaymentRefundProcessing" @click="isOverpaymentRefundModalOpen = false">
              {{ t('Not now') }}
            </Button>
            <Button
              type="button"
              class="bg-amber-600 hover:bg-amber-700 text-white"
              :disabled="overpaymentRefundProcessing"
              @click="confirmOverpaymentRefund"
            >
              {{ overpaymentRefundProcessing ? t('Processing…') : t('Refund to wallet') }}
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Wallet payment confirmation -->
      <Dialog v-model:open="isWalletPayModalOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ t('Pay this order') }}</DialogTitle>
            <DialogDescription>
              {{ t('Balance due') }}: <span class="font-semibold">{{ Number(order.balance_due).toFixed(2) }} {{ ($page.props.currency as string) || '' }}</span>
            </DialogDescription>
          </DialogHeader>

          <div class="py-3 space-y-4">
            <!-- Option 1: use the client's existing wallet balance -->
            <div class="rounded-xl border border-blue-200 dark:border-blue-900/40 bg-blue-50/60 dark:bg-blue-900/15 p-4 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider font-bold text-blue-700 dark:text-blue-300">{{ t('Existing wallet balance') }}</span>
                <span class="font-mono text-lg font-bold text-blue-900 dark:text-blue-100">
                  {{ walletBalance.toFixed(2) }}
                  <span class="text-xs font-normal text-blue-700/80 dark:text-blue-300/80 ml-1">{{ ($page.props.currency as string) || '' }}</span>
                </span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ t("Applies real funds already in {name}'s wallet — up to {amount} now.", { name: order.client.name, amount: Math.min(walletBalance, Number(order.balance_due)).toFixed(2) }) }}
                <span v-if="walletBalance < Number(order.balance_due)">{{ t('Not enough to cover the full balance; the remainder stays due.') }}</span>
              </p>
              <Button
                type="button"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white"
                :disabled="payFromBalanceProcessing || walletBalance <= 0"
                @click="confirmPayFromBalance"
              >
                <Loader2 v-if="payFromBalanceProcessing" class="w-4 h-4 mr-2 animate-spin" />
                <Wallet v-else class="w-4 h-4 mr-2" />
                {{ payFromBalanceProcessing ? t('Processing…') : (walletBalance <= 0 ? t('No wallet balance available') : t('Use wallet balance')) }}
              </Button>
            </div>

            <!-- Option 2: staff received payment out-of-band (cash, etc.) — record it via the wallet ledger -->
            <div class="rounded-xl border border-green-200 dark:border-green-900/40 bg-green-50/60 dark:bg-green-900/15 p-4 space-y-2">
              <span class="text-xs uppercase tracking-wider font-bold text-green-700 dark:text-green-300">{{ t('Received payment another way') }}</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ t('Tops the wallet up for the full balance and immediately applies it — use this when the client already paid you directly (cash, transfer) and you just need to record it.') }}
              </p>
              <Button
                type="button"
                variant="outline"
                class="w-full border-green-300 dark:border-green-900/50 text-green-700 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-900/30"
                :disabled="walletPayProcessing"
                @click="confirmWalletPay"
              >
                <Loader2 v-if="walletPayProcessing" class="w-4 h-4 mr-2 animate-spin" />
                {{ walletPayProcessing ? t('Processing…') : t('Top up & pay in full') }}
              </Button>
            </div>

            <div class="flex justify-end pt-2 border-t dark:border-gray-700">
              <Button type="button" variant="outline" :disabled="walletPayProcessing || payFromBalanceProcessing" @click="isWalletPayModalOpen = false">{{ t('Cancel') }}</Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Hard-delete confirmation. Requires the admin to type the order
           number, since deletion cascades into items and detaches any
           backorder children (parent_order_id → NULL). -->
      <Dialog v-model:open="isDeleteModalOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold text-red-700 dark:text-red-300">{{ t('Delete this order?') }}</DialogTitle>
            <DialogDescription>
              {{ t('The order, its line items, and its returned-materials records are removed permanently. Inventory is restored for any items still considered out of stock. Wallet transactions tied to this order stay in the ledger as historical record.') }}
            </DialogDescription>
          </DialogHeader>

          <div class="py-3 space-y-3">
            <div v-if="(order.backorders?.length ?? 0) > 0" class="rounded-lg border border-amber-200 dark:border-amber-900/40 bg-amber-50/70 dark:bg-amber-900/15 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
              ⚠ {{ t('This order has {count} backorder(s). They will remain but lose their link back to this order.', { count: order.backorders.length }) }}
            </div>
            <div v-if="Number(order.paid_amount) > 0" class="rounded-lg border border-amber-200 dark:border-amber-900/40 bg-amber-50/70 dark:bg-amber-900/15 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
              ⚠ {{ Number(order.paid_amount).toFixed(2) }} {{ ($page.props.currency as string) || '' }} {{ t('was paid against this order. Wallet entries stay; no automatic refund.') }}
            </div>

            <label class="text-xs uppercase font-bold text-gray-500 block">
              {{ t('Type') }} <span class="font-mono text-gray-900 dark:text-white">{{ deleteConfirmExpected }}</span> {{ t('to confirm') }}
            </label>
            <input
              v-model="deleteConfirmText"
              type="text"
              :placeholder="deleteConfirmExpected"
              class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm font-mono dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-500 outline-none"
              autocomplete="off"
            />

            <div class="flex justify-end gap-3 pt-3 border-t dark:border-gray-700">
              <Button type="button" variant="outline" :disabled="deleteProcessing" @click="isDeleteModalOpen = false">{{ t('Keep Order') }}</Button>
              <Button
                type="button"
                class="bg-red-600 hover:bg-red-700 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!canConfirmDelete || deleteProcessing"
                @click="confirmDelete"
              >
                <Loader2 v-if="deleteProcessing" class="w-4 h-4 mr-2 animate-spin" />
                <Trash2 v-else class="w-4 h-4 mr-2" />
                {{ deleteProcessing ? t('Deleting…') : t('Delete forever') }}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Cancellation Modal -->
      <Dialog v-model:open="isCancelModalOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ t('Cancel Order') }}</DialogTitle>
            <DialogDescription>
              {{ t('Record why this order is being cancelled. The note is kept for later statistics.') }}
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-3 py-2">
            <label class="text-xs uppercase font-bold text-gray-500 block">{{ t('Reason') }} *</label>
            <textarea
              v-model="cancelForm.cancellation_reason"
              rows="4"
              maxlength="1000"
              :placeholder="t('e.g. Client unreachable, duplicate order, wrong address…')"
              class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-500 outline-none"
            ></textarea>
            <p v-if="cancelError" class="text-xs text-red-600">{{ cancelError }}</p>

            <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700 mt-2">
              <Button type="button" variant="outline" @click="isCancelModalOpen = false">{{ t('Keep Order') }}</Button>
              <Button type="button" class="bg-red-600 hover:bg-red-700 text-white" @click="submitCancellation">
                {{ t('Cancel Order') }}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog v-model:open="isDeliveryModalOpen">
        <DialogContent class="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ t('Confirm Delivery') }}</DialogTitle>
            <DialogDescription>
              {{ t('Record the actual delivery time and log any bottles or containers returned by the client.') }}
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4 py-2 max-h-[70vh] overflow-y-auto">
            <div>
                <label class="text-xs uppercase font-bold text-gray-500 block mb-1">{{ t('Actual Delivery Time') }}</label>
                <input type="datetime-local" v-model="statusForm.actual_delivery_at" class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 w-full" />
            </div>

            <!-- Partial delivery: per-line actual delivered count. Default is
                 the ordered quantity; rows where the admin drops below get a
                 dismiss / deliver-later toggle. -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-4 space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-xs uppercase font-bold text-gray-500">{{ t('Delivered Quantities') }}</label>
                <span class="text-[10px] text-gray-400">{{ t('Ordered → Delivered') }}</span>
              </div>
              <div v-for="(line, idx) in statusForm.delivered_items" :key="line.order_item_id" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/60 p-3">
                <div class="flex items-center gap-3">
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ productName(props.order.items[idx]?.product?.name) }}</p>
                    <p class="text-[11px] text-gray-500">{{ t('Ordered') }} {{ props.order.items[idx]?.quantity }}</p>
                  </div>
                  <input
                    type="number"
                    min="0"
                    :max="props.order.items[idx]?.quantity"
                    v-model.number="line.delivered_quantity"
                    class="w-20 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg px-2 py-1.5 text-sm text-right outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div
                  v-if="line.delivered_quantity < (props.order.items[idx]?.quantity ?? 0)"
                  class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-gray-700 flex items-center gap-2 flex-wrap"
                >
                  <span class="text-[11px] uppercase font-bold tracking-wide text-amber-600 dark:text-amber-400">
                    {{ t('Short by') }} {{ (props.order.items[idx]?.quantity ?? 0) - line.delivered_quantity }}
                  </span>
                  <div class="ml-auto inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
                    <button
                      type="button"
                      @click="line.shortfall_action = 'dismiss'"
                      :class="[
                        'px-3 py-1.5 font-semibold transition-colors',
                        line.shortfall_action === 'dismiss'
                          ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900'
                          : 'bg-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50'
                      ]"
                    >{{ t('Dismiss') }}</button>
                    <button
                      type="button"
                      @click="line.shortfall_action = 'backorder'"
                      :class="[
                        'px-3 py-1.5 font-semibold border-l border-gray-200 dark:border-gray-700 transition-colors',
                        line.shortfall_action === 'backorder'
                          ? 'bg-blue-600 text-white'
                          : 'bg-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50'
                      ]"
                    >{{ t('Deliver later') }}</button>
                  </div>
                </div>
              </div>
              <p class="text-[11px] text-gray-500 leading-snug">
                <span class="font-semibold">{{ t('Dismiss') }}</span> {{ t('restores stock and reduces the bill;') }}
                <span class="font-semibold">{{ t('Deliver later') }}</span> {{ t('additionally creates a follow-up order for the shortfall.') }}
              </p>
            </div>

            <div v-if="statusForm.returned_materials.length > 0" class="rounded-lg border border-blue-200 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-900/10 p-4 space-y-3">
                <div>
                  <p class="text-xs uppercase font-bold text-blue-700 dark:text-blue-300">{{ t('Empty containers') }}</p>
                  <p class="text-[11px] text-blue-700/80 dark:text-blue-300/80">{{ t("Record how many empties you collected now and how many you'll collect later. Whatever is left is charged at the deposit price.") }}</p>
                </div>

                <div
                  v-for="rm in statusForm.returned_materials"
                  :key="rm.raw_material_id"
                  class="rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/60 p-3 space-y-2"
                >
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ reusableSummaryById[rm.raw_material_id]?.raw_material.name }}</span>
                    <span class="text-[11px] text-gray-500">{{ t('Expected') }} <span class="font-semibold text-gray-700 dark:text-gray-200">{{ reusableSummaryById[rm.raw_material_id]?.expected }}</span></span>
                  </div>

                  <div class="grid grid-cols-2 gap-2">
                    <label class="flex flex-col gap-1">
                      <span class="text-[11px] font-semibold text-gray-500 uppercase">{{ t('Collected now') }}</span>
                      <input
                        type="number" min="0" :max="reusableSummaryById[rm.raw_material_id]?.expected"
                        v-model.number="rm.quantity"
                        class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </label>
                    <label class="flex flex-col gap-1">
                      <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase">{{ t('Collect later') }}</span>
                      <input
                        type="number" min="0" :max="reusableSummaryById[rm.raw_material_id]?.expected"
                        v-model.number="rm.deferred_quantity"
                        class="bg-white dark:bg-gray-900 border border-amber-300 dark:border-amber-900/50 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-500"
                      />
                    </label>
                  </div>

                  <div class="flex items-center justify-between text-xs">
                    <span v-if="(rm.deferred_quantity || 0) > 0" class="text-amber-600 dark:text-amber-400 font-medium">⏳ {{ rm.deferred_quantity }} {{ t('to collect later') }}</span>
                    <span v-else></span>
                    <span v-if="lineCharge(rm) > 0" class="font-semibold text-red-600 dark:text-red-400">{{ t('Deposit charge') }} {{ lineCharge(rm).toFixed(2) }}</span>
                    <span v-else class="font-semibold text-green-600 dark:text-green-400">{{ t('No deposit charge') }}</span>
                  </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t mt-4">
              <Button type="button" variant="outline" @click="isDeliveryModalOpen = false">{{ t('Cancel') }}</Button>
              <Button type="button" class="bg-green-600 hover:bg-green-700 text-white" @click="submitStatusUpdate">{{ t('Save Delivery') }}</Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Repeat order: pick quantities + delivery time before creating. -->
      <RepeatOrderModal
        v-if="adminMode"
        v-model:open="isRepeatModalOpen"
        :items="repeatItems"
        :submit-url="`/admin/orders/${order.id}/repeat`"
        :order-number="order.order_number"
      />
    </div>
  </AppLayout>
</template>
