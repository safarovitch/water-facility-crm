<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index as ordersIndex } from '@/routes/orders';
import { index, create, edit, destroy } from '@/routes/admin/clients';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link } from '@inertiajs/vue3';
import { Phone, Eye, Pencil, Trash2, PlusCircle, Search, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clients', href: index().url },
];

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

const doSearch = () => {
  router.get(index().url, { search: search.value }, { preserveState: true, preserveScroll: true });
};

const deleteClient = (client: Client) => {
  if (!confirm(`Delete ${client.name}? This cannot be undone.`)) return;
  router.delete(destroy(client.id).url, { preserveScroll: true });
};

const initiateCall = (phone: string | null) => {
  if (phone && typeof window !== 'undefined' && window.initiateAsteriskCall) {
    window.initiateAsteriskCall(phone);
  }
};
</script>

<template>
  <Head title="Clients" />
  
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 container mx-auto">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Clients</h1>
          <p class="text-sm text-muted-foreground mt-1">Manage external customers and their profiles.</p>
        </div>
        <Link :href="create().url">
          <Button class="gap-2 shadow-sm font-semibold rounded-xl">
            <PlusCircle class="h-4 w-4" /> Add Client
          </Button>
        </Link>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 flex flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-64 flex-1 max-w-sm">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Search</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search accounts..." class="h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="doSearch" />
                </div>
                <div class="flex gap-2">
                    <Button @click="doSearch" variant="secondary" size="sm" class="h-9">Search</Button>
                </div>
            </div>

            <!-- Table -->
            <div class="relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Client</th>
                            <th class="px-6 py-4 font-semibold">Type</th>
                            <th class="px-6 py-4 font-semibold">Phone</th>
                            <th class="px-6 py-4 font-semibold">Region</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <img class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700" :src="client.avatar_url" :alt="client.name">
                                <div>
                                    <Link :href="`/clients/${client.id}`" class="font-bold text-gray-900 dark:text-white hover:underline">{{ client.name }}</Link>
                                    <div class="text-xs text-muted-foreground object-contain mt-0.5">{{ client.email }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 capitalize font-medium text-gray-700 dark:text-gray-300">
                                {{ client.user_profile?.company_name ?? client.user_profile?.type ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                  <span class="font-medium font-mono text-gray-900 dark:text-gray-100">{{ client.phone ?? '—' }}</span>
                                  <Button v-if="client.phone" variant="outline" size="icon" class="h-8 w-8 hover:bg-green-50 hover:text-green-600 dark:hover:bg-green-900/30" @click.prevent="initiateCall(client.phone)" title="Call">
                                    <Phone class="w-3.5 h-3.5 text-green-600 dark:text-green-500 group-hover:block" />
                                  </Button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded text-xs font-medium">{{ client.user_profile?.region ?? '—' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <Badge :variant="client.status === 'active' ? 'default' : (client.status === 'pending' ? 'secondary' : 'destructive')" class="capitalize">
                                   {{ client.statusLabel }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/clients/${client.id}`" title="View Profile">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                          <Eye class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                    <Link :href="edit(client.id).url" title="Edit">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40">
                                          <Pencil class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                    <Button @click="deleteClient(client)" variant="ghost" size="icon" class="h-8 w-8 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/40" title="Delete">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="clients.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center opacity-60">
                                    <Users class="h-10 w-10 mb-3 text-gray-400" />
                                    <p class="font-medium text-sm">No clients found.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="clients.meta?.last_page > 1" class="px-6 py-4 border-t flex flex-wrap gap-1 bg-gray-50/50 dark:bg-gray-800/30">
                <template v-for="(link, key) in clients.meta.links" :key="key">
                    <Button v-if="link.url === null" disabled variant="outline" size="sm" class="opacity-50 h-8" v-html="link.label" />
                    <Button v-else :variant="link.active ? 'default' : 'outline'" size="sm" class="h-8" @click="router.get(link.url, { search: search }, { preserveScroll: true, preserveState: true })" v-html="link.label" />
                </template>
            </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
