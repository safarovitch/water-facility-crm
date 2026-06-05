<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { index, show, update } from '@/routes/admin/orders';
import { computed, ref } from 'vue';

interface UserProfile { company_name: string | null; type: string; }
interface Client { id: number; name: string; email: string; user_profile: UserProfile | null; }
interface Product { id: number; name: Record<string, string> | string; price: string; sale_price: string; quantity: number; }

interface OrderItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: string | number;
  subtotal: string | number;
  is_gift: boolean;
  product?: Product;
}

interface Order {
  id: number;
  user_id: number;
  scheduled_delivery_at: string | null;
  delivery_address: string | null;
  notes: string | null;
  total_amount: string | number;
  discount_amount: string | number;
  paid_amount: string | number;
  deposit_charge?: string | number;
  items: OrderItem[];
  client?: { name: string };
}

const props = defineProps<{ order: Order; clients: Client[]; products: Product[]; }>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: index().url },
  { title: `Order #${props.order.id}`, href: show(props.order.id).url },
  { title: 'Edit', href: '#' },
];

interface LineItem { product_id: number | null; quantity: number; unit_price: number; subtotal: number; is_gift: boolean; }

// datetime-local expects "YYYY-MM-DDTHH:mm" with no timezone suffix; Laravel
// returns ISO strings like "2026-05-23T14:30:00.000000Z", so trim down.
const toDateTimeLocal = (iso: string | null): string => {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const initialCustomTotal: number | null = (() => {
  const total = Number(props.order.total_amount);
  const calculated = props.order.items.reduce(
    (sum, i) => sum + Number(i.subtotal),
    0,
  );
  if (isNaN(total) || isNaN(calculated)) return null;
  return Math.abs(total - calculated) < 0.005 ? null : total;
})();

const form = useForm({
  user_id: props.order.user_id as number | null,
  scheduled_delivery_at: toDateTimeLocal(props.order.scheduled_delivery_at),
  delivery_address: props.order.delivery_address ?? '',
  notes: props.order.notes ?? '',
  custom_total: initialCustomTotal,
  items: props.order.items.map<LineItem>(i => ({
    product_id: i.product_id,
    quantity: Number(i.quantity),
    unit_price: Number(i.unit_price),
    subtotal: Number(i.subtotal),
    is_gift: Boolean(i.is_gift),
  })),
});

const addItem = () => {
  form.items.push({ product_id: null, quantity: 1, unit_price: 0, subtotal: 0, is_gift: false });
};

const removeItem = (index: number) => {
  form.items.splice(index, 1);
};

const productMap = computed(() => {
  const map: Record<number, Product> = {};
  props.products.forEach(p => map[p.id] = p);
  return map;
});

const resolveProductName = (product: Product): string => {
  if (typeof product.name === 'string') return product.name;
  return product.name?.['en'] ?? product.name?.['uz'] ?? Object.values(product.name)[0] ?? '—';
};

const recalcSubtotal = (item: LineItem) => {
  item.subtotal = item.is_gift ? 0 : item.unit_price * item.quantity;
};

const onProductChange = (item: LineItem) => {
  if (!item.product_id) return;
  const p = productMap.value[item.product_id];
  if (!p) return;
  const price = parseFloat(p.sale_price) > 0 ? parseFloat(p.sale_price) : parseFloat(p.price);
  item.unit_price = price;
  recalcSubtotal(item);
};

const onQtyChange = (item: LineItem) => {
  recalcSubtotal(item);
};

const onGiftToggle = (item: LineItem) => {
  recalcSubtotal(item);
};

const total = computed(() => form.items.reduce((sum, i) => sum + i.subtotal, 0));

const effectiveTotal = computed(() =>
  form.custom_total !== null && form.custom_total !== undefined && !isNaN(Number(form.custom_total))
    ? Number(form.custom_total)
    : total.value,
);

const discount = computed(() => {
  if (form.custom_total === null || form.custom_total === undefined || isNaN(Number(form.custom_total))) return 0;
  return Number((total.value - Number(form.custom_total)).toFixed(2));
});

const clearCustomTotal = () => { form.custom_total = null; };

const paidAmount = computed(() => Number(props.order.paid_amount));
const depositCharge = computed(() => Number(props.order.deposit_charge ?? 0));
const projectedGrandTotal = computed(() => effectiveTotal.value + depositCharge.value);

const overpaymentAmount = computed(() => {
  const diff = paidAmount.value - projectedGrandTotal.value;
  return diff > 0.005 ? Number(diff.toFixed(2)) : 0;
});

const isRefundModalOpen = ref(false);

const postUpdate = (refundOverpayment: boolean, skipPendingRefundAlert = false) => {
  form
    .transform(data => ({
      ...data,
      refund_overpayment: refundOverpayment,
      skip_pending_overpayment_refund: skipPendingRefundAlert,
    }))
    .post(update(props.order.id).url, {
      onFinish: () => {
        isRefundModalOpen.value = false;
      },
    });
};

const submitForm = () => {
  if (overpaymentAmount.value > 0) {
    isRefundModalOpen.value = true;
    return;
  }
  postUpdate(false);
};

const confirmRefundAndSave = () => postUpdate(true);
const saveWithoutRefund = () => postUpdate(false, true);

const clientLabel = (c: Client) =>
  c.user_profile?.company_name ? `${c.name} (${c.user_profile.company_name})` : c.name;
</script>

<template>

  <Head :title="`Edit Order #${order.id}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="pb-6 bg-white dark:bg-gray-900 px-4 py-5 sm:px-6 rounded-t-lg">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Order #{{ order.id }}</h1>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Client & Delivery -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Client & Delivery</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
              <Label for="user_id">Client *</Label>
              <select
                id="user_id"
                v-model="form.user_id"
                required
                class="border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white"
              >
                <option :value="null">Select client...</option>
                <option v-for="c in props.clients" :key="c.id" :value="c.id">{{ clientLabel(c) }}</option>
              </select>
              <InputError :message="form.errors.user_id" />
            </div>
            <div class="grid gap-2">
              <Label for="scheduled_delivery_at">Scheduled Delivery Date & Time <span class="text-gray-400 font-normal text-xs">(past dates allowed)</span></Label>
              <Input id="scheduled_delivery_at" type="datetime-local" v-model="form.scheduled_delivery_at" step="60" />
              <InputError :message="form.errors.scheduled_delivery_at" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <Label for="delivery_address">Delivery Address</Label>
              <textarea id="delivery_address" v-model="form.delivery_address" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Street, building..."></textarea>
              <InputError :message="form.errors.delivery_address" />
            </div>
          </div>
        </div>

        <!-- Line Items -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Items</h2>
            <Button type="button" variant="outline" @click="addItem">+ Add Item</Button>
          </div>
          <InputError :message="form.errors.items" />

          <div v-if="form.items.length === 0" class="text-center py-8 text-gray-400">
            No items yet. Click "+ Add Item" to start.
          </div>

          <div v-for="(item, idx) in form.items" :key="idx" class="mb-3 border-b border-gray-100 dark:border-gray-700 pb-3">
            <div class="grid grid-cols-12 gap-3 items-end">
              <!-- Product -->
              <div class="col-span-5 grid gap-1">
                <Label>Product</Label>
                <select v-model="item.product_id" @change="onProductChange(item)" class="border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                  <option :value="null">Select product...</option>
                  <option v-for="p in props.products" :key="p.id" :value="p.id">{{ resolveProductName(p) }}</option>
                </select>
                <InputError :message="(form.errors as any)[`items.${idx}.product_id`]" />
              </div>
              <!-- Qty -->
              <div class="col-span-2 grid gap-1">
                <Label>Qty</Label>
                <Input type="number" min="1" v-model.number="item.quantity" @input="onQtyChange(item)" />
              </div>
              <!-- Unit price -->
              <div class="col-span-2 grid gap-1">
                <Label>Unit Price</Label>
                <Input type="number" step="0.01" v-model.number="item.unit_price" @input="onQtyChange(item)" :disabled="item.is_gift" />
              </div>
              <!-- Subtotal -->
              <div class="col-span-2 grid gap-1">
                <Label>Subtotal</Label>
                <Input type="number" :value="item.subtotal.toFixed(2)" readonly class="bg-gray-50 dark:bg-gray-700" />
              </div>
              <!-- Remove -->
              <div class="col-span-1 flex items-end pb-0.5">
                <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700 text-lg font-bold">✕</button>
              </div>
            </div>
            <!-- Gift toggle -->
            <div class="mt-2 flex items-center gap-2">
              <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs">
                <input
                  type="checkbox"
                  :checked="item.is_gift"
                  @change="item.is_gift = ($event.target as HTMLInputElement).checked; onGiftToggle(item)"
                  class="h-4 w-4 rounded border-gray-300 text-pink-600 focus:ring-pink-500"
                />
                <span class="font-medium text-gray-700 dark:text-gray-300">Gift (free)</span>
              </label>
              <span v-if="item.is_gift" class="text-[10px] uppercase tracking-wide bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300 px-2 py-0.5 rounded">
                Subtotal forced to 0
              </span>
            </div>
          </div>

          <!-- Total footer with custom pricing / discount -->
          <div v-if="form.items.length > 0" class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-4 space-y-2 text-sm">
            <div class="flex justify-end items-center gap-6">
              <span class="text-gray-500">Calculated</span>
              <span class="font-mono text-gray-700 dark:text-gray-300 w-32 text-right">{{ total.toFixed(2) }}</span>
            </div>

            <div class="flex justify-end items-center gap-6">
              <Label for="custom_total" class="text-gray-500 m-0 font-normal">
                Custom total
                <span class="text-[10px] text-gray-400 ml-1">(leave blank to use calculated)</span>
              </Label>
              <div class="flex items-center gap-1 w-32">
                <Input
                  id="custom_total"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.custom_total"
                  :placeholder="total.toFixed(2)"
                  class="text-right font-mono"
                />
                <button
                  v-if="form.custom_total !== null && form.custom_total !== undefined"
                  type="button"
                  @click="clearCustomTotal"
                  class="text-gray-400 hover:text-red-500 text-xs px-1"
                  title="Clear custom total"
                >✕</button>
              </div>
            </div>
            <InputError :message="form.errors.custom_total" />

            <div v-if="discount !== 0" class="flex justify-end items-center gap-6">
              <span :class="discount > 0 ? 'text-pink-600 dark:text-pink-300 font-medium' : 'text-orange-600 dark:text-orange-300 font-medium'">
                {{ discount > 0 ? 'Discount' : 'Surcharge' }}
              </span>
              <span class="font-mono w-32 text-right" :class="discount > 0 ? 'text-pink-600 dark:text-pink-300' : 'text-orange-600 dark:text-orange-300'">
                {{ discount > 0 ? '-' : '+' }}{{ Math.abs(discount).toFixed(2) }}
              </span>
            </div>

            <div class="flex justify-end items-center gap-6 border-t border-gray-200 dark:border-gray-600 pt-2">
              <span class="text-gray-900 dark:text-white font-bold uppercase text-xs tracking-wider">Order Total</span>
              <span class="font-mono font-bold text-base text-gray-900 dark:text-white w-32 text-right">{{ effectiveTotal.toFixed(2) }}</span>
            </div>
            <div
              v-if="overpaymentAmount > 0"
              class="flex justify-end items-center gap-6 rounded-lg border border-amber-200 dark:border-amber-900/40 bg-amber-50/70 dark:bg-amber-900/15 px-3 py-2"
            >
              <span class="text-amber-800 dark:text-amber-200 text-xs">
                Paid {{ paidAmount.toFixed(2) }} exceeds new total
                <span v-if="depositCharge > 0"> (incl. {{ depositCharge.toFixed(2) }} deposit)</span>
              </span>
              <span class="font-mono font-bold text-amber-700 dark:text-amber-300 w-32 text-right">+{{ overpaymentAmount.toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <div class="grid gap-2">
            <Label for="notes">Notes</Label>
            <textarea id="notes" v-model="form.notes" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
            <InputError :message="form.errors.notes" />
          </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2 pb-6">
          <Button type="button" @click="$inertia.visit(show(order.id).url)" variant="outline">Cancel</Button>
          <Button
            type="submit"
            :disabled="form.processing || form.items.length === 0 || !form.user_id"
          >
            <span v-if="form.processing">Saving...</span>
            <span v-else>Save Changes</span>
          </Button>
        </div>
      </form>

      <Dialog v-model:open="isRefundModalOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">Return overpayment to wallet?</DialogTitle>
            <DialogDescription>
              The client paid {{ paidAmount.toFixed(2) }}, but the new order total is
              {{ projectedGrandTotal.toFixed(2) }}.
              <span v-if="order.client?.name" class="block mt-2 font-medium text-gray-900 dark:text-white">
                Refund {{ overpaymentAmount.toFixed(2) }} to {{ order.client.name }}'s wallet?
              </span>
            </DialogDescription>
          </DialogHeader>
          <div class="flex justify-end gap-3 pt-2">
            <Button type="button" variant="outline" :disabled="form.processing" @click="saveWithoutRefund">
              Save without refund
            </Button>
            <Button
              type="button"
              class="bg-green-600 hover:bg-green-700 text-white"
              :disabled="form.processing"
              @click="confirmRefundAndSave"
            >
              {{ form.processing ? 'Saving…' : `Refund ${overpaymentAmount.toFixed(2)} & save` }}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
