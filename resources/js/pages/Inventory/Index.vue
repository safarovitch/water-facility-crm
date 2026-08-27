<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NumberField from '@/components/NumberField.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { PlusCircle, Search, Edit, Trash2, Camera, MapPin, Database, Wrench } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

interface InventoryItem {
  id: number;
  name: string;
  category: string;
  quantity: string;
  unit: string;
  status: string;
  location: string | null;
  serial_number: string | null;
  purchase_date: string | null;
  purchase_price: string | null;
  notes: string | null;
  photo_url: string | null;
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
  items: Paginated<InventoryItem>;
  summary: {
    total_items: number;
    active_items: number;
    total_value: number;
  };
  filters: {
    category?: string;
    status?: string;
    search?: string;
  };
  categories: string[];
  units: string[];
  statuses: string[];
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Inventory'), href: '/admin/inventory-items' },
]);

const formatCurrency = (value: number | string) => {
  const currency = (usePage().props.currency as string) || 'USD';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(value));
};

const isDialogOpen = ref(false);
const editingRecord = ref<InventoryItem | null>(null);

const form = useForm({
  name: '',
  category: '',
  quantity: '1',
  unit: 'pcs',
  status: 'active',
  location: '',
  serial_number: '',
  purchase_date: '',
  purchase_price: '',
  notes: '',
  photo: null as File | null,
  add_to_expense: false,
});

const filterForm = useForm({
  search: props.filters.search || '',
  category: props.filters.category || '',
  status: props.filters.status || '',
});

