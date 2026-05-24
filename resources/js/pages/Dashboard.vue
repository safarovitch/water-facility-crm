<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import ClientDashboard from '../components/ClientDashboard.vue';

const page = usePage();

const props = defineProps<{
    activeOrder?: any;
    orderHistory?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <ClientDashboard
                :auth="page.props.auth"
                :active-order="activeOrder"
                :order-history="orderHistory || []"
            />
        </div>
    </AppLayout>
</template>
