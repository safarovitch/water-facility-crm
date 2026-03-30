<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index, create, edit, show } from '@/routes/admin/users';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlusCircle, Search, Eye, Pencil, Users } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: index().url
    },
];

interface Paginated<T> {
    data: T[];
    links: Record<string, any> | any[];
    meta?: Record<string, any>;
    last_page?: number;
}

interface User {
    id: number;
    name: string;
    email: string;
    roles: Array<Role>;
    avatar: string;
    status: string;
    statusHtmlClass: string;
    statusLabel: string;
    avatar_url: string;
}

interface Role {
    id: number;
    name: string;
    guard_name: string;
}

const props = defineProps<{
    users: Paginated<User>;
    filters?: { search?: string };
}>();

const search = ref(props.filters?.search || '');

const doSearch = () => {
  router.get(index().url, { search: search.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
  <Head title="Users" />
  
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 container mx-auto">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Users</h1>
          <p class="text-sm text-muted-foreground mt-1">Manage system access, roles, and administrative profiles.</p>
        </div>
        <Link :href="create().url">
          <Button class="gap-2 shadow-sm font-semibold rounded-xl">
            <PlusCircle class="h-4 w-4" /> Add User
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
                    <Input v-model="search" placeholder="Search by name or email..." class="h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="doSearch" />
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
                            <th class="px-6 py-4 font-semibold">User Profile</th>
                            <th class="px-6 py-4 font-semibold">Role</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <img class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700" :src="user.avatar_url" :alt="user.name">
                                <div>
                                    <Link :href="show({ user: user.id })" class="font-bold text-gray-900 dark:text-white hover:underline">{{ user.name }}</Link>
                                    <div class="text-xs text-muted-foreground object-contain mt-0.5">{{ user.email }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 capitalize font-medium text-gray-700 dark:text-gray-300">
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="role in user.roles" :key="role.id" variant="outline" class="font-normal">{{ role.name }}</Badge>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <Badge :variant="user.status === 'active' ? 'default' : (user.status === 'pending' ? 'secondary' : 'destructive')" class="capitalize">
                                   {{ user.statusLabel }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="show({ user: user.id })" title="View Profile">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                          <Eye class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                    <Link :href="edit({ user: user.id })" title="Edit">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40">
                                          <Pencil class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center opacity-60">
                                    <Users class="h-10 w-10 mb-3 text-gray-400" />
                                    <p class="font-medium text-sm">No users found.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="(users.meta?.last_page || users.last_page) > 1" class="px-6 py-4 border-t flex flex-wrap gap-1 bg-gray-50/50 dark:bg-gray-800/30">
                <template v-for="(link, key) in (users.meta?.links || users.links)" :key="key">
                    <Button v-if="link.url === null" disabled variant="outline" size="sm" class="opacity-50 h-8" v-html="link.label" />
                    <Button v-else :variant="link.active ? 'default' : 'outline'" size="sm" class="h-8" @click="router.get(link.url, { search: search }, { preserveScroll: true, preserveState: true })" v-html="link.label" />
                </template>
            </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
