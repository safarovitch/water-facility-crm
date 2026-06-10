<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlusCircle, Search, RotateCcw, Pause, Play, Eye } from 'lucide-vue-next';
import { useLocale } from '@/composables/useLocale';

const { t } = useLocale();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Subscriptions', href: '/admin/subscriptions' },
];

interface SubscriptionItem {
  id: number;
  product_id: number;
  quantity: number;
  product: { id: number; name: string };
}

interface Subscription {
  id: number;
  status: string;
  frequency: string;
  time_slot: string | null;
  delivery_address: string;
  next_delivery_at: string | null;
  created_at_human: string;
  created_at_formatted: string;
  client: { id: number; name: string; email: string };
  items: SubscriptionItem[];
}

interface Paginated<T> {
  data: T[];
  links: Record<string, any>;
  meta: Record<string, any>;
}

const props = defineProps<{
  subscriptions: Paginated<Subscription>;
  statuses: string[];
  frequencies: { value: string; label: string }[];
}>();

const statusFilter = ref('');
const search = ref('');

const applyFilter = () => {
  router.get('/admin/subscriptions', {
    status: statusFilter.value || undefined,
    search: search.value || undefined,
  }, { preserveState: true, preserveScroll: true });
};

const statusBadgeClass: Record<string, string> = {
  active: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400',
  paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-400',
};

const frequencyLabel = (freq: string) => {
  return props.frequencies.find(f => f.value === freq)?.label ?? freq;
};

const itemsSummary = (items: SubscriptionItem[]) => {
  if (items.length === 0) return '—';
  const first = t(items[0].product.name);
  if (items.length === 1) return `${items[0].quantity}x ${first}`;
  return `${items[0].quantity}x ${first} +${items.length - 1} more`;
};
</script>

