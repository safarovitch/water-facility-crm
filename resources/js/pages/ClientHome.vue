<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FannLogo from '@/components/FannLogo.vue';
import OrderStage from '@/components/OrderStage.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import RepeatOrderModal from '@/components/RepeatOrderModal.vue';
import { useEcho } from '@/composables/useEcho';
import {
  Phone,
  MapPin,
  User as UserIcon,
  ShoppingCart,
  Calendar,
  LayoutDashboard,
  LogOut,
  Package,
  RotateCcw,
} from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

interface OrderItem { id: number; quantity: number; is_gift?: boolean; product: { id: number; name: any; image_url?: string | null } }
interface OrderSummary {
  id: number;
  order_number: string;
  status: string;
  scheduled_delivery_at_human?: string | null;
  scheduled_delivery_at_formatted?: string | null;
  actual_delivery_at_human?: string | null;
  created_at_formatted?: string | null;
  created_at_human?: string | null;
  delivery_address?: string | null;
  notes?: string | null;
  items?: OrderItem[];
  courier?: { id: number; name: string; phone?: string | null } | null;
  cancellation_reason?: string | null;
}
interface Profile {
  id: number;
  name: string;
  email?: string | null;
  phone?: string | null;
  phones?: Array<{ id: number; phone: string; label: string; is_default: boolean }>;
  address?: { id: number; address_line: string; city?: string | null; region?: string | null; label?: string | null } | null;
}

const props = defineProps<{
  profile: Profile;
  activeOrder: OrderSummary | null;
  orderHistory: OrderSummary[];
}>();

const { t } = useI18n();

const statusBadge: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  confirmed: 'bg-blue-100 text-blue-800',
  in_production: 'bg-purple-100 text-purple-800',
  ready: 'bg-cyan-100 text-cyan-800',
  accepted: 'bg-indigo-100 text-indigo-800',
  in_transit: 'bg-sky-100 text-sky-800',
  delivered: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
};

const statusLabel = computed((): Record<string, string> => ({
  pending: t('Pending'),
  confirmed: t('Confirmed'),
  in_production: t('Preparing'),
  ready: t('Ready'),
  accepted: t('Picked up'),
  in_transit: t('On the way'),
  delivered: t('Delivered'),
  cancelled: t('Cancelled'),
}));

const resolveProductName = (name: any): string => {
  if (typeof name === 'string') return name;
  if (!name) return '—';
  return name?.en ?? name?.uz ?? Object.values(name)[0] ?? '—';
};

const itemSummary = (order: OrderSummary): string => {
  if (!order.items?.length) return '—';
  const first = resolveProductName(order.items[0].product?.name);
  const more = order.items.length - 1;
  return more > 0 ? `${first} +${more} ${t('more')}` : first;
};

const totalBottles = (order: OrderSummary): number =>
  (order.items ?? []).reduce((sum, it) => sum + (it.quantity ?? 0), 0);

const logout = () => router.post('/logout');

// Staff (any role beyond the plain Client role) get a shortcut into the
// admin dashboard; /admin itself is role-gated server-side.
const page = usePage();
const isStaff = computed(() =>
  ((page.props.auth as any)?.user?.roles ?? []).some((role: string) => role !== 'Client'),
);

// ── Repeat last order ─────────────────────────────────────────────────────────
// orderHistory is latest-first across all orders, so [0] is the same order the
// backend repeats via Order::latest()->first().
const lastOrder = computed(() => props.orderHistory[0] ?? null);
const isRepeatModalOpen = ref(false);
const repeatItems = computed(() =>
  (lastOrder.value?.items ?? []).map((item) => ({
    product_id: item.product.id,
    name: resolveProductName(item.product?.name),
    quantity: item.quantity,
    is_gift: item.is_gift,
  })),
);

// ── Order detail modal ────────────────────────────────────────────────────────
const detailOrder = ref<OrderSummary | null>(null);
const isDetailOpen = computed({
  get: () => detailOrder.value !== null,
  set: (v: boolean) => { if (!v) detailOrder.value = null; },
});

const openDetail = (order: OrderSummary) => { detailOrder.value = order; };

// ── Live order status updates via Echo ───────────────────────────────────────
const liveActiveOrder = ref<OrderSummary | null>(props.activeOrder);

let echoChannel: any = null;

onMounted(() => {
  const echo = useEcho();
  if (!echo || !props.profile.id) return;

  echoChannel = echo.private(`client.${props.profile.id}`)
    .listen('.App\\Events\\OrderStatusUpdated', (data: any) => {
      if (liveActiveOrder.value && liveActiveOrder.value.id === data.id) {
        liveActiveOrder.value = { ...liveActiveOrder.value, status: data.status, courier: data.courier };
      } else if (!liveActiveOrder.value && data.status !== 'delivered' && data.status !== 'cancelled') {
        router.reload({ only: ['activeOrder'] });
      }

      if (data.status === 'delivered' || data.status === 'cancelled') {
        liveActiveOrder.value = null;
        router.reload({ only: ['orderHistory'] });
      }
    });
});

