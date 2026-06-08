<script setup lang="ts">
/**
 * Thin wrapper around the primary brand wordmark. The old procedural variants
 * (mark/inline/curved/wordmark/stacked) are kept as accepted-but-ignored props
 * so existing call sites don't break — every variant now renders the single
 * official logo (public/fann-logo-primary.svg via BrandLogo).
 *
 * Sizing is passed through via `class`; use `light` on dark backgrounds.
 */
import BrandLogo from '@/components/BrandLogo.vue';

withDefaults(
    defineProps<{
        variant?: 'wordmark' | 'stacked' | 'mark' | 'inline' | 'curved';
        accentColor?: string;
        showTagline?: boolean;
        light?: boolean;
    }>(),
    {
        variant: 'wordmark',
        accentColor: '#38bdf8',
        showTagline: true,
        light: false,
    },
);
</script>

<template>
    <BrandLogo :light="light" />
</template>
