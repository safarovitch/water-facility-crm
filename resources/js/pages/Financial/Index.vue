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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { PlusCircle, TrendingUp, TrendingDown, Wallet, Calendar, Tag, Trash2, Edit } from 'lucide-vue-next';

interface FinancialRecord {
  id: number;
  amount: string;
  type: string;
  category: string;
  description: string | null;
  transaction_date: string;
  transaction_date_human: string;
  transaction_date_formatted: string;
  recorded_by_id: number;
  recorder?: { name: string };
  created_at_human: string;
  receipt_url: string | null;
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
  records: Paginated<FinancialRecord>;
  summary: {
    total_income: number;
    total_expense: number;
    balance: number;
  };
  filters: {
    type?: string;
    category?: string;
    from?: string;
    to?: string;
  };
  types: Record<string, string>;
  categories: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Accounting', href: '/financial-records' },
];

const isDialogOpen = ref(false);
const editingRecord = ref<FinancialRecord | null>(null);
const form = useForm({
  amount: '',
  type: 'expense',
  category: 'other',
  description: '',
  transaction_date: new Date().toLocaleString('sv-SE').slice(0, 16).replace(' ', 'T'),
  receipt: null as File | null,
});

const openCreateDialog = () => {
  editingRecord.value = null;
  form.reset();
  form.type = 'expense';
  form.category = 'other';
  form.transaction_date = new Date().toLocaleString('sv-SE').slice(0, 16).replace(' ', 'T');
  form.receipt = null;
  isDialogOpen.value = true;
};

const openEditDialog = (record: FinancialRecord) => {
  editingRecord.value = record;
  form.amount = record.amount;
  form.type = record.type;
  form.category = record.category;
  form.description = record.description || '';
  form.transaction_date = record.transaction_date.slice(0, 16).replace(' ', 'T');
  form.receipt = null;
  isDialogOpen.value = true;
};

const submit = () => {
  if (editingRecord.value) {
    form.post(`/financial-records/${editingRecord.value.id}`, {
      onSuccess: () => {
        isDialogOpen.value = false;
        editingRecord.value = null;
      },
    });
  } else {
    form.post('/financial-records', {
      onSuccess: () => {
        isDialogOpen.value = false;
      },
    });
  }
};

const deleteRecord = (id: number) => {
  const currency = (usePage().props.currency as string) || 'USD';
  if (confirm('Are you sure you want to delete this record?')) {
    router.delete(`/financial-records/${id}`);
  }
};

const filterForm = useForm({
    type: props.filters.type || '',
    category: props.filters.category || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
});

const applyFilters = () => {
    router.get('/financial-records', filterForm.data(), { preserveState: true });
};

const resetFilters = () => {
    filterForm.reset();
    applyFilters();
};

const categoryBadgeClass = (category: string) => {
  const classes: Record<string, string> = {
    wages: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    rent: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    partner_payoff: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    sales: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    maintenance: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    utilities: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    other: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
  };
  return classes[category] || classes.other;
};

const formatCurrency = (value: number) => {
  const currency = (usePage().props.currency as string) || 'USD';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
};
</script>

