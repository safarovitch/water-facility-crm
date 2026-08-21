<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/products';
import { computed, ref } from 'vue';
import { PlusCircle, Trash2, Box } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

interface RawMaterialCombo {
  id: number;
  name: string;
  unit: string;
  sku: string | null;
  pivot?: { quantity: string };
}

interface Product {
  id: number;
  name: Record<string, string>;
  sku: string;
  price: string;
  sale_price: string;
  cost: string;
  weight: string;
  quantity: number;
  manage_stock: boolean;
  is_produced: boolean;
  low_stock_threshold: number;
  low_stock_action: string;
  status: string;
  short_description: Record<string, string> | null;
  image_url: string | null;
  raw_materials?: RawMaterialCombo[];
}

const props = defineProps<{
  product: Product;
  statuses: Record<string, string>;
  lowStockActions: Record<string, string>;
  availableRawMaterials: RawMaterialCombo[];
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Products'), href: index().url },
  { title: props.product.name?.ru ?? props.product.sku, href: '#' },
]);

const availableLocales = usePage().props.available_locales as string[];

const form = useForm({
  name: Object.fromEntries(availableLocales.map(l => [l, props.product.name?.[l] ?? ''])),
  sku: props.product.sku,
  price: props.product.price,
  sale_price: props.product.sale_price,
  cost: props.product.cost,
  weight: props.product.weight,
  quantity: props.product.quantity,
  manage_stock: props.product.manage_stock,
  is_produced: props.product.is_produced,
  low_stock_threshold: props.product.low_stock_threshold,
  low_stock_action: props.product.low_stock_action,
  status: props.product.status,
  short_description: Object.fromEntries(availableLocales.map(l => [l, props.product.short_description?.[l] ?? ''])),
  image: null as File | null,
  raw_materials: props.product.raw_materials?.map(rm => ({ id: rm.id, quantity: Number(rm.pivot?.quantity ?? 1) })) ?? [],
});

const languageLabels: Record<string, string> = {
  ru: 'Русский',
  tg: 'Тоҷикӣ',
  en: 'English',
};

const availableLanguages = availableLocales.map(code => ({
  code,
  label: languageLabels[code] || code.toUpperCase(),
}));

type LanguageCode = typeof availableLanguages[number]['code'];

const currentLang = ref<LanguageCode>('ru');

const submitForm = () => {
  form.post(update(props.product.id).url);
};

const addRawMaterial = () => {
  form.raw_materials.push({ id: props.availableRawMaterials[0]?.id || 0, quantity: 1 });
};

const removeRawMaterial = (index: number) => {
  form.raw_materials.splice(index, 1);
};

const handleFileChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (file) {
    form.image = file;
  }
};

const getImageUrl = computed(() => {
  if (form.image) return URL.createObjectURL(form.image);
  return props.product.image_url;
});

const fileInput = ref<HTMLInputElement | null>(null);

const triggerUpload = () => {
  fileInput.value?.click();
};

