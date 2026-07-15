<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { index as ordersIndex } from '@/routes/orders';
import { index, create, edit, show, destroy } from '@/routes/admin/clients';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import { Phone, Eye, Pencil, Trash2, PlusCircle, Search, Users } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTableSort } from '@/composables/useTableSort';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Clients'), href: index().url },
]);

interface UserProfile {
  type: string;
  company_name: string | null;
  region: string | null;
}

interface Client {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  status: string;
  statusLabel: string;
  statusHtmlClass: string;
  avatar_url: string;
  user_profile: UserProfile | null;
}

interface Paginated<T> {
  data: T[];
  links: Record<string, any>;
  meta: Record<string, any>;
}

const props = defineProps<{
  clients: Paginated<Client>;
}>();

const search = ref('');
const statusFilter = ref('');
const typeFilter = ref('');
const { sort, isSorted, getSortDirection, toggleSort, getSortIcon } = useTableSort();

const doSearch = () => {
  router.get(index().url, {
    search: search.value,
    status: statusFilter.value || undefined,
    type: typeFilter.value || undefined,
    ...(sort.value && { sort_by: sort.value.column, sort_dir: sort.value.direction }),
  }, { preserveState: true, preserveScroll: true });
};

const handleSort = (column: string) => {
  toggleSort(column);
  const newSort = isSorted(column) ? sort.value : null;
  router.get(index().url, {
    search: search.value,
    status: statusFilter.value || undefined,
    type: typeFilter.value || undefined,
    ...(newSort && { sort_by: newSort.column, sort_dir: newSort.direction }),
  }, { preserveState: true, preserveScroll: true });
};

// Deleting clients is reserved for full-admin roles; couriers may only
// view/add/edit.
const canDeleteClients = computed(() => !!usePage().props.auth.can?.deleteClients);

const deleteClient = (client: Client) => {
  if (!confirm(t('Delete {name}? This cannot be undone.', { name: client.name }))) return;
  router.delete(destroy(client.id).url, { preserveScroll: true });
};

const initiateCall = (phone: string | null) => {
  if (phone && typeof window !== 'undefined' && window.initiateAsteriskCall) {
    window.initiateAsteriskCall(phone);
  }
};
</script>

