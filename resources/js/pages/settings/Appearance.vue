<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { appearance } from '@/routes';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('Appearance settings'),
        href: appearance().url,
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('Appearance settings')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('Appearance settings')" :description="t(&quot;Update your account's appearance settings&quot;)" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
