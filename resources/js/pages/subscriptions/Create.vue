<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import { computed, ref } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useI18n } from '@/composables/useI18n';

const { t: tl } = useLocale();
const { t } = useI18n();

interface Client {
  id: number;
  name: string;
  email: string;
  addresses: { id: number; label: string; address_line: string }[];
}

interface Product {
  id: number;
  name: Record<string, string> | string;
  price: string;
  sale_price: string;
}

const props = defineProps<{
  clients: Client[];
  products: Product[];
  frequencies: { value: string; label: string }[];
  timeSlots: { value: string; label: string }[];
}>();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Subscriptions'), href: '/admin/subscriptions' },
  { title: t('New Subscription'), href: '#' },
]);

interface LineItem {
  product_id: number | null;
  quantity: number;
}

const form = useForm({
  user_id: null as number | null,
  frequency: 'weekly',
  interval_days: 7,
  day_of_week: null as number | null,
  day_of_month: null as number | null,
  time_slot: '' as string,
  delivery_address: '',
  notes: '',
  items: [] as LineItem[],
});

const addItem = () => {
  form.items.push({ product_id: null, quantity: 1 });
};

const removeItem = (index: number) => {
  form.items.splice(index, 1);
};

const resolveProductName = (product: Product): string => {
  if (typeof product.name === 'string') return product.name;
  return product.name?.['en'] ?? product.name?.['uz'] ?? Object.values(product.name)[0] ?? '—';
};

const selectedClient = computed(() => props.clients.find(c => c.id === form.user_id));
const clientAddresses = computed(() => selectedClient.value?.addresses || []);

const showCustomInterval = computed(() => form.frequency === 'custom');
const showDayOfWeek = computed(() => form.frequency === 'weekly' || form.frequency === 'biweekly');
const showDayOfMonth = computed(() => form.frequency === 'monthly');

const daysOfWeek = computed(() => [
  { value: 0, label: t('Sunday') },
  { value: 1, label: t('Monday') },
  { value: 2, label: t('Tuesday') },
  { value: 3, label: t('Wednesday') },
  { value: 4, label: t('Thursday') },
  { value: 5, label: t('Friday') },
  { value: 6, label: t('Saturday') },
]);

const submitForm = () => {
  form.post('/admin/subscriptions/store');
};
</script>

<template>
  <Head :title="t('New Subscription')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="pb-6 bg-white dark:bg-gray-900 px-4 py-5 sm:px-6 rounded-t-lg">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Create Recurring Subscription') }}</h1>
        <p class="text-sm text-muted-foreground mt-1">{{ t('Set up automatic recurring water deliveries for a client.') }}</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Client & Address -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Client & Delivery') }}</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
              <Label for="user_id">{{ t('Client') }} *</Label>
              <select id="user_id" v-model="form.user_id" required class="border-input flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                <option :value="null">{{ t('Select client...') }}</option>
                <option v-for="c in props.clients" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
              </select>
              <InputError :message="form.errors.user_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
              <Label>{{ t('Delivery Address') }} *</Label>
              <div v-if="clientAddresses.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                <div v-for="addr in clientAddresses" :key="addr.id"
                  @click="form.delivery_address = addr.address_line"
                  :class="[
                    'p-3 rounded-lg border cursor-pointer transition-colors text-sm',
                    form.delivery_address === addr.address_line
                      ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20'
                      : 'border-gray-200 dark:border-gray-700 hover:border-blue-300'
                  ]">
                  <div class="font-medium text-gray-900 dark:text-white">{{ addr.label }}</div>
                  <div class="text-xs text-gray-500 mt-1 line-clamp-2">{{ addr.address_line }}</div>
                </div>
              </div>
              <textarea v-model="form.delivery_address" rows="2" :placeholder="t('Enter delivery address...')" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
              <InputError :message="form.errors.delivery_address" />
            </div>
          </div>
        </div>

        <!-- Schedule -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Schedule') }}</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label>{{ t('Frequency') }} *</Label>
              <select v-model="form.frequency" class="border-input flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                <option v-for="f in props.frequencies" :key="f.value" :value="f.value">{{ t(f.label) }}</option>
              </select>
              <InputError :message="form.errors.frequency" />
            </div>

            <div v-if="showDayOfWeek" class="grid gap-2">
              <Label>{{ t('Preferred Day') }}</Label>
              <select v-model="form.day_of_week" class="border-input flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                <option :value="null">{{ t('Any day') }}</option>
                <option v-for="d in daysOfWeek" :key="d.value" :value="d.value">{{ d.label }}</option>
              </select>
            </div>

            <div v-if="showDayOfMonth" class="grid gap-2">
              <Label>{{ t('Day of Month') }}</Label>
              <Input type="number" min="1" max="31" v-model.number="form.day_of_month" :placeholder="t('e.g. 15')" class="h-10" />
            </div>

            <div v-if="showCustomInterval" class="grid gap-2">
              <Label>{{ t('Every X Days') }} *</Label>
              <Input type="number" min="1" max="365" v-model.number="form.interval_days" class="h-10" />
              <InputError :message="form.errors.interval_days" />
            </div>

            <div class="grid gap-2">
              <Label>{{ t('Time Slot') }}</Label>
              <select v-model="form.time_slot" class="border-input flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                <option value="">{{ t('No preference') }}</option>
                <option v-for="ts in props.timeSlots" :key="ts.value" :value="ts.value">{{ t(ts.label) }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Items -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Products') }}</h2>
            <Button type="button" variant="outline" @click="addItem">{{ t('+ Add Product') }}</Button>
          </div>
          <InputError :message="form.errors.items" />

          <div v-if="form.items.length === 0" class="text-center py-8 text-gray-400">
            {{ t('No products yet. Click "+ Add Product" to start.') }}
          </div>

          <div v-for="(item, idx) in form.items" :key="idx" class="mb-3 border-b border-gray-100 dark:border-gray-700 pb-3">
            <div class="flex items-end gap-3">
              <div class="flex-1 grid gap-1">
                <Label>{{ t('Product') }}</Label>
                <select v-model="item.product_id" class="border-input flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white">
                  <option :value="null">{{ t('Select product...') }}</option>
                  <option v-for="p in props.products" :key="p.id" :value="p.id">{{ resolveProductName(p) }}</option>
                </select>
              </div>
              <div class="w-24 grid gap-1">
                <Label>{{ t('Qty') }}</Label>
                <Input type="number" min="1" v-model.number="item.quantity" class="h-10" />
              </div>
              <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700 h-10 w-10 flex items-center justify-center rounded-lg border border-red-200 dark:border-red-800 shrink-0">✕</button>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <div class="grid gap-2">
            <Label for="notes">{{ t('Notes') }}</Label>
            <textarea id="notes" v-model="form.notes" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" :placeholder="t('Optional delivery instructions...')"></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2 pb-6">
          <Button type="button" @click="$inertia.visit('/admin/subscriptions')" variant="outline">{{ t('Cancel') }}</Button>
          <Button type="submit" :disabled="form.processing || form.items.length === 0 || !form.user_id || !form.delivery_address">
            <span v-if="form.processing">{{ t('Creating...') }}</span>
            <span v-else>{{ t('Create Subscription') }}</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
