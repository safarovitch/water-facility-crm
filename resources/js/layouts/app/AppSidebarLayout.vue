<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BottomNav from '@/components/BottomNav.vue';
import OrderNotificationToast from '@/components/OrderNotificationToast.vue';

import type { BreadcrumbItemType } from '@/types';

import { ref, onMounted } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="flex min-h-svh flex-col">
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" class="overflow-x-hidden pb-20 md:pb-0 transition-[padding] duration-200">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <slot />
            </AppContent>
            <BottomNav />
            <template v-if="mounted && $page.props.auth.user">
                <OrderNotificationToast />
            </template>
        </AppShell>
    </div>
</template>
