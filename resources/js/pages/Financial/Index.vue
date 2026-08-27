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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { PlusCircle, TrendingUp, TrendingDown, Wallet, Calendar, Tag, Trash2, Edit, DollarSign, FileSpreadsheet } from 'lucide-vue-next';
import { useTableSort } from '@/composables/useTableSort';
import { useI18n } from '@/composables/useI18n';

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
    total_revenue?: number;
    total_income?: number;
    total_expense: number;
    balance?: number;
  };
  // True for courier staff: they only see their own expenses and may not
  // record income or delete records.
  courierScoped?: boolean;
  filters: {
    type?: string;
    category?: string;
    from?: string;
    to?: string;
  };
  types: Record<string, string>;
  categories: Record<string, string>;
}>();

const { t, locale } = useI18n();

// Couriers may only record expenses.
const availableTypes = computed(() =>
  props.courierScoped
    ? Object.fromEntries(Object.entries(props.types).filter(([value]) => value === 'expense'))
    : props.types
);

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: props.courierScoped ? t('My Expenses') : t('Accounting'), href: '/admin/financial-records' },
]);

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
    form.post(`/admin/financial-records/${editingRecord.value.id}`, {
      onSuccess: () => {
        isDialogOpen.value = false;
        editingRecord.value = null;
      },
    });
  } else {
    form.post('/admin/financial-records', {
      onSuccess: () => {
        isDialogOpen.value = false;
      },
    });
  }
};

const deleteRecord = (id: number) => {
  const currency = (usePage().props.currency as string) || 'USD';
  if (confirm(t('Are you sure you want to delete this record?'))) {
    router.delete(`/admin/financial-records/${id}`);
  }
};

const filterForm = useForm({
    type: props.filters.type || '',
    category: props.filters.category || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
});

const { sort, isSorted, getSortDirection, toggleSort, getSortIcon } = useTableSort();

const applyFilters = () => {
    router.get('/admin/financial-records', {
        ...filterForm.data(),
        ...(sort.value && { sort_by: sort.value.column, sort_dir: sort.value.direction }),
    }, { preserveState: true });
};

const resetFilters = () => {
    filterForm.reset();
    applyFilters();
};

const handleSort = (column: string) => {
  toggleSort(column);
  const newSort = isSorted(column) ? sort.value : null;
  router.get('/admin/financial-records', {
    ...filterForm.data(),
    ...(newSort && { sort_by: newSort.column, sort_dir: newSort.direction }),
  }, { preserveState: true });
};

/**
 * Plain download link — the server builds the .xlsx from the same filters and
 * sort the table is using, so the sheet matches what is on screen.
 */
