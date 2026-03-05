<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/products';
import { computed, ref } from 'vue';

const props = defineProps<{
  statuses: Record<string, string>;
  lowStockActions: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Products', href: index().url },
  { title: 'New Product', href: '#' },
];

const form = useForm({
  name: { en: '', uz: '', ru: '' } as Record<string, string>,
  sku: '',
  price: '',
  sale_price: '',
  cost: '',
  weight: '',
  quantity: 0,
  currency: 'UZS',
  manage_stock: false,
  low_stock_threshold: 0,
  low_stock_action: 'none',
  status: 'active',
  short_description: { en: '', uz: '', ru: '' } as Record<string, string>,
});

const isFormValid = computed(() =>
  form.name.en.length > 0 && form.sku.length > 0 && String(form.price).length > 0
);

const submitForm = () => {
  form.post(store().url);
};

const selectClass = cn(
  'mt-1 cursor-pointer border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none dark:bg-input/30 md:text-sm',
  'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);
</script>

<template>

  <Head title="New Product" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="p-4 pb-6 bg-white dark:bg-gray-900">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create New Product</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add a product to the catalogue</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">

        <!-- Basic Info -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Basic Info</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

            <!-- Name EN -->
            <div class="grid gap-2">
              <Label for="name_en">Name (EN) *</Label>
              <Input id="name_en" v-model="form.name.en" required placeholder="Product name in English" />
              <InputError :message="form.errors['name.en']" />
            </div>
            <!-- Name UZ -->
            <div class="grid gap-2">
              <Label for="name_uz">Name (UZ)</Label>
              <Input id="name_uz" v-model="form.name.uz" placeholder="Mahsulot nomi" />
            </div>
            <!-- Name RU -->
            <div class="grid gap-2">
              <Label for="name_ru">Name (RU)</Label>
              <Input id="name_ru" v-model="form.name.ru" placeholder="Название продукта" />
            </div>

            <!-- SKU -->
            <div class="grid gap-2">
              <Label for="sku">SKU *</Label>
              <Input id="sku" v-model="form.sku" required placeholder="e.g. WTR-19L-001" />
              <InputError :message="form.errors.sku" />
            </div>

            <!-- Status -->
            <div class="grid gap-2">
              <Label for="status">Status *</Label>
              <select id="status" v-model="form.status" :class="selectClass">
                <option v-for="(label, value) in props.statuses" :key="value" :value="value">{{ label }}</option>
              </select>
              <InputError :message="form.errors.status" />
            </div>

            <!-- Currency -->
            <div class="grid gap-2">
              <Label for="currency">Currency *</Label>
              <Input id="currency" v-model="form.currency" placeholder="UZS" maxlength="10" />
              <InputError :message="form.errors.currency" />
            </div>

            <!-- Short description EN -->
            <div class="grid gap-2 sm:col-span-2">
              <Label for="short_desc_en">Short Description (EN)</Label>
              <textarea id="short_desc_en" v-model="form.short_description.en" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Brief product description..."></textarea>
            </div>
          </div>
        </div>

        <!-- Pricing -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Pricing</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            <div class="grid gap-2">
              <Label for="price">Price *</Label>
              <Input id="price" type="number" step="0.01" min="0" v-model="form.price" required placeholder="0.00" />
              <InputError :message="form.errors.price" />
            </div>
            <div class="grid gap-2">
              <Label for="sale_price">Sale Price</Label>
              <Input id="sale_price" type="number" step="0.01" min="0" v-model="form.sale_price" placeholder="0.00" />
              <InputError :message="form.errors.sale_price" />
            </div>
            <div class="grid gap-2">
              <Label for="cost">Cost</Label>
              <Input id="cost" type="number" step="0.01" min="0" v-model="form.cost" placeholder="0.00" />
              <InputError :message="form.errors.cost" />
            </div>
          </div>
        </div>

        <!-- Inventory -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Inventory</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="quantity">Stock Quantity *</Label>
              <Input id="quantity" type="number" min="0" v-model.number="form.quantity" required />
              <InputError :message="form.errors.quantity" />
            </div>
            <div class="grid gap-2">
              <Label for="weight">Weight (kg)</Label>
              <Input id="weight" type="number" step="0.01" min="0" v-model="form.weight" placeholder="0.00" />
            </div>

            <!-- Manage stock toggle -->
            <div class="flex items-center gap-3">
              <input id="manage_stock" type="checkbox" v-model="form.manage_stock" class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600" />
              <Label for="manage_stock" class="mb-0 cursor-pointer">Track stock levels</Label>
            </div>

            <template v-if="form.manage_stock">
              <div class="grid gap-2">
                <Label for="low_stock_threshold">Low Stock Threshold</Label>
                <Input id="low_stock_threshold" type="number" min="0" v-model.number="form.low_stock_threshold" />
              </div>
              <div class="grid gap-2">
                <Label for="low_stock_action">Low Stock Action</Label>
                <select id="low_stock_action" v-model="form.low_stock_action" :class="selectClass">
                  <option v-for="(label, value) in props.lowStockActions" :key="value" :value="value">{{ label }}</option>
                </select>
              </div>
            </template>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 pt-2 pb-6">
          <Button type="button" @click="$inertia.visit(index().url)" variant="outline">Cancel</Button>
          <Button type="submit" :disabled="form.processing || !isFormValid">
            <span v-if="form.processing">Creating...</span>
            <span v-else>Create Product</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