const applyFilters = () => {
  router.get('/admin/inventory-items', filterForm.data(), { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
  filterForm.reset();
  applyFilters();
};

const openCreateModal = () => {
  editingRecord.value = null;
  form.reset();
  form.add_to_expense = false;
  form.clearErrors();
  isDialogOpen.value = true;
};

const openEditModal = (item: InventoryItem) => {
  editingRecord.value = item;
  form.name = item.name;
  form.category = item.category;
  form.quantity = item.quantity;
  form.unit = item.unit;
  form.status = item.status;
  form.location = item.location || '';
  form.serial_number = item.serial_number || '';
  form.purchase_date = item.purchase_date || '';
  form.purchase_price = item.purchase_price || '';
  form.notes = item.notes || '';
  form.photo = null;
  form.add_to_expense = false;
  form.clearErrors();
  isDialogOpen.value = true;
};

const submit = () => {
  if (editingRecord.value) {
    form.transform((data: any) => {
      const payload = { ...data };
      if (!payload.photo) {
        delete payload.photo;
      }
      return payload;
    }).post(`/admin/inventory-items/${editingRecord.value.id}`, {
      onSuccess: () => isDialogOpen.value = false,
    });
  } else {
    form.post('/admin/inventory-items', {
      onSuccess: () => isDialogOpen.value = false,
    });
  }
};

const deleteRecord = (id: number) => {
  if (confirm(t('Are you sure you want to delete this inventory item?'))) {
    router.delete(`/admin/inventory-items/${id}`);
  }
};

const statusBadgeClass = (status: string) => {
  const classes: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 border-green-200 dark:border-green-800',
    in_repair: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 border-yellow-200 dark:border-yellow-800',
    retired: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 border-gray-200 dark:border-gray-800',
    lost: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 border-red-200 dark:border-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

const statusLabel = computed((): Record<string, string> => ({
  active: t('Active'),
  in_repair: t('In repair'),
  retired: t('Retired'),
  lost: t('Lost'),
}));
</script>

<template>
  <Head :title="t('Inventory')" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-4 md:space-y-6 container mx-auto px-4 md:px-0">
      <!-- Headers & Summary Cards -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">{{ t('Inventory') }}</h1>
          <p class="text-sm text-muted-foreground mt-1">{{ t('Manage tools, equipment, and assets.') }}</p>
        </div>
        <Button @click="openCreateModal" class="w-full md:w-auto gap-2 shadow-sm font-semibold rounded-xl h-11 md:h-10">
          <PlusCircle class="h-4 w-4" /> {{ t('Add Item') }}
        </Button>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <Card class="border-l-4 border-l-blue-500 shadow-md">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ t('Total Items') }}</CardTitle>
            <Database class="h-4 w-4 text-blue-600 dark:text-blue-400" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ summary.total_items }}</div>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-green-500 shadow-md">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ t('Active Items') }}</CardTitle>
            <PlusCircle class="h-4 w-4 text-green-600 dark:text-green-400" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ summary.active_items }}</div>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-purple-500 shadow-md">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ t('Total Asset Value') }}</CardTitle>
            <Wrench class="h-4 w-4 text-purple-600 dark:text-purple-400" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ formatCurrency(summary.total_value) }}</div>
          </CardContent>
        </Card>
      </div>

      <!-- Filters & Table -->
      <Card class="shadow-sm">
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 grid grid-cols-1 md:flex md:flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-full md:w-64">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Search') }}</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="filterForm.search" :placeholder="t('Search name, serial...')" class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="applyFilters" />
                </div>
                <div class="grid grid-cols-2 gap-3 w-full md:flex md:gap-3 md:w-auto">
                    <div class="space-y-1 md:w-48">
                        <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Category') }}</Label>
                        <Input v-model="filterForm.category" list="category-suggestions" :placeholder="t('All')" class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm" />
                    </div>
                    <div class="space-y-1 md:w-40">
                        <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Status') }}</Label>
                        <select v-model="filterForm.status" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option value="">{{ t('All Statuses') }}</option>
                            <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ statusLabel[status] ?? status.replace('_', ' ') }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <Button @click="applyFilters" variant="secondary" size="sm" class="h-10 md:h-9 flex-1 md:flex-none">{{ t('Apply') }}</Button>
                    <Button @click="resetFilters" variant="ghost" size="sm" class="h-10 md:h-9 flex-1 md:flex-none text-muted-foreground">{{ t('Reset') }}</Button>
                </div>
            </div>

            <!-- Table (Desktop) -->
            <div class="hidden md:block relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold">{{ t('Item') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Category') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Status') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Quantity') }}</th>
                            <th class="px-6 py-4 font-semibold text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                  <div class="h-10 w-10 shrink-0 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 dark:border-gray-700">
                                    <img v-if="item.photo_url" :src="item.photo_url" class="h-full w-full object-cover" />
                                    <Wrench v-else class="h-5 w-5 text-gray-400" />
                                  </div>
                                  <div>
                                    <div class="font-bold text-gray-900 dark:text-white">{{ item.name }}</div>
                                    <div class="text-xs flex gap-2 items-center text-gray-500 mt-1">
                                      <span v-if="item.serial_number" class="font-mono text-[10px] bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700">SN: {{ item.serial_number }}</span>
                                      <span v-if="item.location" class="flex items-center gap-1"><MapPin class="h-3 w-3" /> {{ item.location }}</span>
                                    </div>
                                  </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <Badge variant="outline" class="font-medium bg-gray-50 dark:bg-gray-800/50">
                                    {{ item.category }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4">
                                <Badge :class="statusBadgeClass(item.status)" class="capitalize">
                                    {{ statusLabel[item.status] ?? item.status.replace('_', ' ') }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold">{{ Number(item.quantity) }}</span> <span class="text-xs text-muted-foreground">{{ item.unit }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button @click="openEditModal(item)" variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button @click="deleteRecord(item.id)" variant="ghost" size="icon" class="h-8 w-8 text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/40">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="md:hidden divide-y divide-border/60">
                <div v-for="item in items.data" :key="item.id" class="p-4 bg-white dark:bg-background active:bg-muted/30 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 shrink-0 bg-gray-100 dark:bg-gray-800 rounded-xl overflow-hidden flex items-center justify-center border border-gray-200 dark:border-gray-700 shadow-sm">
                            <img v-if="item.photo_url" :src="item.photo_url" class="h-full w-full object-cover" />
                            <Wrench v-else class="h-7 w-7 text-gray-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-gray-900 dark:text-white text-base leading-tight">{{ item.name }}</div>
                                <Badge :class="statusBadgeClass(item.status)" class="capitalize whitespace-nowrap text-[10px] h-5 px-1.5 shrink-0">
                                    {{ statusLabel[item.status] ?? item.status.replace('_', ' ') }}
                                </Badge>
                            </div>
                            <div class="text-xs text-muted-foreground mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                <span class="bg-muted/50 px-1.5 py-0.5 rounded text-[10px] uppercase font-bold tracking-tight">{{ item.category }}</span>
                                <span v-if="item.serial_number" class="font-mono">SN: {{ item.serial_number }}</span>
                                <span v-if="item.location" class="flex items-center gap-1"><MapPin class="h-3 w-3" /> {{ item.location }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('In Stock') }}</span>
                            <span class="font-bold text-sm">{{ Number(item.quantity) }} <span class="text-xs font-normal text-muted-foreground">{{ item.unit }}</span></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button @click="openEditModal(item)" variant="secondary" size="sm" class="h-9 px-4 rounded-lg shadow-sm border border-border/50">
                                <Edit class="h-4 w-4 mr-1.5" /> {{ t('Edit') }}
                            </Button>
                            <Button @click="deleteRecord(item.id)" variant="ghost" size="icon" class="h-9 w-9 text-red-500 bg-red-50/50 dark:bg-red-900/10 rounded-lg">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="items.data.length === 0" class="px-6 py-12 text-center text-muted-foreground">
                <div class="flex flex-col items-center justify-center opacity-60">
                    <Database class="h-10 w-10 mb-3 text-gray-400" />
                    <p class="font-medium text-sm">{{ t('No inventory items found.') }}</p>
                    <p class="text-xs mt-1">{{ t('Adjust filters or add a new item.') }}</p>
                </div>
            </div>
            
            <!-- Pagination -->
            <Pagination :paginator="items" />
        </CardContent>
      </Card>

      <!-- Datalists -->
      <datalist id="category-suggestions">
        <option v-for="cat in categories" :key="cat" :value="cat" />
      </datalist>
      <datalist id="unit-suggestions">
        <option v-for="u in units" :key="u" :value="u" />
      </datalist>

      <!-- Create/Edit Dialog -->
      <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold">{{ editingRecord ? t('Edit Inventory Item') : t('Add Inventory Item') }}</DialogTitle>
            <DialogDescription>
              {{ t('Provide the details of the tool, equipment, or asset.') }}
            </DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submit" class="space-y-5 py-2">
            
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2 col-span-2 md:col-span-1">
                <Label for="name">{{ t('Name / Description') }} <span class="text-red-500">*</span></Label>
                <Input v-model="form.name" id="name" :placeholder="t('E.g. DeWalt Hammer Drill')" required />
              </div>
              
              <div class="space-y-2 col-span-2 md:col-span-1">
                <Label for="category">{{ t('Category') }} <span class="text-red-500">*</span></Label>
                <Input v-model="form.category" id="category" list="category-suggestions" :placeholder="t('Power Tools, Assets...')" required />
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-2">
                <Label for="quantity">{{ t('Quantity') }} <span class="text-red-500">*</span></Label>
                <NumberField v-model="form.quantity" :step="0.01" :min="0" :controls="false" id="quantity" required />
              </div>

              <div class="space-y-2">
                <Label for="unit">{{ t('Unit') }} <span class="text-red-500">*</span></Label>
                <Input v-model="form.unit" id="unit" list="unit-suggestions" :placeholder="t('pcs, kg...')" required />
              </div>

              <div class="space-y-2">
                <Label for="status">{{ t('Status') }} <span class="text-red-500">*</span></Label>
                <select v-model="form.status" id="status" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                    <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ statusLabel[status] ?? status.replace('_', ' ') }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 border-t pt-4">
              <div class="space-y-2">
                <Label for="serial_number">{{ t('Serial Number') }}</Label>
                <Input v-model="form.serial_number" id="serial_number" :placeholder="t('Optional identifier')" />
              </div>

              <div class="space-y-2">
                <Label for="location">{{ t('Location') }}</Label>
                <Input v-model="form.location" id="location" :placeholder="t('Where is it stored?')" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="purchase_date">{{ t('Purchase Date') }}</Label>
                <Input v-model="form.purchase_date" type="date" id="purchase_date" />
              </div>

              <div class="space-y-2">
                 <Label for="purchase_price">{{ t('Purchase Value') }} ({{ ($page.props.currency as string) || 'USD' }})</Label>
                 <NumberField v-model="form.purchase_price" :step="0.01" :min="0" :controls="false" id="purchase_price" placeholder="0.00" />
              </div>
            </div>

            <div class="flex items-center space-x-2 bg-blue-50/50 dark:bg-blue-900/10 p-3 rounded-lg border border-blue-100 dark:border-blue-900/30">
              <Checkbox id="add_to_expense" v-model:checked="form.add_to_expense" />
              <div class="grid gap-1.5 leading-none">
                <Label
                  for="add_to_expense"
                  class="text-sm font-semibold leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                >
                  {{ t('Add to expenses') }}
                </Label>
                <p class="text-xs text-muted-foreground italic">
                  {{ t('Create a record in the financial system for this purchase.') }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="notes">{{ t('Additional Notes') }}</Label>
              <Input v-model="form.notes" id="notes" :placeholder="t('Condition details, warranty, etc.')" />
            </div>

            <div class="space-y-2 border-t pt-4">
              <Label for="photo" class="flex items-center gap-2">{{ t('Item Photo') }}</Label>
              <div class="flex items-center gap-3">
                <div class="relative w-full">
                  <Camera class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input @input="form.photo = $event.target.files[0]" id="photo" type="file" accept="image/*" class="pl-10 file:ml-2 w-full text-muted-foreground cursor-pointer hover:bg-muted/50 transition-colors" />
                </div>
              </div>
              <p v-if="editingRecord?.photo_url && !form.photo" class="text-xs text-muted-foreground mt-1">
                {{ t('Current photo active. Uploading a new one will replace it.') }}
              </p>
            </div>

            <!-- Errors -->
             <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive font-medium border border-destructive/20">
              <ul class="list-disc pl-4 space-y-1">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
              </ul>
            </div>

            <div class="flex justify-end gap-3 pt-4 pb-2 border-t mt-6">
              <Button type="button" variant="outline" @click="isDialogOpen = false" class="shadow-sm">{{ t('Cancel') }}</Button>
              <Button type="submit" :disabled="form.processing" class="shadow-sm min-w-[120px]">
                {{ form.processing ? t('Saving...') : (editingRecord ? t('Update Item') : t('Add Item')) }}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