const exportUrl = computed(() => {
  const params = new URLSearchParams();

  Object.entries(filterForm.data()).forEach(([key, value]) => {
    if (value) params.set(key, String(value));
  });

  if (sort.value) {
    params.set('sort_by', sort.value.column);
    params.set('sort_dir', sort.value.direction);
  }

  params.set('locale', locale.value);

  return `/admin/financial-records/export?${params.toString()}`;
});

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
  <Head :title="t('Accounting')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">{{ courierScoped ? t('My Expenses') : t('Accounting') }}</h1>
          <p class="text-muted-foreground mt-1 text-lg">{{ courierScoped ? t('Expenses you have recorded.') : t("Manage your facility's income and expenses.") }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
          <Button as-child variant="outline" size="lg" class="w-full sm:w-auto">
            <a :href="exportUrl" download>
              <FileSpreadsheet class="mr-2 h-5 w-5" />
              {{ t('Export to Excel') }}
            </a>
          </Button>
          <Button @click="openCreateDialog" size="lg" class="w-full sm:w-auto shadow-lg hover:shadow-xl transition-all">
            <PlusCircle class="mr-2 h-5 w-5" />
            {{ courierScoped ? t('Add Expense') : t('Add Record') }}
          </Button>
        </div>
      </div>

      <!-- Summary Cards (couriers only see their own expense total) -->
      <div class="grid grid-cols-1 gap-6 mb-10" :class="courierScoped ? 'md:grid-cols-1 lg:max-w-sm' : 'md:grid-cols-2 lg:grid-cols-4'">
        <Card v-if="!courierScoped" class="border-l-4 border-l-purple-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ t('Total Revenue') }}</CardTitle>
            <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-full group-hover:scale-110 transition-transform">
              <DollarSign class="h-4 w-4 text-purple-600 dark:text-purple-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ formatCurrency(summary.total_revenue ?? 0) }}</div>
            <p class="text-xs text-muted-foreground mt-1">{{ t('From delivered orders') }}</p>
          </CardContent>
        </Card>
        <Card v-if="!courierScoped" class="border-l-4 border-l-green-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ t('Total Income') }}</CardTitle>
            <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-full group-hover:scale-110 transition-transform">
              <TrendingUp class="h-4 w-4 text-green-600 dark:text-green-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(summary.total_income ?? 0) }}</div>
            <p class="text-xs text-muted-foreground mt-1">{{ t('Total revenue collected') }}</p>
          </CardContent>
        </Card>
        <Card class="border-l-4 border-l-red-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ courierScoped ? t('My Expenses') : t('Total Expenses') }}</CardTitle>
            <div class="p-2 bg-red-50 dark:bg-red-900/20 rounded-full group-hover:scale-110 transition-transform">
              <TrendingDown class="h-4 w-4 text-red-600 dark:text-red-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(summary.total_expense) }}</div>
            <p class="text-xs text-muted-foreground mt-1">{{ courierScoped ? t('Recorded by you') : t('Total operational costs') }}</p>
          </CardContent>
        </Card>
        <Card v-if="!courierScoped" class="border-l-4 border-l-blue-500 shadow-md hover:shadow-lg transition-all overflow-hidden group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">{{ t('Net Balance') }}</CardTitle>
            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-full group-hover:scale-110 transition-transform">
              <Wallet class="h-4 w-4 text-blue-600 dark:text-blue-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold" :class="(summary.balance ?? 0) >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600'">
              {{ formatCurrency(summary.balance ?? 0) }}
            </div>
            <p class="text-xs text-muted-foreground mt-1">{{ t('Profit/Loss for the period') }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Filters & Table -->
      <Card class="shadow-sm">
        <CardHeader class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">{{ t('Transactions') }}</h2>
            <CardDescription>{{ t('Filter and view all financial records.') }}</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 grid grid-cols-1 md:flex md:flex-wrap gap-3 items-end border-b">
                <div class="grid grid-cols-2 gap-3 w-full md:flex md:gap-3 md:w-auto">
                    <div class="space-y-1 md:w-40">
                        <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Type') }}</Label>
                        <select v-model="filterForm.type" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option value="">{{ t('All Types') }}</option>
                            <option v-for="(label, value) in types" :key="value" :value="value">{{ t(String(label)) }}</option>
                        </select>
                    </div>
                    <div class="space-y-1 md:w-48">
                        <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Category') }}</Label>
                        <Input v-model="filterForm.category" list="category-suggestions" :placeholder="t('All')" class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm" @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 w-full md:flex md:gap-3 md:w-auto">
                    <div class="space-y-1 md:w-40">
                        <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('From') }}</Label>
                        <Input v-model="filterForm.from" type="date" class="h-10 md:h-9" />
                    </div>
                    <div class="space-y-1 md:w-40">
                        <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('To') }}</Label>
                        <Input v-model="filterForm.to" type="date" class="h-10 md:h-9" />
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
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-foreground transition-colors" @click="handleSort('transaction_date')">
                                <div class="flex items-center gap-2">{{ t('Date') }} <span class="text-xs">{{ isSorted('transaction_date') ? getSortIcon('transaction_date') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-foreground transition-colors" @click="handleSort('category')">
                                <div class="flex items-center gap-2">{{ t('Category') }} <span class="text-xs">{{ isSorted('category') ? getSortIcon('category') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-medium">{{ t('Description') }}</th>
                            <th class="px-6 py-4 font-medium">{{ t('Recorder') }}</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-foreground transition-colors" @click="handleSort('amount')">
                                <div class="flex items-center justify-end gap-2">{{ t('Amount') }} <span class="text-xs">{{ isSorted('amount') ? getSortIcon('amount') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-medium text-center">{{ t('Receipt') }}</th>
                            <th class="px-6 py-4 font-medium text-center">{{ t('Actions') }}</th>
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
                                    {{ t(String(categories[record.category] || record.category)) }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 max-w-xs truncate">
                                {{ record.description || '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-muted-foreground">
                                {{ record.recorder?.name || t('Unknown') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold" :class="record.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                <span v-if="record.type === 'income'">+</span><span v-else>-</span>{{ formatCurrency(parseFloat(record.amount)) }}
                             </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <a v-if="record.receipt_url" :href="record.receipt_url" target="_blank" class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-all">
                                    <Tag class="h-4 w-4" />
                                </a>
                                <span v-else class="text-[10px] font-black uppercase text-muted-foreground/30">{{ t('None') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button @click="openEditDialog(record)" variant="ghost" size="icon" class="h-8 w-8 text-blue-600">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button v-if="!courierScoped" @click="deleteRecord(record.id)" variant="ghost" size="icon" class="h-8 w-8 text-red-600">
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
                <div v-for="record in records.data" :key="record.id" class="p-4 bg-white dark:bg-background active:bg-muted/30 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex flex-col">
                            <span class="text-xs text-muted-foreground font-mono">{{ record.transaction_date_formatted }}</span>
                            <Badge :class="categoryBadgeClass(record.category)" variant="outline" class="mt-1 w-fit">
                                {{ t(String(categories[record.category] || record.category)) }}
                            </Badge>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-lg" :class="record.type === 'income' ? 'text-green-600 font-black' : 'text-red-500 font-black'">
                                <span v-if="record.type === 'income'">+</span><span v-else>-</span>{{ formatCurrency(parseFloat(record.amount)) }}
                            </div>
                            <div class="text-[10px] text-muted-foreground uppercase tracking-widest font-bold">{{ t(record.type) }}</div>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-700 dark:text-gray-300 line-clamp-2 mb-3">
                        {{ record.description || t('No description provided') }}
                    </p>

                    <div class="flex items-center justify-between pt-3 border-t border-dashed border-border/60">
                        <div class="flex items-center gap-2">
                            <div class="h-6 w-6 rounded-full bg-muted flex items-center justify-center text-[10px] font-bold">
                                {{ record.recorder?.name?.charAt(0) || '?' }}
                            </div>
                            <span class="text-xs text-muted-foreground">{{ record.recorder?.name || t('Unknown') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a v-if="record.receipt_url" :href="record.receipt_url" target="_blank" class="h-9 w-9 flex items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Tag class="h-4 w-4" />
                            </a>
                            <Button @click="openEditDialog(record)" variant="secondary" size="sm" class="h-9 rounded-lg">
                                <Edit class="h-4 w-4" />
                            </Button>
                            <Button v-if="!courierScoped" @click="deleteRecord(record.id)" variant="ghost" size="icon" class="h-9 w-9 text-red-500 bg-red-50/50 dark:bg-red-900/10 rounded-lg">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="records.data.length === 0" class="px-6 py-12 text-center text-muted-foreground">
                <Tag class="h-10 w-10 mx-auto mb-2 opacity-20" />
                <p>{{ t('No financial records found for the given filters.') }}</p>
            </div>

            <!-- Pagination -->
            <Pagination :paginator="records" />
        </CardContent>
      </Card>



      <!-- Create/Edit Dialog -->
      <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{{ editingRecord ? t('Edit Record') : t('Add Financial Record') }}</DialogTitle>
            <DialogDescription>
              {{ t('Record an income or expense for the facility.') }}
            </DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submit" class="space-y-4 py-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                <Label for="type">{{ t('Type') }}</Label>
                <select v-model="form.type" id="type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                    <option v-for="(label, value) in availableTypes" :key="value" :value="value">{{ t(String(label)) }}</option>
                </select>
                </div>
                <div class="space-y-2">
                <Label for="category">{{ t('Category') }}</Label>
                <Input v-model="form.category" id="category" list="category-suggestions" :placeholder="t('e.g. Wages, Maintenance...')" class="h-9 w-full bg-transparent" required />
                </div>
            </div>

            <div class="space-y-2">
              <Label for="amount">{{ t('Amount') }}</Label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-[10px] font-bold text-muted-foreground uppercase opacity-70">{{ (usePage().props.currency as string) || '$' }}</span>
                <NumberField v-model="form.amount" id="amount" :step="0.01" :min="0" :controls="false" input-class="pl-12 text-left" placeholder="0.00" required />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="date">{{ t('Date & Time') }}</Label>
              <Input v-model="form.transaction_date" id="date" type="datetime-local" required />
            </div>

            <div class="space-y-2">
              <Label for="description">{{ t('Description (Optional)') }}</Label>
              <Input v-model="form.description" id="description" :placeholder="t('Notes about this transaction...')" />
            </div>

            <div class="space-y-2">
              <Label for="receipt">{{ t('Receipt / Invoice (Optional)') }}</Label>
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
                {{ t('Has existing receipt') }}
              </p>
            </div>

            <DialogFooter class="pt-4">
              <Button type="button" variant="ghost" @click="isDialogOpen = false">{{ t('Cancel') }}</Button>
              <Button type="submit" :disabled="form.processing">
                {{ editingRecord ? t('Update Record') : t('Save Record') }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <!-- Category Suggestions Datalist -->
      <datalist id="category-suggestions">
        <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
      </datalist>

    </div>
  </AppLayout>
</template>
