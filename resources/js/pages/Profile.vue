<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import ClientDashboard from '../components/ClientDashboard.vue';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isClient = computed(() => user.value.roles.includes('Client'));

const props = defineProps<{
    activeOrder?: any;
    orderHistory?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Profile',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="My Profile" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="isClient" class="p-6">
            <ClientDashboard
                :auth="page.props.auth"
                :active-order="activeOrder"
                :order-history="orderHistory || []"
            />
        </div>
        <div v-else class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Staff/admin viewing their own profile page -->
            <div class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-2xl font-bold text-primary">
                    {{ user.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-foreground">{{ user.name }}</h2>
                    <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                    <div class="mt-1 flex gap-2">
                        <span
                            v-for="role in user.roles"
                            :key="role"
                            class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >
                            {{ role }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    To update your profile information, name or password, visit
                    <a href="/settings/profile" class="font-medium text-primary underline-offset-4 hover:underline">Settings → Profile</a>.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
