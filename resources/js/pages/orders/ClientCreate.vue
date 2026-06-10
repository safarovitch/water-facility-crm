<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FannLogo from '@/components/FannLogo.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import InputError from '@/components/InputError.vue';
import DeliveryTimePicker from '@/components/DeliveryTimePicker.vue';
import { ArrowLeft, MapPin, Plus, Minus, ShoppingCart, Package } from 'lucide-vue-next';

interface Address {
  id: number;
  label: string | null;
  address_line: string;
  city?: string | null;
  is_default?: boolean;
}

interface Product {
  id: number;
  name: Record<string, string> | string;
  price: string;
  sale_price: string;
  quantity: number;
  status: string;
}

const props = defineProps<{
  addresses: Address[];
  products: Product[];
}>();

const SELF_PICKUP_LABEL = 'Self Pickup';

const resolveProductName = (product: Product): string => {
  if (typeof product.name === 'string') return product.name;
  return product.name?.['en'] ?? product.name?.['uz'] ?? product.name?.['ru'] ?? Object.values(product.name ?? {})[0] ?? '—';
};

const effectivePrice = (p: Product): number => {
  const sale = parseFloat(p.sale_price);
  const price = parseFloat(p.price);
  return sale > 0 ? sale : price;
};

// quantities keyed by product id
const quantities = ref<Record<number, number>>({});

const inc = (id: number) => {
  quantities.value[id] = (quantities.value[id] ?? 0) + 1;
};
const dec = (id: number) => {
  const next = (quantities.value[id] ?? 0) - 1;
  if (next <= 0) {
    delete quantities.value[id];
  } else {
    quantities.value[id] = next;
  }
};

const cartItems = computed(() =>
  props.products
    .map((p) => ({ product: p, quantity: quantities.value[p.id] ?? 0 }))
    .filter((row) => row.quantity > 0),
);

const total = computed(() =>
  cartItems.value.reduce((sum, row) => sum + effectivePrice(row.product) * row.quantity, 0),
);

const totalBottles = computed(() =>
  cartItems.value.reduce((sum, row) => sum + row.quantity, 0),
);

// ── Delivery address selection ──────────────────────────────────────────────
const defaultAddress = props.addresses.find((a) => a.is_default) ?? props.addresses[0] ?? null;

const form = useForm({
  scheduled_delivery_at: '',
  delivery_address: defaultAddress?.address_line ?? '',
  new_address: '',
  new_address_label: '',
  notes: '',
  items: [] as { product_id: number; quantity: number }[],
});

const isAddingNew = ref(false);

const isSelfPickup = computed(() => form.delivery_address === SELF_PICKUP_LABEL);

const selectAddress = (addr: Address) => {
  form.delivery_address = addr.address_line;
  isAddingNew.value = false;
  form.new_address = '';
  form.new_address_label = '';
};

const selectSelfPickup = () => {
  form.delivery_address = SELF_PICKUP_LABEL;
  isAddingNew.value = false;
  form.new_address = '';
  form.new_address_label = '';
};

const toggleNewAddress = () => {
  isAddingNew.value = !isAddingNew.value;
  if (isAddingNew.value) {
    form.delivery_address = '';
  } else {
    form.new_address = '';
    form.new_address_label = '';
  }
};

const formatCurrency = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

const canSubmit = computed(
  () =>
    cartItems.value.length > 0 &&
    (form.delivery_address || (isAddingNew.value && form.new_address.trim().length > 0)) &&
    !form.processing,
);

const submit = () => {
  form.items = cartItems.value.map((row) => ({
    product_id: row.product.id,
    quantity: row.quantity,
  }));
  form.post('/orders/store');
};
</script>