<template>
  <Head title="Accounting" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="px-4 py-6 md:px-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Accounting</h1>
          <p class="text-muted-foreground mt-1 text-lg">Manage your facility's income and expenses.</p>
        </div>
        <Button @click="openCreateDialog" size="lg" class="w-full md:w-auto shadow-lg hover:shadow-xl transition-all">
          <PlusCircle class="mr-2 h-5 w-5" />
          Add Record
        </Button>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <Card class="border-l-4 border-l-green-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Total Income</CardTitle>
            <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-full group-hover:scale-110 transition-transform">
              <TrendingUp class="h-4 w-4 text-green-600 dark:text-green-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(summary.total_income) }}</div>
            <p class="text-xs text-muted-foreground mt-1">Total revenue collected</p>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-red-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Total Expenses</CardTitle>
            <div class="p-2 bg-red-50 dark:bg-red-900/20 rounded-full group-hover:scale-110 transition-transform">
              <TrendingDown class="h-4 w-4 text-red-600 dark:text-red-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(summary.total_expense) }}</div>
            <p class="text-xs text-muted-foreground mt-1">Total operational costs</p>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-blue-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Net Balance</CardTitle>
            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-full group-hover:scale-110 transition-transform">
              <Wallet class="h-4 w-4 text-blue-600 dark:text-blue-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold" :class="summary.balance >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600'">
              {{ formatCurrency(summary.balance) }}
            </div>
            <p class="text-xs text-muted-foreground mt-1">Profit/Loss for the period</p>
          </CardContent>
        </Card>
      </div>

      <!-- Filters & Table -->
      <Card class="shadow-sm">
        <CardHeader class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Transactions</h2>
            <CardDescription>Filter and view all financial records.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 flex flex-wrap gap-3 items-end border-b">
                <div class="space-y-1">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Type</Label>
                    <select v-model="filterForm.type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                        <option value="">All Types</option>
                        <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Category</Label>
                    <Input v-model="filterForm.category" list="category-suggestions" placeholder="All Categories" class="h-9 w-full bg-transparent border-input shadow-sm transition-colors" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">From</Label>
                    <Input v-model="filterForm.from" type="date" class="h-9" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">To</Label>
                    <Input v-model="filterForm.to" type="date" class="h-9" />
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
                            <th class="px-6 py-4 font-medium">Date</th>
                            <th class="px-6 py-4 font-medium">Category</th>
                            <th class="px-6 py-4 font-medium">Description</th>
                            <th class="px-6 py-4 font-medium">Recorder</th>
                            <th class="px-6 py-4 font-medium text-right">Amount</th>
                            <th class="px-6 py-4 font-medium text-center">Receipt</th>
                            <th class="px-6 py-4 font-medium text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="record in records.data" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium">{{ record.transaction_date_formatted }}</div>
                                <div class="text-xs text-muted-foreground font-mono">{{ record.created_at_human }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Badge :class="categoryBadgeClass(record.category)" variant="outline">
                                    {{ categories[record.category] || record.category }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 max-w-xs truncate">
                                {{ record.description || '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-muted-foreground">
                                {{ record.recorder?.name || 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold" :class="record.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                <span v-if="record.type === 'income'">+</span><span v-else>-</span>{{ formatCurrency(parseFloat(record.amount)) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <a v-if="record.receipt_url" :href="record.receipt_url" target="_blank" class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-all">
                                    <Tag class="h-4 w-4" />
                                </a>
                                <span v-else class="text-[10px] font-black uppercase text-muted-foreground/30">None</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button @click="openEditDialog(record)" variant="ghost" size="icon" class="h-8 w-8 text-blue-600">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button @click="deleteRecord(record.id)" variant="ghost" size="icon" class="h-8 w-8 text-red-600">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="records.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                <Tag class="h-10 w-10 mx-auto mb-2 opacity-20" />
                                <p>No financial records found for the given filters.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (Simple) -->
            <div v-if="records.last_page > 1" class="px-6 py-4 border-t flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Showing {{ records.from }} to {{ records.to }} of {{ records.total }} entries
                </div>
                <div class="flex gap-2">
                    <Button 
                        v-for="link in records.links.filter((l: any) => !isNaN(parseInt(l.label)) || l.label === '&laquo; Previous' || l.label === 'Next &raquo;')" 
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="router.get(link.url, {}, { preserveState: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
        </CardContent>
      </Card>

      <!-- Category Suggestions Datalist -->
      <datalist id="category-suggestions">
        <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
      </datalist>

      <!-- Create/Edit Dialog -->
      <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{{ editingRecord ? 'Edit Record' : 'Add Financial Record' }}</DialogTitle>
            <DialogDescription>
              Record an income or expense for the facility.
            </DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submit" class="space-y-4 py-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                <Label for="type">Type</Label>
                <select v-model="form.type" id="type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                    <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                </select>
                </div>
                <div class="space-y-2">
                <Label for="category">Category</Label>
                <Input v-model="form.category" id="category" list="category-suggestions" placeholder="e.g. Wages, Maintenance..." class="h-9 w-full bg-transparent" required />
                </div>
            </div>

            <div class="space-y-2">
              <Label for="amount">Amount</Label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-[10px] font-bold text-muted-foreground uppercase opacity-70">{{ (usePage().props.currency as string) || '$' }}</span>
                <Input v-model="form.amount" id="amount" type="number" step="0.01" min="0" class="pl-12" placeholder="0.00" required />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="date">Date & Time</Label>
              <Input v-model="form.transaction_date" id="date" type="datetime-local" required />
            </div>

            <div class="space-y-2">
              <Label for="description">Description (Optional)</Label>
              <Input v-model="form.description" id="description" placeholder="Notes about this transaction..." />
            </div>

            <div class="space-y-2">
              <Label for="receipt">Receipt / Invoice (Optional)</Label>
              <div class="flex items-center gap-3">
                <Input 
                   type="file" 
                   id="receipt" 
                   @input="form.receipt = ($event.target as HTMLInputElement).files?.[0] || null" 
                   accept="image/*"
                   class="cursor-pointer"
                />
              </div>
              <p v-if="editingRecord?.receipt_url" class="text-[10px] text-green-600 font-bold uppercase tracking-widest">
                Has existing receipt
              </p>
            </div>

            <DialogFooter class="pt-4">
              <Button type="button" variant="ghost" @click="isDialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="form.processing">
                {{ editingRecord ? 'Update Record' : 'Save Record' }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
