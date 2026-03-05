<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index as ordersIndex } from '@/routes/orders';
import { index, create, edit, destroy } from '@/routes/clients';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    router.get(index().url, { search: search.value }, { preserveState: true });
};

const deleteClient = (client: Client) => {
    if (!confirm(`Delete ${client.name}? This cannot be undone.`)) return;
    router.delete(destroy(client.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head title="Clients" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative overflow-x-auto sm:rounded-lg">
            <div class="p-4 flex items-center justify-between flex-wrap gap-3 pb-4 bg-white dark:bg-gray-900">
                <div class="flex gap-2">
                    <Link :href="create().url"
                        class="inline-flex items-center text-gray-500 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:bg-gray-700">
                        + Add Client
                    </Link>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                        </svg>
                    </div>
                    <input v-model="search" @keyup.enter="doSearch" type="text"
                        class="block p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg w-72 bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="Search clients..." />
                </div>
            </div>

            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Name</th>
                        <th scope="col" class="px-6 py-3">Type</th>
                        <th scope="col" class="px-6 py-3">Phone</th>
                        <th scope="col" class="px-6 py-3">Region</th>
                        <th scope="col" class="px-6 py-3">Status</th>
                        <th scope="col" class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="client in props.clients.data" :key="client.id"
                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 border-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <th scope="row" class="flex items-center px-6 py-4 text-gray-900 whitespace-nowrap dark:text-white">
                            <img class="w-10 h-10 rounded-full" :src="client.avatar_url" :alt="client.name">
                            <div class="ps-3">
                                <div class="text-base font-semibold">{{ client.name }}</div>
                                <div class="font-normal text-gray-500">{{ client.email }}</div>
                            </div>
                        </th>
                        <td class="px-6 py-4 capitalize">
                            {{ client.user_profile?.company_name ?? client.user_profile?.type ?? '—' }}
                        </td>
                        <td class="px-6 py-4">{{ client.phone ?? '—' }}</td>
                        <td class="px-6 py-4">{{ client.user_profile?.region ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="h-2.5 w-2.5 rounded-full me-2" :class="client.statusHtmlClass"></div>
                                {{ client.statusLabel }}
                            </div>
                        </td>
                        <td class="px-6 py-4 flex items-center gap-3">
                            <Link :href="ordersIndex({ query: { user_id: client.id } }).url"
                                class="font-medium text-gray-600 dark:text-gray-400 hover:underline">Orders</Link>
                            <Link :href="edit(client.id).url"
                                class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</Link>
                            <button @click="deleteClient(client)"
                                class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="props.clients.data.length === 0">
                        <td colspan="6" class="px-6 py-10 text-center text-gray-400">No clients found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