<template>
  <Head title="Order water" />

  <div class="min-h-screen bg-[#f6f7fb] dark:bg-slate-950 text-slate-900 dark:text-slate-100">
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 dark:bg-slate-900/85 dark:border-slate-800 backdrop-blur">
      <div class="mx-auto max-w-4xl flex items-center justify-between px-4 sm:px-6 py-3">
        <Link href="/profile" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">
          <ArrowLeft class="h-4 w-4" />
          Back
        </Link>
        <FannLogo variant="inline" class="h-7 w-auto" />
        <span class="w-12" />
      </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 sm:px-6 py-6 space-y-6 pb-32">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Order water</h1>
        <p class="text-sm text-slate-500 mt-1">Pick your bottles and where you want them delivered.</p>
      </div>

      <!-- Product picker -->
      <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
        <h2 class="text-base font-semibold flex items-center gap-2 mb-4">
          <Package class="h-4 w-4 text-slate-500" />
          Choose products
        </h2>

        <p v-if="products.length === 0" class="text-sm text-slate-500 italic">
          No products are available right now.
        </p>

        <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
          <li v-for="product in products" :key="product.id" class="flex items-center gap-4 py-3">
            <div class="h-12 w-12 rounded-xl bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center text-sky-700 dark:text-sky-300 shrink-0">
              <Package class="h-5 w-5" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium truncate">{{ resolveProductName(product) }}</p>
              <p class="text-xs text-slate-500">
                <span class="font-mono">{{ formatCurrency(effectivePrice(product)) }}</span>
                <span v-if="parseFloat(product.sale_price) > 0 && parseFloat(product.sale_price) < parseFloat(product.price)"
                      class="ml-2 line-through text-slate-300">{{ formatCurrency(parseFloat(product.price)) }}</span>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="dec(product.id)"
                :disabled="(quantities[product.id] ?? 0) === 0"
                class="h-8 w-8 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <Minus class="h-4 w-4" />
              </button>
              <span class="w-8 text-center font-semibold tabular-nums">{{ quantities[product.id] ?? 0 }}</span>
              <button
                type="button"
                @click="inc(product.id)"
                class="h-8 w-8 rounded-full bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600"
              >
                <Plus class="h-4 w-4" />
              </button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Delivery address -->
      <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
        <h2 class="text-base font-semibold flex items-center gap-2 mb-4">
          <MapPin class="h-4 w-4 text-slate-500" />
          Delivery
        </h2>

        <div v-if="!isAddingNew" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            type="button"
            @click="selectSelfPickup"
            :class="[
              'p-3 rounded-xl border text-left text-sm transition-colors flex items-start gap-2',
              isSelfPickup
                ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20'
                : 'border-dashed border-slate-200 dark:border-slate-700 hover:border-amber-300',
            ]"
          >
            <span class="text-lg leading-none">🏬</span>
            <div>
              <div class="font-medium">Self pickup</div>
              <div class="text-xs text-slate-500 mt-1">I'll collect the order — no delivery needed.</div>
            </div>
          </button>

          <button
            v-for="addr in addresses"
            :key="addr.id"
            type="button"
            @click="selectAddress(addr)"
            :class="[
              'p-3 rounded-xl border text-left text-sm transition-colors',
              form.delivery_address === addr.address_line && !isSelfPickup
                ? 'border-sky-500 bg-sky-50 dark:bg-sky-900/20'
                : 'border-slate-200 dark:border-slate-700 hover:border-sky-300',
            ]"
          >
            <div class="font-medium">{{ addr.label ?? 'Address' }}</div>
            <div class="text-xs text-slate-500 mt-1 line-clamp-2">{{ addr.address_line }}<span v-if="addr.city"> · {{ addr.city }}</span></div>
          </button>
        </div>

        <div v-if="!isAddingNew" class="mt-3">
          <Button type="button" variant="link" size="sm" @click="toggleNewAddress" class="px-0 h-auto">
            + Use a different address
          </Button>
        </div>

        <div v-if="isAddingNew" class="space-y-3 rounded-xl border border-sky-100 dark:border-sky-900/40 bg-sky-50/40 dark:bg-sky-900/10 p-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-sky-900 dark:text-sky-100">New delivery address</h3>
            <Button type="button" variant="ghost" size="sm" @click="toggleNewAddress">Cancel</Button>
          </div>
          <div class="grid gap-3">
            <div class="grid gap-1.5">
              <Label for="new_address_label" class="text-xs">Label (e.g. Home, Office)</Label>
              <Input id="new_address_label" v-model="form.new_address_label" placeholder="Home" />
            </div>
            <div class="grid gap-1.5">
              <Label for="new_address" class="text-xs">Address *</Label>
              <textarea
                id="new_address"
                v-model="form.new_address"
                rows="2"
                class="block w-full rounded-md border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                placeholder="Street, building, apartment…"
              ></textarea>
            </div>
          </div>
        </div>

        <InputError :message="form.errors.delivery_address" class="mt-2" />
        <InputError :message="form.errors.new_address" class="mt-2" />
      </section>

      <!-- Schedule & notes -->
      <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6 space-y-4">
        <h2 class="text-base font-semibold">When &amp; notes</h2>

        <div class="grid gap-2">
          <Label for="scheduled_delivery_at">Preferred delivery date &amp; time <span class="text-xs text-slate-400 font-normal">(slots 09:00–20:00)</span></Label>
          <DeliveryTimePicker id="scheduled_delivery_at" v-model="form.scheduled_delivery_at" auto-default />
          <InputError :message="form.errors.scheduled_delivery_at" />
        </div>

        <div class="grid gap-2">
          <Label for="notes">Notes <span class="text-xs text-slate-400 font-normal">(optional)</span></Label>
          <textarea
            id="notes"
            v-model="form.notes"
            rows="2"
            class="block w-full rounded-md border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
            placeholder="Anything we should know? (gate code, floor, etc.)"
          ></textarea>
          <InputError :message="form.errors.notes" />
        </div>
      </section>

      <InputError :message="form.errors.items" />
    </main>

    <!-- Sticky checkout bar -->
    <div class="fixed bottom-0 inset-x-0 z-40 border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur">
      <div class="mx-auto max-w-4xl px-4 sm:px-6 py-3 flex items-center gap-3">
        <div class="flex-1 min-w-0">
          <p class="text-xs text-slate-500">
            {{ totalBottles }} item<span v-if="totalBottles !== 1">s</span>
          </p>
          <p class="text-lg font-semibold tabular-nums">{{ formatCurrency(total) }}</p>
        </div>
        <button
          type="button"
          @click="submit"
          :disabled="!canSubmit"
          class="inline-flex h-11 items-center gap-2 rounded-full bg-sky-500 px-6 text-sm font-semibold text-white shadow-sm hover:bg-sky-600 disabled:bg-slate-300 disabled:cursor-not-allowed"
        >
          <ShoppingCart class="h-4 w-4" />
          <span v-if="form.processing">Placing…</span>
          <span v-else>Place order</span>
        </button>
      </div>
    </div>
  </div>
</template>
