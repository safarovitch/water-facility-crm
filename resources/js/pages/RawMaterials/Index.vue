<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NumberField from '@/components/NumberField.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PlusCircle, Search, Edit, Trash2, Box } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

interface RawMaterial {
  id: number;
  name: string;
  sku: string | null;
  unit: string;
  current_stock: string;
  cost_per_unit: string;
  deposit_price: string;
  status: string;
  is_reusable: boolean;
  created_at_human: string;
}

interface Paginated<T> {
  data: T[];
  links: any[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
}

const props = defineProps<{
  materials: Paginated<RawMaterial>;
  filters: {
    status?: string;
    search?: string;
  };
  units: string[];
  statuses: string[];
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Products'), href: '/admin/products/index' },
  { title: t('Raw Materials'), href: '/admin/raw-materials' },
]);

const formatCurrency = (value: number | string) => {
  const currency = (usePage().props.currency as string) || 'USD';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(value));
};

const isDialogOpen = ref(false);
const editingRecord = ref<RawMaterial | null>(null);

const form = useForm({
  name: '',
  sku: '',
  unit: 'pcs',
  current_stock: '0',
  cost_per_unit: '0',
  deposit_price: '0',
  status: 'active',
  is_reusable: false,
});

const filterForm = useForm({
  search: props.filters.search || '',
  status: props.filters.status || '',
});

