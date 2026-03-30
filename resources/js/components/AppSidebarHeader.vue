<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="mb-4 md:mb-6 flex min-h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 pt-[env(safe-area-inset-top)]"
    >
        <div class="mx-auto w-full max-w-screen-2xl px-4 md:px-6 flex items-center justify-between md:justify-start gap-2 h-16">
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1 hidden md:inline-flex" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>
            
            <!-- Mobile Menu Placeholder or Action if needed -->
            <div class="md:hidden">
                <!-- Can add a specific mobile notification icon here later -->
            </div>
        </div>
    </header>
</template>
