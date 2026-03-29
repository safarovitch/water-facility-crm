<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PlusCircle, Search, Edit, Trash2, Camera, MapPin, Database, Wrench } from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Inventory', href: '/inventory-items' },
];

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
});

const filterForm = useForm({
  search: props.filters.search || '',
  category: props.filters.category || '',
  status: props.filters.status || '',
});

const applyFilters = () => {
  router.get('/inventory-items', filterForm.data(), { preserveState: true, preserveScroll: true });
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
    }).post(`/inventory-items/${editingRecord.value.id}`, {
      onSuccess: () => isDialogOpen.value = false,
    });
  } else {
    form.post('/inventory-items', {
      onSuccess: () => isDialogOpen.value = false,
    });
  }
};

const deleteRecord = (id: number) => {
  if (confirm('Are you sure you want to delete this inventory item?')) {
    router.delete(`/inventory-items/${id}`);
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
</script>

<template>
  <Head title="Inventory" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 container mx-auto">
      <!-- Headers & Summary Cards -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Inventory</h1>
          <p class="text-sm text-muted-foreground mt-1">Manage tools, equipment, and assets.</p>
        </div>
        <Button @click="openCreateModal" class="gap-2 shadow-sm font-semibold rounded-xl">
          <PlusCircle class="h-4 w-4" /> Add Item
        </Button>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <Card class="border-l-4 border-l-blue-500 shadow-md">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Total Items</CardTitle>
            <Database class="h-4 w-4 text-blue-600 dark:text-blue-400" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ summary.total_items }}</div>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-green-500 shadow-md">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Active Items</CardTitle>
            <PlusCircle class="h-4 w-4 text-green-600 dark:text-green-400" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ summary.active_items }}</div>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-purple-500 shadow-md">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Total Asset Value</CardTitle>
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
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 flex flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-64">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Search</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="filterForm.search" placeholder="Search name, serial..." class="h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="applyFilters" />
                </div>
                <div class="space-y-1 w-48">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Category</Label>
                    <Input v-model="filterForm.category" list="category-suggestions" placeholder="All Categories" class="h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm" />
                </div>
                <div class="space-y-1 w-40">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Status</Label>
                    <select v-model="filterForm.status" class="flex h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option value="">All Statuses</option>
                        <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ status.replace('_', ' ') }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <Button @click="applyFilters" variant="secondary" size="sm" class="h-9">Apply</Button>
                    <Button @click="resetFilters" variant="ghost" size="sm" class="h-9 text-muted-foreground">Reset</Button>
                </div>
            </div>

            <!-- Table -->
            <div class="relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Item</th>
                            <th class="px-6 py-4 font-semibold">Category</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold">Quantity</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
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
                                    {{ item.status.replace('_', ' ') }}
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
                        <tr v-if="items.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center opacity-60">
                                    <Database class="h-10 w-10 mb-3 text-gray-400" />
                                    <p class="font-medium text-sm">No inventory items found.</p>
                                    <p class="text-xs mt-1">Adjust filters or add a new item.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div v-if="items.last_page > 1" class="px-6 py-4 border-t flex flex-wrap gap-1 bg-gray-50/50 dark:bg-gray-800/30">
                <div v-for="(link, key) in items.links" :key="key">
                    <Button
                        v-if="link.url === null"
                        disabled
                        variant="outline"
                        size="sm"
                        class="opacity-50 h-8"
                        v-html="link.label"
                    />
                    <Button
                        v-else
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        class="h-8"
                        @click="router.get(link.url, filterForm.data(), { preserveScroll: true, preserveState: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
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
            <DialogTitle class="text-xl font-bold">{{ editingRecord ? 'Edit Inventory Item' : 'Add Inventory Item' }}</DialogTitle>
            <DialogDescription>
              Provide the details of the tool, equipment, or asset.
            </DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submit" class="space-y-5 py-2">
            
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2 col-span-2 md:col-span-1">
                <Label for="name">Name / Description <span class="text-red-500">*</span></Label>
                <Input v-model="form.name" id="name" placeholder="E.g. DeWalt Hammer Drill" required />
              </div>
              
              <div class="space-y-2 col-span-2 md:col-span-1">
                <Label for="category">Category <span class="text-red-500">*</span></Label>
                <Input v-model="form.category" id="category" list="category-suggestions" placeholder="Power Tools, Assets..." required />
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-2">
                <Label for="quantity">Quantity <span class="text-red-500">*</span></Label>
                <Input v-model="form.quantity" type="number" step="0.01" min="0" id="quantity" required />
              </div>

              <div class="space-y-2">
                <Label for="unit">Unit <span class="text-red-500">*</span></Label>
                <Input v-model="form.unit" id="unit" list="unit-suggestions" placeholder="pcs, kg..." required />
              </div>

              <div class="space-y-2">
                <Label for="status">Status <span class="text-red-500">*</span></Label>
                <select v-model="form.status" id="status" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                    <option v-for="status in statuses" :key="status" :value="status" class="capitalize">{{ status.replace('_', ' ') }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 border-t pt-4">
              <div class="space-y-2">
                <Label for="serial_number">Serial Number</Label>
                <Input v-model="form.serial_number" id="serial_number" placeholder="Optional identifier" />
              </div>

              <div class="space-y-2">
                <Label for="location">Location</Label>
                <Input v-model="form.location" id="location" placeholder="Where is it stored?" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="purchase_date">Purchase Date</Label>
                <Input v-model="form.purchase_date" type="date" id="purchase_date" />
              </div>

              <div class="space-y-2">
                 <Label for="purchase_price">Purchase Value ({{ ($page.props.currency as string) || 'USD' }})</Label>
                 <Input v-model="form.purchase_price" type="number" step="0.01" min="0" id="purchase_price" placeholder="0.00" />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="notes">Additional Notes</Label>
              <Input v-model="form.notes" id="notes" placeholder="Condition details, warranty, etc." />
            </div>

            <div class="space-y-2 border-t pt-4">
              <Label for="photo" class="flex items-center gap-2">Item Photo</Label>
              <div class="flex items-center gap-3">
                <div class="relative w-full">
                  <Camera class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input @input="form.photo = $event.target.files[0]" id="photo" type="file" accept="image/*" class="pl-10 file:ml-2 w-full text-muted-foreground cursor-pointer hover:bg-muted/50 transition-colors" />
                </div>
              </div>
              <p v-if="editingRecord?.photo_url && !form.photo" class="text-xs text-muted-foreground mt-1">
                Current photo active. Uploading a new one will replace it.
              </p>
            </div>

            <!-- Errors -->
             <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive font-medium border border-destructive/20">
              <ul class="list-disc pl-4 space-y-1">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
              </ul>
            </div>

            <div class="flex justify-end gap-3 pt-4 pb-2 border-t mt-6">
              <Button type="button" variant="outline" @click="isDialogOpen = false" class="shadow-sm">Cancel</Button>
              <Button type="submit" :disabled="form.processing" class="shadow-sm min-w-[120px]">
                {{ form.processing ? 'Saving...' : (editingRecord ? 'Update Item' : 'Add Item') }}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