const applyFilters = () => {
  router.get('/admin/raw-materials', filterForm.data(), { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
  filterForm.reset();
  applyFilters();
};

const openCreateModal = () => {
  editingRecord.value = null;
  form.reset();
  form.clearErrors();
  isDialogOpen.value = true;
};

const openEditModal = (item: RawMaterial) => {
  editingRecord.value = item;
  form.name = item.name;
  form.sku = item.sku || '';
  form.unit = item.unit;
  form.current_stock = item.current_stock;
  form.cost_per_unit = item.cost_per_unit;
  form.deposit_price = item.deposit_price ?? '0';
  form.status = item.status;
  form.is_reusable = !!item.is_reusable;
  form.clearErrors();
  isDialogOpen.value = true;
};

const submit = () => {
  if (editingRecord.value) {
    // Note: because the controller accepts update on post using '{rawMaterial}',
    // Wait, the router defined it as post '{rawMaterial}' which maps to update.
    form.post(`/admin/raw-materials/${editingRecord.value.id}`, {
      onSuccess: () => isDialogOpen.value = false,
    });
  } else {
    form.post('/admin/raw-materials', {
      onSuccess: () => isDialogOpen.value = false,
    });
  }
};

const deleteRecord = (id: number) => {
  if (confirm(t('Are you sure you want to delete this raw material? It may be linked to manufactured products.'))) {
    router.delete(`/admin/raw-materials/${id}`);
  }
};
</script>

<template>
  <Head :title="t('Raw Materials')" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 container mx-auto">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">{{ t('Raw Materials') }}</h1>
          <p class="text-sm text-muted-foreground mt-1">{{ t('Manage consumable inventory for building products.') }}</p>
        </div>
        <Button @click="openCreateModal" class="gap-2 shadow-sm font-semibold rounded-xl">
          <PlusCircle class="h-4 w-4" /> {{ t('Add Material') }}
        </Button>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 flex flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-64">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Search') }}</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="filterForm.search" :placeholder="t('Search by name or sku...')" class="h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="applyFilters" />
                </div>
                <div class="space-y-1 w-40">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Status') }}</Label>
                    <select v-model="filterForm.status" class="flex h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option value="">{{ t('All Statuses') }}</option>
                        <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ t(status) }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <Button @click="applyFilters" variant="secondary" size="sm" class="h-9">{{ t('Apply') }}</Button>
                    <Button @click="resetFilters" variant="ghost" size="sm" class="h-9 text-muted-foreground">{{ t('Reset') }}</Button>
                </div>
            </div>

            <!-- Table -->
            <div class="relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold">{{ t('Material') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Stock Level') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Unit Cost') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Status') }}</th>
                            <th class="px-6 py-4 font-semibold text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="item in materials.data" :key="item.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4">
                              <div class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                {{ item.name }}
                                <Badge v-if="item.is_reusable" variant="outline" class="bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800 text-[10px] px-1.5 py-0">{{ t('Reusable') }}</Badge>
                              </div>
                              <div class="text-xs text-gray-500 mt-0.5" v-if="item.sku">SKU: {{ item.sku }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-base" :class="Number(item.current_stock) <= 0 ? 'text-red-500' : 'text-green-600'">{{ Number(item.current_stock) }}</span> 
                                <span class="text-xs text-muted-foreground ml-1">{{ item.unit }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                  <span class="font-medium">{{ formatCurrency(item.cost_per_unit) }}</span>
                                  <span class="text-xs text-muted-foreground ml-1">/ {{ item.unit }}</span>
                                </div>
                                <div v-if="item.is_reusable" class="text-xs text-blue-700 dark:text-blue-300 mt-0.5">
                                  {{ t('Deposit') }}: <span class="font-medium">{{ formatCurrency(item.deposit_price) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <Badge :variant="item.status === 'active' ? 'default' : 'secondary'" class="capitalize">
                                    {{ t(item.status) }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button @click="openEditModal(item)" variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button @click="deleteRecord(item.id)" variant="ghost" size="icon" class="h-8 w-8 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/40">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="materials.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center opacity-60">
                                    <Box class="h-10 w-10 mb-3 text-gray-400" />
                                    <p class="font-medium text-sm">{{ t('No raw materials found.') }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <Pagination :paginator="materials" />
        </CardContent>
      </Card>

      <datalist id="unit-suggestions">
        <option v-for="u in units" :key="u" :value="u" />
      </datalist>

      <!-- Create/Edit Dialog -->
      <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ editingRecord ? t('Edit Raw Material') : t('Add Raw Material') }}</DialogTitle>
            <DialogDescription>
              {{ t('Define components used to build your products.') }}
            </DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submit" class="space-y-4 py-2">
            
            <div class="space-y-2">
              <Label for="name">{{ t('Material Name') }} <span class="text-red-500">*</span></Label>
              <Input v-model="form.name" id="name" :placeholder="t('E.g. 19L Plastic Bottle')" required />
            </div>

            <div class="space-y-2">
              <Label for="sku">{{ t('SKU Code') }}</Label>
              <Input v-model="form.sku" id="sku" :placeholder="t('Optional identifier')" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="current_stock">{{ t('Current Stock') }}</Label>
                <NumberField v-model="form.current_stock" :step="0.01" :controls="false" id="current_stock" required />
              </div>
              <div class="space-y-2">
                <Label for="unit">{{ t('Unit') }} <span class="text-red-500">*</span></Label>
                <Input v-model="form.unit" id="unit" list="unit-suggestions" :placeholder="t('pcs, L, kg...')" required />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                 <Label for="cost_per_unit">{{ t('Cost Per Unit') }}</Label>
                 <NumberField v-model="form.cost_per_unit" :step="0.01" :min="0" :controls="false" id="cost_per_unit" placeholder="0.00" />
              </div>
              <div class="space-y-2">
                <Label for="status">{{ t('Status') }}</Label>
                <select v-model="form.status" id="status" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                    <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ t(status) }}</option>
                </select>
              </div>
            </div>

            <div class="flex items-center gap-2 pt-2 pb-2 border-b border-gray-100 dark:border-gray-800">
              <input type="checkbox" id="is_reusable" v-model="form.is_reusable" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
              <div class="grid gap-0.5">
                  <Label for="is_reusable" class="cursor-pointer font-semibold">{{ t('Reusable Material') }}</Label>
                  <p class="text-xs text-muted-foreground">{{ t('Select if this material (like a 20L bottle) is returned by clients and can be restocked without repurchasing.') }}</p>
              </div>
            </div>

            <div v-if="form.is_reusable" class="space-y-2 rounded-lg border border-blue-100 bg-blue-50/40 dark:border-blue-900/40 dark:bg-blue-900/10 p-3">
              <Label for="deposit_price" class="font-semibold">{{ t('Deposit Price') }} <span class="text-xs text-muted-foreground font-normal">({{ t('charged when not returned') }})</span></Label>
              <NumberField v-model="form.deposit_price" :step="0.01" :min="0" :controls="false" id="deposit_price" placeholder="0.00" />
              <p class="text-xs text-muted-foreground">
                {{ t("What the client pays per unit when they don't return this container with their next order. This is the replacement price you quote — typically your cost plus a small fee — and is separate from") }} <span class="font-medium">{{ t('Cost Per Unit') }}</span>.
              </p>
            </div>

            <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive font-medium border border-destructive/20">
              <ul class="list-disc pl-4 space-y-1">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
              </ul>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t mt-4">
              <Button type="button" variant="outline" @click="isDialogOpen = false" class="shadow-sm">{{ t('Cancel') }}</Button>
              <Button type="submit" :disabled="form.processing" class="shadow-sm min-w-[120px]">
                {{ form.processing ? t('Saving...') : (editingRecord ? t('Update') : t('Add Material')) }}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
