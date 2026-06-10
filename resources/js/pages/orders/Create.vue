<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import DeliveryTimePicker from '@/components/DeliveryTimePicker.vue';
import ClientCombobox from '@/components/ClientCombobox.vue';
import { index, store } from '@/routes/admin/orders';
import { computed, ref } from 'vue';

interface UserProfile { company_name: string | null; type: string; }
interface Client { id: number; name: string; email: string; user_profile: UserProfile | null; }
interface Product { id: number; name: Record<string, string> | string; price: string; sale_price: string; quantity: number; }

const props = defineProps<{ clients: Client[]; products: Product[]; }>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: index().url },
  { title: 'New Order', href: '#' },
];

interface LineItem { product_id: number | null; quantity: number; unit_price: number; subtotal: number; is_gift: boolean; }

const SELF_PICKUP_LABEL = 'Self Pickup';

const form = useForm({
  user_id: null as number | null,
  scheduled_delivery_at: '',
  delivery_address: '',
  new_address: '',
  new_address_label: '',
  notes: '',
  items: [] as LineItem[],
  custom_total: null as number | null,
  new_contact: null as null | { name: string; phone: string; email: string },
});

const isNewContact = ref(false);

const toggleNewContact = () => {
  isNewContact.value = !isNewContact.value;
  if (isNewContact.value) {
    form.user_id = null;
    form.new_contact = { name: '', phone: '', email: '' };
  } else {
    form.new_contact = null;
  }
};

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

const submitForm = () => { form.post(store().url); };

const selectedClient = computed(() => props.clients.find(c => c.id === form.user_id));
const clientAddresses = computed(() => (selectedClient.value as any)?.addresses || []);

const isAddingNewAddress = ref(false);

const isSelfPickup = computed(() => form.delivery_address === SELF_PICKUP_LABEL);

const onAddressSelect = (addr: string) => {
  form.delivery_address = addr;
  isAddingNewAddress.value = false;
};

const selectSelfPickup = () => {
  form.delivery_address = SELF_PICKUP_LABEL;
  form.new_address = '';
  form.new_address_label = '';
  isAddingNewAddress.value = false;
};

const toggleNewAddress = () => {
  isAddingNewAddress.value = !isAddingNewAddress.value;
  if (isAddingNewAddress.value) {
    form.delivery_address = '';
  }
};
</script>