onBeforeUnmount(() => {
  if (echoChannel) {
    echoChannel.stopListening('.App\\Events\\OrderStatusUpdated');
  }
});
</script>

<template>
  <Head :title="t('My account')" />

  <div class="min-h-screen bg-[#f6f7fb] dark:bg-slate-950 text-slate-900 dark:text-slate-100">
    <!-- Slim top bar (replaces the sidebar) -->
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 dark:bg-slate-900/85 dark:border-slate-800 backdrop-blur">
      <div class="mx-auto max-w-4xl flex items-center justify-between px-4 sm:px-6 py-3">
        <Link href="/" class="flex items-center text-slate-900 dark:text-white">
          <FannLogo variant="inline" class="h-8 w-auto" />
        </Link>
        <div class="flex items-center gap-2">
          <Link
            v-if="isStaff"
            href="/admin"
            class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            <LayoutDashboard class="h-4 w-4" />
            <span class="hidden sm:inline">{{ t('Dashboard') }}</span>
          </Link>
          <button
            type="button"
            @click="logout"
            class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            <LogOut class="h-4 w-4" />
            <span class="hidden sm:inline">{{ t('Sign out') }}</span>
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 sm:px-6 py-6 space-y-6">
      <!-- Greeting -->
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">{{ t('Hi, {name}', { name: profile.name?.split(' ')[0] ?? '' }) }}</h1>
          <p class="text-sm text-slate-500">{{ t('Welcome back to fann.') }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-if="orderHistory.length"
            type="button"
            @click="isRepeatModalOpen = true"
            class="inline-flex h-10 items-center gap-2 rounded-full border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            <RotateCcw class="h-4 w-4" />
            {{ t('Repeat last order') }}
          </button>
          <Link
            href="/orders/create"
            class="inline-flex h-10 items-center gap-2 rounded-full bg-sky-500 px-5 text-sm font-semibold text-white shadow-sm hover:bg-sky-600"
          >
            <ShoppingCart class="h-4 w-4" />
            {{ t('Order water') }}
          </Link>
        </div>
      </div>

      <!-- Active order stage (only when one exists) -->
      <OrderStage v-if="liveActiveOrder" :order="liveActiveOrder" />

      <!-- Profile + contact -->
      <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold flex items-center gap-2">
            <UserIcon class="h-4 w-4 text-slate-500" />
            {{ t('Profile') }}
          </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div class="space-y-1">
            <p class="text-[11px] uppercase tracking-wider text-slate-400">{{ t('Name') }}</p>
            <p class="font-medium text-slate-900 dark:text-slate-100">{{ profile.name }}</p>
          </div>
          <div v-if="profile.email" class="space-y-1">
            <p class="text-[11px] uppercase tracking-wider text-slate-400">{{ t('Email') }}</p>
            <p class="font-medium text-slate-900 dark:text-slate-100">{{ profile.email }}</p>
          </div>
          <div class="space-y-1">
            <p class="text-[11px] uppercase tracking-wider text-slate-400 flex items-center gap-1">
              <Phone class="h-3 w-3" />
              {{ t('Phone') }}
            </p>
            <p class="font-medium text-slate-900 dark:text-slate-100">
              {{ profile.phone ?? '—' }}
            </p>
          </div>
          <div class="space-y-1">
            <p class="text-[11px] uppercase tracking-wider text-slate-400 flex items-center gap-1">
              <MapPin class="h-3 w-3" />
              {{ t('Default address') }}
            </p>
            <p class="font-medium text-slate-900 dark:text-slate-100">
              <template v-if="profile.address">{{ profile.address.address_line }}<span v-if="profile.address.city"> · {{ profile.address.city }}</span></template>
              <template v-else>— {{ t('no address') }} —</template>
            </p>
          </div>
        </div>
      </section>

      <!-- Order history -->
      <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold flex items-center gap-2">
            <Package class="h-4 w-4 text-slate-500" />
            {{ t('Order history') }}
          </h2>
          <span class="text-xs text-slate-400">{{ orderHistory.length }} {{ t('order(s)') }}</span>
        </div>

        <p v-if="orderHistory.length === 0" class="text-sm text-slate-500 italic">
          {{ t("You haven't placed any orders yet.") }}
        </p>

        <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
          <li
            v-for="order in orderHistory"
            :key="order.id"
            @click="openDetail(order)"
            class="flex items-center gap-3 py-3 cursor-pointer rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/40 px-2 -mx-2 transition"
          >
            <div class="h-10 w-10 rounded-full bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center text-sky-700 dark:text-sky-300 shrink-0">
              <Package class="h-4 w-4" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="font-mono text-sm font-semibold truncate">#{{ order.order_number }}</p>
                <span
                  :class="['text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full', statusBadge[order.status] ?? 'bg-slate-100 text-slate-700']"
                >
                  {{ statusLabel[order.status] ?? order.status }}
                </span>
              </div>
              <p class="text-xs text-slate-500 truncate">{{ itemSummary(order) }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-slate-400 flex items-center gap-1 justify-end">
                <Calendar class="h-3 w-3" />
                {{ order.created_at_formatted ?? order.created_at_human }}
              </p>
            </div>
          </li>
        </ul>
      </section>
    </main>

    <!-- Read-only order detail modal -->
    <Dialog v-model:open="isDetailOpen">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle v-if="detailOrder" class="font-mono text-lg">#{{ detailOrder.order_number }}</DialogTitle>
          <DialogDescription v-if="detailOrder">
            <span
              :class="['inline-block text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full mt-1', statusBadge[detailOrder.status] ?? 'bg-slate-100 text-slate-700']"
            >
              {{ statusLabel[detailOrder.status] ?? detailOrder.status }}
            </span>
          </DialogDescription>
        </DialogHeader>

        <div v-if="detailOrder" class="space-y-4 text-sm">
          <!-- Cancellation reason -->
          <div v-if="detailOrder.status === 'cancelled' && detailOrder.cancellation_reason" class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-900/40 p-3">
            <p class="text-[10px] font-bold uppercase tracking-wide text-red-700 dark:text-red-300">{{ t('Cancellation reason') }}</p>
            <p class="mt-1 text-sm text-red-900 dark:text-red-100">{{ detailOrder.cancellation_reason }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-[10px] uppercase tracking-wider text-slate-400">{{ t('Created') }}</p>
              <p class="font-medium">{{ detailOrder.created_at_formatted ?? detailOrder.created_at_human ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[10px] uppercase tracking-wider text-slate-400">{{ t('Scheduled') }}</p>
              <p class="font-medium">{{ detailOrder.scheduled_delivery_at_formatted ?? detailOrder.scheduled_delivery_at_human ?? '—' }}</p>
            </div>
            <div v-if="detailOrder.status === 'delivered' && detailOrder.actual_delivery_at_human" class="col-span-2">
              <p class="text-[10px] uppercase tracking-wider text-slate-400">{{ t('Delivered') }}</p>
              <p class="font-medium">{{ detailOrder.actual_delivery_at_human }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-[10px] uppercase tracking-wider text-slate-400">{{ t('Delivery address') }}</p>
              <p class="font-medium">{{ detailOrder.delivery_address ?? '—' }}</p>
            </div>
            <div v-if="detailOrder.notes" class="col-span-2">
              <p class="text-[10px] uppercase tracking-wider text-slate-400">{{ t('Notes') }}</p>
              <p class="font-medium whitespace-pre-line">{{ detailOrder.notes }}</p>
            </div>
          </div>

          <!-- Items (no prices, no totals) -->
          <div>
            <p class="text-[10px] uppercase tracking-wider text-slate-400 mb-2">{{ t('Items') }}</p>
            <ul class="rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
              <li v-for="item in detailOrder.items" :key="item.id" class="flex items-center gap-3 px-3 py-2">
                <div class="h-8 w-8 rounded bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-600">
                  {{ item.quantity }}×
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ resolveProductName(item.product?.name) }}</p>
                </div>
                <span v-if="item.is_gift" class="text-[10px] uppercase tracking-wide bg-pink-100 text-pink-700 px-2 py-0.5 rounded font-bold">{{ t('Gift') }}</span>
              </li>
              <li v-if="!detailOrder.items?.length" class="px-3 py-3 text-xs italic text-slate-500">{{ t('No items recorded.') }}</li>
            </ul>
            <p class="mt-2 text-xs text-slate-500">{{ t('Total bottles') }}: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ totalBottles(detailOrder) }}</span></p>
          </div>

          <div v-if="detailOrder.courier" class="rounded-lg bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs">
            <p class="text-[10px] uppercase tracking-wider text-slate-400">{{ t('Courier') }}</p>
            <div class="flex items-center justify-between mt-1">
              <p class="font-medium text-sm">{{ detailOrder.courier.name }}</p>
              <a
                v-if="detailOrder.courier.phone"
                :href="`tel:${detailOrder.courier.phone}`"
                class="text-xs font-semibold text-sky-600 hover:text-sky-700"
              >{{ t('Call') }}</a>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Repeat last order: pick quantities + delivery time before placing. -->
    <RepeatOrderModal
      v-if="lastOrder"
      v-model:open="isRepeatModalOpen"
      :items="repeatItems"
      :submit-url="'/orders/repeat-last'"
      :order-number="lastOrder.order_number"
    />
  </div>
</template>
