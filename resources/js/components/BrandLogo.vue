<script setup lang="ts">
/**
 * The Fann brand wordmark (public/fann-logo-primary.svg).
 *
 * The asset is a fixed navy (#0a1f3c) wordmark, so it's served as a cached
 * static file rather than bundled. Pass sizing via `class` (e.g. "h-9 w-auto").
 * On dark surfaces pass `light` (renders white) or add `dark:brightness-0
 * dark:invert` for a theme-adaptive logo.
 */
withDefaults(
    defineProps<{
        light?: boolean;
        alt?: string;
    }>(),
    {
        light: false,
        alt: 'Fann',
    },
);
</script>

<template>
    <img
        src="/fann-logo-primary.svg"
        :alt="alt"
        draggable="false"
        :class="light ? 'brightness-0 invert' : ''"
    />
</template>