<template>
  <Head :title="t('Clients')" />
  
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 container mx-auto">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">{{ t('Clients') }}</h1>
          <p class="text-sm text-muted-foreground mt-1">{{ t('Manage external customers and their profiles.') }}</p>
        </div>
        <Link :href="create().url" class="w-full md:w-auto">
          <Button class="w-full md:w-auto gap-2 shadow-sm font-semibold rounded-xl h-11 md:h-10">
            <PlusCircle class="h-4 w-4" /> {{ t('Add Client') }}
          </Button>
        </Link>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 grid grid-cols-1 md:flex md:flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-full md:w-64 md:max-w-sm">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Search') }}</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="search" :placeholder="t('Search accounts...')" class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="doSearch" />
                </div>
                <div class="space-y-1 w-full md:w-40">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Status') }}</Label>
                    <select v-model="statusFilter" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm">
                        <option value="">{{ t('All Statuses') }}</option>
                        <option value="active">{{ t('Active') }}</option>
                        <option value="pending">{{ t('Pending') }}</option>
                        <option value="inactive">{{ t('Inactive') }}</option>
                    </select>
                </div>
                <div class="space-y-1 w-full md:w-40">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">{{ t('Type') }}</Label>
                    <select v-model="typeFilter" class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm">
                        <option value="">{{ t('All Types') }}</option>
                        <option value="individual">{{ t('Individual') }}</option>
                        <option value="business">{{ t('Business') }}</option>
                    </select>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <Button @click="doSearch" variant="secondary" size="sm" class="h-10 md:h-9 flex-1 md:flex-none">{{ t('Search') }}</Button>
                </div>
            </div>

            <!-- Table (Desktop) -->
            <div class="hidden md:block relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('name')">
                                <div class="flex items-center gap-2">{{ t('Client') }} <span class="text-xs">{{ isSorted('name') ? getSortIcon('name') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('user_profile')">
                                <div class="flex items-center gap-2">{{ t('Type') }} <span class="text-xs">{{ isSorted('user_profile') ? getSortIcon('user_profile') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold">{{ t('Phone') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ t('Region') }}</th>
                            <th class="px-6 py-4 font-semibold cursor-pointer hover:text-foreground transition-colors" @click="handleSort('status')">
                                <div class="flex items-center gap-2">{{ t('Status') }} <span class="text-xs">{{ isSorted('status') ? getSortIcon('status') : '⇅' }}</span></div>
                            </th>
                            <th class="px-6 py-4 font-semibold text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <img class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700" :src="client.avatar_url" :alt="client.name">
                                <div>
                                    <Link :href="show(client.id).url" class="font-bold text-gray-900 dark:text-white hover:underline">{{ client.name }}</Link>
                                    <div class="text-xs text-muted-foreground object-contain mt-0.5">{{ client.email }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 capitalize font-medium text-gray-700 dark:text-gray-300">
                                {{ client.user_profile?.company_name ?? client.user_profile?.type ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                  <span class="font-medium font-mono text-gray-900 dark:text-gray-100">{{ client.phone ?? '—' }}</span>
                                  <Button v-if="client.phone" variant="outline" size="icon" class="h-8 w-8 hover:bg-green-50 hover:text-green-600 dark:hover:bg-green-900/30" @click.prevent="initiateCall(client.phone)" :title="t('Call')">
                                    <Phone class="w-3.5 h-3.5 text-green-600 dark:text-green-500 group-hover:block" />
                                  </Button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded text-xs font-medium">{{ client.user_profile?.region ?? '—' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <Badge :variant="client.status === 'active' ? 'default' : (client.status === 'pending' ? 'secondary' : 'destructive')" class="capitalize">
                                   {{ t(client.statusLabel) }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="show(client.id).url" :title="t('View Profile')">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                          <Eye class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                    <Link :href="edit(client.id).url" :title="t('Edit')">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40">
                                          <Pencil class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                    <Button v-if="canDeleteClients" @click="deleteClient(client)" variant="ghost" size="icon" class="h-8 w-8 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/40" :title="t('Delete')">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="clients.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center opacity-60">
                                    <Users class="h-10 w-10 mb-3 text-gray-400" />
                                    <p class="font-medium text-sm">{{ t('No clients found.') }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="md:hidden divide-y divide-border/60">
                <div v-for="client in clients.data" :key="client.id" class="p-4 bg-white dark:bg-background active:bg-muted/30 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <img class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700" :src="client.avatar_url" :alt="client.name">
                            <div>
                                <Link :href="show(client.id).url" class="font-bold text-gray-900 dark:text-white">{{ client.name }}</Link>
                                <div class="text-xs text-muted-foreground mt-0.5">{{ client.email }}</div>
                            </div>
                        </div>
                        <Badge :variant="client.status === 'active' ? 'default' : (client.status === 'pending' ? 'secondary' : 'destructive')" class="capitalize text-[10px] h-5 px-1.5 shrink-0">
                            {{ t(client.statusLabel) }}
                        </Badge>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Type') }}</span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ client.user_profile?.company_name ?? client.user_profile?.type ?? '—' }}</span>
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{{ t('Region') }}</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ client.user_profile?.region ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-dashed border-border/60">
                        <div class="flex items-center gap-2">
                            <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ client.phone ?? '—' }}</span>
                            <Button v-if="client.phone" variant="ghost" size="icon" class="h-8 w-8 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30" @click.prevent="initiateCall(client.phone)" :title="t('Call')">
                                <Phone class="w-3.5 h-3.5 fill-current" />
                            </Button>
                        </div>
                        <div class="flex items-center gap-1">
                            <Link :href="show(client.id).url">
                                <Button variant="secondary" size="sm" class="h-9 px-3 rounded-lg shadow-sm border border-border/50">
                                    <Eye class="h-4 w-4 mr-1" /> {{ t('View') }}
                                </Button>
                            </Link>
                            <Link :href="edit(client.id).url">
                                <Button variant="ghost" size="icon" class="h-9 w-9 text-blue-600">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="clients.data.length === 0" class="md:hidden px-6 py-12 text-center text-muted-foreground">
                <div class="flex flex-col items-center justify-center opacity-60">
                    <Users class="h-10 w-10 mb-3 text-gray-400" />
                    <p class="font-medium text-sm">{{ t('No clients found.') }}</p>
                </div>
            </div>

            <!-- Pagination -->
            <Pagination :paginator="clients" />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