const selectClass = cn(
  'mt-1 cursor-pointer border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none dark:bg-input/30 md:text-sm',
  'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);
</script>

<template>

  <Head :title="t('Edit {name}', { name: product.name?.[availableLocales[0]] ?? product.sku })" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="p-4 pb-6 bg-white dark:bg-gray-900">
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">SKU: {{ product.sku }}</p>
      </div>

      <!-- Language Switcher -->
      <div class="flex justify-end mb-6 px-4 sm:px-0">
        <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
          <button v-for="lang in availableLanguages" :key="lang.code" type="button" @click="currentLang = lang.code" :class="[
            'px-4 py-1.5 text-sm font-medium rounded-md transition-all',
            currentLang === lang.code
              ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow-sm'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
          ]">
            {{ lang.label }}
          </button>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">

        <!-- Image Upload -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Product Image') }}</h2>
          <div class="flex items-center gap-6">
            <div class="w-32 h-32 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden border-2 border-dashed border-gray-300 dark:border-gray-600 relative group">
              <img v-if="getImageUrl" :src="getImageUrl" class="w-full h-full object-cover" />
              <div v-else class="text-gray-400 text-xs text-center p-2">{{ t('No image') }}</div>

              <label for="image_upload" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity cursor-pointer">
                <span class="text-white text-xs font-medium">{{ t('Change') }}</span>
              </label>
              <input id="image_upload" type="file" ref="fileInput" @change="handleFileChange" class="hidden" accept="image/*" />
            </div>
            <div class="space-y-1">
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Update product image') }}</p>
              <p class="text-xs text-gray-500">{{ t('PNG, JPG, GIF up to 2MB') }}</p>
              <Button type="button" variant="outline" size="sm" @click="triggerUpload">
                {{ t('Select File') }}
              </Button>
            </div>
          </div>
          <InputError :message="form.errors.image" class="mt-2" />
        </div>

        <!-- Basic Info -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Basic Info') }}</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <!-- Multilingual Name -->
            <div class="grid gap-2 sm:col-span-1">
              <Label :for="'name_' + currentLang">{{ t('Name') }} ({{ currentLang.toUpperCase() }}) *</Label>
              <Input :id="'name_' + currentLang" v-model="form.name[currentLang]" required />
              <InputError :message="form.errors[`name.${currentLang}` as keyof typeof form.errors]" />
            </div>
            <div class="grid gap-2">
              <Label for="sku">{{ t('SKU') }} *</Label>
              <Input id="sku" v-model="form.sku" required />
              <InputError :message="form.errors.sku" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <Label for="status">{{ t('Status') }} *</Label>
              <select id="status" v-model="form.status" :class="selectClass">
                <option v-for="(label, value) in props.statuses" :key="value" :value="value">{{ t(String(label)) }}</option>
              </select>
            </div>
            <!-- Multilingual Short description -->
            <div class="grid gap-2 sm:col-span-2">
              <Label :for="'short_desc_' + currentLang">{{ t('Short Description') }} ({{ currentLang.toUpperCase() }})</Label>
              <textarea :id="'short_desc_' + currentLang" v-model="form.short_description[currentLang]" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
              <InputError :message="form.errors[`short_description.${currentLang}` as keyof typeof form.errors]" />
            </div>
          </div>
        </div>

        <!-- Pricing -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Pricing') }}</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            <div class="grid gap-2">
              <Label for="price">{{ t('Price') }} *</Label>
              <Input id="price" type="number" step="0.01" min="0" v-model="form.price" required />
              <InputError :message="form.errors.price" />
            </div>
            <div class="grid gap-2">
              <Label for="sale_price">{{ t('Sale Price') }}</Label>
              <Input id="sale_price" type="number" step="0.01" min="0" v-model="form.sale_price" />
            </div>
            <div class="grid gap-2">
              <Label for="cost">{{ t('Cost') }}</Label>
              <Input id="cost" type="number" step="0.01" min="0" v-model="form.cost" />
            </div>
          </div>
        </div>

        <!-- Inventory -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Inventory') }}</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="quantity">{{ t('Stock Quantity') }} *</Label>
              <Input id="quantity" type="number" min="0" v-model.number="form.quantity" required />
              <InputError :message="form.errors.quantity" />
            </div>
            <div class="grid gap-2">
              <Label for="weight">{{ t('Weight (kg)') }}</Label>
              <Input id="weight" type="number" step="0.01" min="0" v-model="form.weight" />
            </div>
            <!-- Production plan opt-in -->
            <div class="flex items-center gap-3">
              <input id="is_produced" type="checkbox" v-model="form.is_produced" class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600" />
              <Label for="is_produced" class="mb-0 cursor-pointer">{{ t('We produce this ourselves') }}</Label>
            </div>
            <p class="-mt-2 text-xs text-muted-foreground">
              {{ t('Only these products appear in the daily production plan. Leave off for resold or side products.') }}
            </p>

            <div class="flex items-center gap-3">
              <input id="manage_stock" type="checkbox" v-model="form.manage_stock" class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600" />
              <Label for="manage_stock" class="mb-0 cursor-pointer">{{ t('Track stock levels') }}</Label>
            </div>
            <template v-if="form.manage_stock">
              <div class="grid gap-2">
                <Label for="low_stock_threshold">{{ t('Low Stock Threshold') }}</Label>
                <Input id="low_stock_threshold" type="number" min="0" v-model.number="form.low_stock_threshold" />
              </div>
              <div class="grid gap-2">
                <Label for="low_stock_action">{{ t('Low Stock Action') }}</Label>
                <select id="low_stock_action" v-model="form.low_stock_action" :class="selectClass">
                  <option v-for="(label, value) in props.lowStockActions" :key="value" :value="value">{{ t(String(label)) }}</option>
                </select>
              </div>
            </template>
          </div>
        </div>

        <!-- Bill of Materials (BOM) -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6 mt-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Bill of Materials (BOM)') }}</h2>
              <p class="text-sm text-gray-500">{{ t('Define the raw materials consumed when this product is produced.') }}</p>
            </div>
            <Button type="button" @click="addRawMaterial" variant="outline" size="sm" class="gap-2">
              <PlusCircle class="w-4 h-4" /> {{ t('Add Material') }}
            </Button>
          </div>

          <div v-if="form.raw_materials.length === 0" class="text-center py-6 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
            <Box class="w-8 h-8 mx-auto text-gray-400 mb-2" />
            <p class="text-sm text-gray-500">{{ t('No raw materials attached.') }}</p>
            <Button type="button" variant="link" @click="addRawMaterial" class="p-0 h-auto text-blue-600">{{ t('Add first material') }}</Button>
          </div>

          <div v-else class="space-y-3">
            <div v-for="(rm, index) in form.raw_materials" :key="index" class="flex items-end gap-3 bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
              <div class="flex-1 space-y-1">
                <Label>{{ t('Raw Material') }}</Label>
                <select v-model="rm.id" class="flex h-9 w-full rounded-md border border-input bg-white dark:bg-gray-800 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                  <option v-for="material in props.availableRawMaterials" :key="material.id" :value="material.id">
                    {{ material.name }} ({{ t('Measured in') }} {{ material.unit }})
                  </option>
                </select>
              </div>
              <div class="w-40 space-y-1">
                <Label>{{ t('Quantity Consumed') }}</Label>
                <Input v-model="rm.quantity" type="number" step="0.0001" min="0" required class="bg-white dark:bg-gray-800" />
              </div>
              <Button type="button" @click="removeRawMaterial(index)" variant="ghost" size="icon" class="h-9 w-9 text-red-500 hover:text-red-700 dark:hover:bg-red-900/30">
                <Trash2 class="w-4 h-4" />
              </Button>
            </div>
            <InputError :message="form.errors.raw_materials" />
          </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2 pb-6">
          <Button type="button" @click="$inertia.visit(index().url)" variant="outline">{{ t('Cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">
            <span v-if="form.processing">{{ t('Saving...') }}</span>
            <span v-else>{{ t('Save Changes') }}</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
