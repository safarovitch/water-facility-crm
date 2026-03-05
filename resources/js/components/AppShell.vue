<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import WebRTCDialer from '@/components/WebRTCDialer.vue';

interface Props {
  variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
  <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col relative">
    <slot />
    <WebRTCDialer />
  </div>
  <SidebarProvider v-else :default-open="isOpen" class="relative">
    <slot />
    <WebRTCDialer />
  </SidebarProvider>
</template>
