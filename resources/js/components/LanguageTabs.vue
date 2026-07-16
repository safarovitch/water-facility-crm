<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';

const { locale, setLocale, locales } = useI18n();
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
        <button
            v-for="l in locales"
            :key="l.code"
            @click="setLocale(l.code)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                locale === l.code
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <span class="text-sm">{{ l.label }}</span>
        </button>
    </div>
</template>