<template>
  <Head title="Subscriptions" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-4 md:space-y-6 container mx-auto px-4 md:px-0">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">Subscriptions</h1>
          <p class="text-sm text-muted-foreground mt-1">Manage recurring water delivery schedules.</p>
        </div>
        <Link href="/admin/subscriptions/create" class="w-full md:w-auto">
          <Button class="w-full md:w-auto gap-2 shadow-sm font-semibold rounded-xl h-11 md:h-10">
            <PlusCircle class="h-4 w-4" /> New Subscription
          </Button>
        </Link>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-0">
          <!-- Filters -->
          <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 grid grid-cols-1 md:flex md:flex-wrap gap-3 items-end border-b">
            <div class="space-y-1 relative w-full md:w-64 max-w-sm">
              <Label class="text-xs uppercase tracking-wider text-muted-foreground">Search</Label>
              <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
              <Input v-model="search" placeholder="Client name or email..." class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="applyFilter" />
            </div>
            <div class="space-y-1 w-full md:w-48">
              <Label class="text-xs uppercase tracking-wider text-muted-foreground">Status</Label>
              <select v-model="statusFilter" @change="applyFilter" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                <option value="">All</option>
                <option v-for="s in props.statuses" :key="s" :value="s" class="capitalize">{{ s }}</option>
              </select>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
              <Button @click="applyFilter" variant="secondary" size="sm" class="h-10 md:h-9 flex-1 md:flex-none">Apply</Button>
            </div>
          </div>

          <!-- Desktop Table -->
          <div class="hidden md:block relative overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                <tr>
                  <th class="px-6 py-4 font-semibold">Client</th>
                  <th class="px-6 py-4 font-semibold">Items</th>
                  <th class="px-6 py-4 font-semibold">Frequency</th>
                  <th class="px-6 py-4 font-semibold">Status</th>
                  <th class="px-6 py-4 font-semibold">Next Delivery</th>
                  <th class="px-6 py-4 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                <tr v-for="sub in subscriptions.data" :key="sub.id" class="hover:bg-muted/40 transition-colors group">
                  <td class="px-6 py-4">
                    <div class="font-bold text-gray-900 dark:text-white">{{ sub.client?.name }}</div>
                    <div class="text-xs text-muted-foreground mt-0.5">{{ sub.client?.email }}</div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                    {{ itemsSummary(sub.items) }}
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-medium">{{ frequencyLabel(sub.frequency) }}</span>
                    <div v-if="sub.time_slot" class="text-xs text-muted-foreground capitalize mt-0.5">{{ sub.time_slot }}</div>
                  </td>
                  <td class="px-6 py-4">
                    <Badge variant="outline" class="capitalize border-transparent font-semibold" :class="statusBadgeClass[sub.status]">
                      {{ sub.status }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4">
                    <span v-if="sub.next_delivery_at" class="text-sm text-gray-900 dark:text-white">{{ sub.next_delivery_at }}</span>
                    <span v-else class="text-sm text-muted-foreground">—</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                      <Link :href="`/admin/subscriptions/${sub.id}`">
                        <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400" title="View">
                          <Eye class="h-4 w-4" />
                        </Button>
                      </Link>
                      <Button v-if="sub.status === 'active'" variant="ghost" size="icon" class="h-8 w-8 text-yellow-600 hover:bg-yellow-50" title="Pause" @click="router.patch(`/admin/subscriptions/${sub.id}/pause`)">
                        <Pause class="h-4 w-4" />
                      </Button>
                      <Button v-if="sub.status === 'paused'" variant="ghost" size="icon" class="h-8 w-8 text-green-600 hover:bg-green-50" title="Resume" @click="router.patch(`/admin/subscriptions/${sub.id}/resume`)">
                        <Play class="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards -->
          <div class="md:hidden divide-y divide-border/60">
            <div v-for="sub in subscriptions.data" :key="sub.id" class="p-4 bg-white dark:bg-background active:bg-muted/30 transition-colors">
              <div class="flex items-start justify-between mb-3">
                <div class="flex flex-col">
                  <span class="font-bold text-gray-900 dark:text-white">{{ sub.client?.name }}</span>
                  <span class="text-xs text-muted-foreground mt-0.5">{{ sub.client?.email }}</span>
                </div>
                <Badge variant="outline" class="capitalize border-transparent font-semibold text-[10px] h-5 px-1.5 shrink-0" :class="statusBadgeClass[sub.status]">
                  {{ sub.status }}
                </Badge>
              </div>

              <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="flex flex-col">
                  <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Items</span>
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ itemsSummary(sub.items) }}</span>
                </div>
                <div class="flex flex-col text-right">
                  <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Frequency</span>
                  <span class="text-sm font-medium">{{ frequencyLabel(sub.frequency) }}</span>
                </div>
              </div>

              <div class="flex items-center justify-between pt-3 border-t border-dashed border-border/60">
                <div class="flex flex-col">
                  <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Next Delivery</span>
                  <span class="text-xs text-gray-700 dark:text-gray-300">{{ sub.next_delivery_at ?? 'Not scheduled' }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Link :href="`/admin/subscriptions/${sub.id}`">
                    <Button variant="secondary" size="sm" class="h-9 px-3 rounded-lg shadow-sm border border-border/50">
                      <Eye class="h-4 w-4 mr-1" /> View
                    </Button>
                  </Link>
                  <Button v-if="sub.status === 'active'" variant="ghost" size="icon" class="h-9 w-9 text-yellow-600" @click="router.patch(`/admin/subscriptions/${sub.id}/pause`)">
                    <Pause class="h-4 w-4" />
                  </Button>
                  <Button v-if="sub.status === 'paused'" variant="ghost" size="icon" class="h-9 w-9 text-green-600" @click="router.patch(`/admin/subscriptions/${sub.id}/resume`)">
                    <Play class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="subscriptions.data.length === 0" class="px-6 py-12 text-center text-muted-foreground">
            <div class="flex flex-col items-center justify-center opacity-60">
              <RotateCcw class="h-10 w-10 mb-3 text-gray-400" />
              <p class="font-medium text-sm">No subscriptions found.</p>
            </div>
          </div>

          <!-- Pagination -->
          <Pagination :paginator="subscriptions" />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
