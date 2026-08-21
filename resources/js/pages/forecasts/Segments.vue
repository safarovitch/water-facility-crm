<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Info, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ClientRow {
    id: number;
    name: string;
    company_name: string | null;
    segment: string;
    source: string;
    confidence: number | null;
}

const props = defineProps<{
    clients: { data: ClientRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    segments: { value: string; label: string }[];
    filters: { search: string; segment: string | null };
    counts: Record<string, number>;
}>();

const { t } = useI18n();

const canEdit = computed(() => !!usePage().props.auth.can?.manageSegments);

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('Forecasts'), href: '/admin/forecasts/index' },
    { title: t('Segments'), href: '/admin/forecasts/segments' },
]);

const search = ref(props.filters.search);

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/admin/forecasts/segments',
            { search: value, segment: props.filters.segment },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const filterBySegment = (segment: string | null) =>
    router.get('/admin/forecasts/segments', { search: search.value, segment }, { preserveState: true, preserveScroll: true });

const setSegment = (client: ClientRow, segment: string) => {
    if (segment === client.segment) return;
    router.post(`/admin/forecasts/segments/${client.id}`, { segment }, { preserveScroll: true, preserveState: true });
};

const sourceLabel: Record<string, string> = {
    default: 'Not classified',
    rules: 'By keyword',
    ai: 'By AI',
    manual: 'By hand',
};

const sourceClass: Record<string, string> = {
    default: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    rules: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    ai: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    manual: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

const unclassified = computed(() => props.counts['unknown'] ?? 0);

/**
 * Laravel's paginator ships its labels as HTML entities ("&laquo; Previous").
 * Decoding them to plain text keeps them out of v-html, which is both an
 * XSS-shaped footgun and disallowed on a component by the lint rules.
 */
const paginationLabel = (label: string) =>
    label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&nbsp;/g, ' ')
        .replace(/<[^>]*>/g, '')
        .trim();
</script>

<template>
    <Head :title="t('Client segments')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto space-y-4 px-4 md:space-y-6 md:px-0">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">{{ t('Client segments') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {{
                        t(
                            'Segments decide which seasonal pattern each client is forecast against. A school marked as an office will be predicted to keep ordering all summer.',
                        )
                    }}
                </p>
            </div>

            <Card v-if="unclassified > 0" class="border-amber-300 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                <CardContent class="flex items-start gap-3 p-4 text-sm">
                    <Info class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <span class="font-semibold text-amber-900 dark:text-amber-200">
                            {{ unclassified }} {{ t('clients are unclassified.') }}
                        </span>
                        <span class="text-amber-800 dark:text-amber-300">
                            {{ t('They are forecast with a flat curve — no seasonal rise or fall at all — which is safe but imprecise. Run') }}
                            <code class="rounded bg-amber-100 px-1 py-0.5 text-xs dark:bg-amber-900/60"
                                >php artisan forecast:classify-segments --ai</code
                            >
                            {{ t('or set them below.') }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" :placeholder="t('Search clients')" class="pl-8" />
                </div>
                <button
                    type="button"
                    class="rounded-full border px-2.5 py-1 text-xs transition-colors"
                    :class="!filters.segment ? 'border-sky-500 bg-sky-500/10 text-sky-700 dark:text-sky-300' : 'border-border text-muted-foreground'"
                    @click="filterBySegment(null)"
                >
                    {{ t('All') }} ({{ clients.total }})
                </button>
                <button
                    v-for="segment in segments"
                    :key="segment.value"
                    type="button"
                    class="rounded-full border px-2.5 py-1 text-xs transition-colors"
                    :class="
                        filters.segment === segment.value
                            ? 'border-sky-500 bg-sky-500/10 text-sky-700 dark:text-sky-300'
                            : 'border-border text-muted-foreground hover:border-foreground/30'
                    "
                    @click="filterBySegment(segment.value)"
                >
                    {{ t(segment.label) }} ({{ counts[segment.value] ?? 0 }})
                </button>
            </div>

            <Card class="shadow-sm">
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-sm">
                            <thead>
                                <tr class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase">
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Client') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Segment') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Set by') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="client in clients.data" :key="client.id" class="hover:bg-muted/40">
                                    <td class="px-4 py-2.5">
                                        <div class="font-medium text-foreground">{{ client.name }}</div>
                                        <div v-if="client.company_name" class="text-xs text-muted-foreground">{{ client.company_name }}</div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select
                                            v-if="canEdit"
                                            :value="client.segment"
                                            class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                                            @change="setSegment(client, ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option v-for="segment in segments" :key="segment.value" :value="segment.value">
                                                {{ t(segment.label) }}
                                            </option>
                                        </select>
                                        <span v-else class="text-foreground">{{ client.segment }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-[10px] font-medium tracking-wide uppercase"
                                            :class="sourceClass[client.source] ?? sourceClass.default"
                                        >
                                            {{ t(sourceLabel[client.source] ?? client.source) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!clients.data.length">
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-muted-foreground">{{ t('No clients found.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div v-if="clients.links.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in clients.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'border-sky-500 bg-sky-500/10 text-sky-700 dark:text-sky-300'
                                : 'border-border text-muted-foreground hover:border-foreground/30'
                        "
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                    <span v-else class="rounded-md px-3 py-1.5 text-sm text-muted-foreground">{{ paginationLabel(link.label) }}</span>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
