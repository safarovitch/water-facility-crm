<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import { index, store } from '@/routes/orders';
import { computed, ref } from 'vue';

interface UserProfile { company_name: string | null; type: string; }
interface Client { id: number; name: string; email: string; user_profile: UserProfile | null; }
interface Product { id: number; name: Record<string, string> | string; price: string; sale_price: string; quantity: number; }

const props = defineProps<{ clients: Client[]; products: Product[]; }>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: index().url },
  { title: 'New Order', href: '#' },
];

interface LineItem { product_id: number | null; quantity: number; unit_price: number; subtotal: number; }

const form = useForm({
  user_id: null as number | null,
  delivery_date: '',
  delivery_address: '',
  notes: '',
  items: [] as LineItem[],
});

const addItem = () => {
  form.items.push({ product_id: null, quantity: 1, unit_price: 0, subtotal: 0 });
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

const onProductChange = (item: LineItem) => {
  if (!item.product_id) return;
  const p = productMap.value[item.product_id];
  if (!p) return;
  const price = parseFloat(p.sale_price) > 0 ? parseFloat(p.sale_price) : parseFloat(p.price);
  item.unit_price = price;
  item.subtotal = price * item.quantity;
};

const onQtyChange = (item: LineItem) => {
  item.subtotal = item.unit_price * item.quantity;
};

const total = computed(() => form.items.reduce((sum, i) => sum + i.subtotal, 0));

const submitForm = () => { form.post(store().url); };

const clientLabel = (c: Client) =>
  c.user_profile?.company_name ? `${c.name} (${c.user_profile.company_name})` : c.name;
</script>

<template>

  <Head title="New Order" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="p-4 pb-6 bg-white dark:bg-gray-900">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create New Order</h1>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Client & Delivery -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Client & Delivery</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
              <Label for="user_id">Client *</Label>
              <select id="user_id" v-model="form.user_id" required class="border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                <option :value="null">Select client...</option>
                <option v-for="c in props.clients" :key="c.id" :value="c.id">{{ clientLabel(c) }}</option>
              </select>
              <InputError :message="form.errors.user_id" />
            </div>
            <div class="grid gap-2">
              <Label for="delivery_date">Delivery Date</Label>
              <Input id="delivery_date" type="date" v-model="form.delivery_date" />
              <InputError :message="form.errors.delivery_date" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <Label for="delivery_address">Delivery Address</Label>
              <textarea id="delivery_address" v-model="form.delivery_address" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
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

          <div v-for="(item, idx) in form.items" :key="idx" class="grid grid-cols-12 gap-3 items-end mb-3 border-b border-gray-100 dark:border-gray-700 pb-3">
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
              <Input type="number" step="0.01" v-model.number="item.unit_price" @input="onQtyChange(item)" />
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

          <!-- Total footer -->
          <div v-if="form.items.length > 0" class="flex justify-end mt-3">
            <div class="text-base font-semibold text-gray-900 dark:text-white">
              Total: {{ total.toFixed(2) }}
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
          <Button type="submit" :disabled="form.processing || form.items.length === 0 || !form.user_id">
            <span v-if="form.processing">Creating...</span>
            <span v-else>Create Order</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
