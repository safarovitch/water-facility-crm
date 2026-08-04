<script setup lang="ts">
import { computed, ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { MapPin } from 'lucide-vue-next';

const props = defineProps<{
  lat?: number | null;
  lng?: number | null;
  address?: string | null;
}>();

const open = ref(false);
const container = ref<HTMLElement | null>(null);

onClickOutside(container, () => { open.value = false; });

const hasLocation = computed(() => !!(props.lat && props.lng) || !!props.address?.trim());

const googleUrl = computed(() => {
  if (props.lat && props.lng) return `https://maps.google.com/?q=${props.lat},${props.lng}`;
  if (props.address) return `https://maps.google.com/?q=${encodeURIComponent(props.address.trim())}`;
  return null;
});

const yandexUrl = computed(() => {
  // Yandex uses lng,lat order for pt parameter
  if (props.lat && props.lng) return `https://yandex.com/maps/?pt=${props.lng},${props.lat}&z=15&l=map`;
  if (props.address) return `https://yandex.com/maps/?text=${encodeURIComponent(props.address.trim())}`;
  return null;
});

const osmUrl = computed(() => {
  if (props.lat && props.lng) return `https://www.openstreetmap.org/?mlat=${props.lat}&mlon=${props.lng}&zoom=15&layers=M`;
  if (props.address) return `https://www.openstreetmap.org/search?query=${encodeURIComponent(props.address.trim())}`;
  return null;
});

const options = computed(() => [
  { label: 'Yandex Maps', url: yandexUrl.value, emoji: '🗺️' },
  { label: 'Google Maps', url: googleUrl.value, emoji: '📍' },
  { label: 'OpenStreetMap', url: osmUrl.value, emoji: '🌍' },
].filter(o => o.url));
</script>

<template>
  <div v-if="hasLocation" ref="container" class="relative inline-flex min-w-0 max-w-full">
    <button
      type="button"
      @click.stop="open = !open"
      class="inline-flex min-w-0 items-start gap-1.5 text-left text-blue-600 dark:text-blue-400 hover:underline"
    >
      <MapPin class="h-4 w-4 shrink-0 mt-0.5" />
      <span class="min-w-0 whitespace-normal break-words"><slot>{{ address }}</slot></span>
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-full mt-1 z-50 min-w-[160px] rounded-lg border border-border bg-white dark:bg-gray-900 shadow-lg py-1 text-sm"
    >
      <a
        v-for="opt in options"
        :key="opt.label"
        :href="opt.url!"
        target="_blank"
        rel="noopener noreferrer"
        class="flex items-center gap-2 px-3 py-2 hover:bg-muted/60 transition-colors"
        @click="open = false"
      >
        <span>{{ opt.emoji }}</span>
        <span class="font-medium">{{ opt.label }}</span>
      </a>
    </div>
  </div>
  <span v-else class="text-sm text-muted-foreground break-words">{{ address || '—' }}</span>
</template>