<template>

  <Head title="New Order" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="pb-6 bg-white dark:bg-gray-900 px-4 py-5 sm:px-6 rounded-t-lg">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create New Order</h1>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Client & Delivery -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Client & Delivery</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
              <div class="flex items-center justify-between">
                <Label for="user_id">Client *</Label>
                <button
                  type="button"
                  @click="toggleNewContact"
                  class="text-xs font-medium text-sky-600 hover:text-sky-700"
                >
                  {{ isNewContact ? '← Pick existing client' : '+ New walk-in client' }}
                </button>
              </div>

              <ClientCombobox
                v-if="!isNewContact"
                id="user_id"
                v-model="form.user_id"
                :clients="props.clients"
              />

              <div
                v-else
                class="rounded-xl border border-dashed border-sky-300 bg-sky-50/40 dark:border-sky-800 dark:bg-sky-900/10 p-4 grid grid-cols-1 sm:grid-cols-2 gap-3"
              >
                <p class="sm:col-span-2 text-xs text-sky-800 dark:text-sky-200">
                  A client profile will be created from these details. If they later register with the same phone or email, the account will be adopted.
                </p>
                <div class="grid gap-1">
                  <Label class="text-xs">Name *</Label>
                  <Input v-model="form.new_contact!.name" placeholder="Client name" />
                  <InputError :message="(form.errors as any)['new_contact.name']" />
                </div>
                <div class="grid gap-1">
                  <Label class="text-xs">Phone *</Label>
                  <Input v-model="form.new_contact!.phone" placeholder="+992 …" />
                  <InputError :message="(form.errors as any)['new_contact.phone']" />
                </div>
                <div class="grid gap-1 sm:col-span-2">
                  <Label class="text-xs">Email <span class="text-gray-400 font-normal">(optional)</span></Label>
                  <Input v-model="form.new_contact!.email" type="email" placeholder="client@example.com" />
                  <InputError :message="(form.errors as any)['new_contact.email']" />
                </div>
              </div>

              <InputError :message="form.errors.user_id" />
            </div>
            <div class="grid gap-2">
              <Label for="scheduled_delivery_at">Scheduled Delivery Date & Time <span class="text-gray-400 font-normal text-xs">(slots 09:00–20:00)</span></Label>
              <DeliveryTimePicker id="scheduled_delivery_at" v-model="form.scheduled_delivery_at" auto-default />
              <InputError :message="form.errors.scheduled_delivery_at" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <Label>Delivery Address</Label>
              <div v-if="!isAddingNewAddress" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                <!-- Self pickup option -->
                <div
                  @click="selectSelfPickup"
                  :class="[
                    'p-3 rounded-lg border cursor-pointer transition-colors text-sm flex items-start gap-2',
                    isSelfPickup
                      ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20'
                      : 'border-dashed border-gray-300 dark:border-gray-600 hover:border-amber-400 dark:hover:border-amber-700'
                  ]">
                  <span class="text-lg leading-none">🏬</span>
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">Self Pickup</div>
                    <div class="text-xs text-gray-500 mt-1">Client will collect the order — no delivery needed.</div>
                  </div>
                </div>

                <!-- Existing client addresses -->
                <div v-for="addr in clientAddresses" :key="addr.id"
                  @click="onAddressSelect(addr.address_line)"
                  :class="[
                    'p-3 rounded-lg border cursor-pointer transition-colors text-sm',
                    form.delivery_address === addr.address_line && !isSelfPickup
                      ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20'
                      : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700'
                  ]">
                  <div class="font-medium text-gray-900 dark:text-white">{{ addr.label }}</div>
                  <div class="text-xs text-gray-500 mt-1 line-clamp-2">{{ addr.address_line }}</div>
                </div>
              </div>

              <div v-if="!isAddingNewAddress" class="flex justify-start">
                <Button type="button" variant="link" size="sm" @click="toggleNewAddress" class="px-0 h-auto">
                  + Add new address for this client
                </Button>
              </div>

              <div v-if="isAddingNewAddress" class="space-y-3 p-4 rounded-xl border border-blue-100 bg-blue-50/30 dark:border-blue-900/30 dark:bg-blue-900/10">
                <div class="flex items-center justify-between">
                  <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100">Adding New Address</h3>
                  <Button type="button" variant="ghost" size="sm" @click="toggleNewAddress">Cancel</Button>
                </div>
                <div class="grid gap-3">
                  <div class="grid gap-1.5">
                    <Label for="new_address_label" class="text-xs">Address Label (e.g. Home, Office)</Label>
                    <Input id="new_address_label" v-model="form.new_address_label" placeholder="Office" />
                  </div>
                  <div class="grid gap-1.5">
                    <Label for="new_address" class="text-xs">Address Line *</Label>
                    <textarea id="new_address" v-model="form.new_address" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Street, building..."></textarea>
                  </div>
                </div>
              </div>

              <div v-if="!isAddingNewAddress && clientAddresses.length === 0 && !isSelfPickup" class="mt-2 p-4 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                <p class="text-xs text-gray-500 mb-2">No addresses found for this client.</p>
                <Button type="button" variant="outline" size="sm" @click="toggleNewAddress">
                  Add First Address
                </Button>
              </div>

              <InputError :message="form.errors.delivery_address" />
              <InputError :message="form.errors.new_address" />
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
            <!-- Desktop: 12-col grid -->
            <div class="hidden md:grid grid-cols-12 gap-3 items-end">
              <div class="col-span-5 grid gap-1">
                <Label>Product</Label>
                <select v-model="item.product_id" @change="onProductChange(item)" class="border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                  <option :value="null">Select product...</option>
                  <option v-for="p in props.products" :key="p.id" :value="p.id">{{ resolveProductName(p) }}</option>
                </select>
                <InputError :message="(form.errors as any)[`items.${idx}.product_id`]" />
              </div>
              <div class="col-span-2 grid gap-1">
                <Label>Qty</Label>
                <Input type="number" min="1" v-model.number="item.quantity" @input="onQtyChange(item)" />
              </div>
              <div class="col-span-2 grid gap-1">
                <Label>Unit Price</Label>
                <Input type="number" step="0.01" v-model.number="item.unit_price" @input="onQtyChange(item)" :disabled="item.is_gift" />
              </div>
              <div class="col-span-2 grid gap-1">
                <Label>Subtotal</Label>
                <Input type="number" :value="item.subtotal.toFixed(2)" readonly class="bg-gray-50 dark:bg-gray-700" />
              </div>
              <div class="col-span-1 flex items-end pb-0.5">
                <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700 text-lg font-bold">✕</button>
              </div>
            </div>

            <!-- Mobile: Stacked card -->
            <div class="md:hidden space-y-3">
              <div class="flex items-start justify-between gap-2">
                <div class="flex-1 grid gap-1">
                  <Label>Product</Label>
                  <select v-model="item.product_id" @change="onProductChange(item)" class="border-input flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                    <option :value="null">Select product...</option>
                    <option v-for="p in props.products" :key="p.id" :value="p.id">{{ resolveProductName(p) }}</option>
                  </select>
                  <InputError :message="(form.errors as any)[`items.${idx}.product_id`]" />
                </div>
                <button type="button" @click="removeItem(idx)" class="mt-6 text-red-500 hover:text-red-700 h-10 w-10 flex items-center justify-center rounded-lg border border-red-200 dark:border-red-800">✕</button>
              </div>
              <div class="grid grid-cols-3 gap-3">
                <div class="grid gap-1">
                  <Label>Qty</Label>
                  <Input type="number" min="1" v-model.number="item.quantity" @input="onQtyChange(item)" class="h-10" />
                </div>
                <div class="grid gap-1">
                  <Label>Price</Label>
                  <Input type="number" step="0.01" v-model.number="item.unit_price" @input="onQtyChange(item)" :disabled="item.is_gift" class="h-10" />
                </div>
                <div class="grid gap-1">
                  <Label>Subtotal</Label>
                  <Input type="number" :value="item.subtotal.toFixed(2)" readonly class="bg-gray-50 dark:bg-gray-700 h-10" />
                </div>
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
          <Button type="button" @click="$inertia.visit(index().url)" variant="outline">Cancel</Button>
          <Button
            type="submit"
            :disabled="
              form.processing
              || form.items.length === 0
              || (!form.user_id && !(isNewContact && form.new_contact?.name && form.new_contact?.phone))
            "
          >
            <span v-if="form.processing">Creating...</span>
            <span v-else>Create Order</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
