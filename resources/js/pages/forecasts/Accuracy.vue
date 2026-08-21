<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Summary {
    observations: number;
    predicted_units: number;
    actual_units: number;
    wape: number | null;
    accuracy_pct: number | null;
    bias_pct: number | null;
    coverage_pct: number | null;
}

interface RecentDay {
    date: string;
    predicted: number;
    actual: number;
    p10: number;
    p90: number;
    in_band: boolean;
}

const props = defineProps<{
    days: number;
    metrics: {
        window_days: number;
        observations: number;
        total: Summary;
        by_segment: Record<string, Summary>;
        by_lead_time: Record<string, Summary>;
        recent_days: RecentDay[];
        bias_factor: number;
    };
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('Forecasts'), href: '/admin/forecasts/index' },
    { title: t('Accuracy'), href: '/admin/forecasts/accuracy' },
]);

const windows = [30, 90, 180, 365];
const setWindow = (days: number) => router.get('/admin/forecasts/accuracy', { days }, { preserveState: true, preserveScroll: true });

const scored = computed(() => props.metrics.total.observations > 0);

/** Scale for the comparison bars: whichever is larger, predicted or actual. */
const maxRecent = computed(() => Math.max(1, ...props.metrics.recent_days.flatMap((d) => [d.predicted, d.actual, d.p90])));

const leadOrder = ['0-1 days', '2-7 days', '8-14 days', '15-30 days', '30+ days'];
const orderedLeadTimes = computed(() =>
    leadOrder.filter((k) => props.metrics.by_lead_time[k]).map((k) => ({ bucket: k, ...props.metrics.by_lead_time[k] })),
);

const segmentRows = computed(() =>
    Object.entries(props.metrics.by_segment)
        .map(([segment, m]) => ({ segment, ...m }))
        .sort((a, b) => b.actual_units - a.actual_units),
);

const accuracyClass = (value: number | null) => {
    if (value === null) return 'text-muted-foreground';
    if (value >= 85) return 'text-emerald-600 dark:text-emerald-400';
    if (value >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
};

const shortDate = (iso: string) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
</script>

<template>
    <Head :title="t('Forecast accuracy')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto space-y-4 px-4 md:space-y-6 md:px-0">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">{{ t('Forecast accuracy') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('How past forecasts compared with what was actually ordered. This is what makes the forecast improve.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Window') }}</span>
                <Button v-for="w in windows" :key="w" :variant="days === w ? 'default' : 'outline'" size="sm" @click="setWindow(w)">
                    {{ w }} {{ t('days') }}
                </Button>
            </div>

            <Card v-if="!scored" class="shadow-sm">
                <CardContent class="p-6 text-center">
                    <p class="text-sm text-muted-foreground">
                        {{ t('No forecasts have been scored yet.') }}
                    </p>
                    <p class="mx-auto mt-2 max-w-xl text-xs text-muted-foreground">
                        {{
                            t(
                                'The scheduler snapshots the forecast every night and grades it the following night. The first numbers appear a day after the schedule starts running.',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>

            <template v-else>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Card class="shadow-sm">
                        <CardContent class="space-y-1 p-4">
                            <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Accuracy') }}</div>
                            <div class="text-2xl font-bold" :class="accuracyClass(metrics.total.accuracy_pct)">{{ metrics.total.accuracy_pct }}%</div>
                            <div class="text-xs text-muted-foreground">{{ metrics.total.observations }} {{ t('days scored') }}</div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-sm">
                        <CardContent class="space-y-1 p-4">
                            <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Bias') }}</div>
                            <div class="text-2xl font-bold text-foreground">
                                {{ (metrics.total.bias_pct ?? 0) > 0 ? '+' : '' }}{{ metrics.total.bias_pct }}%
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ (metrics.total.bias_pct ?? 0) > 0 ? t('forecast ran high') : t('forecast ran low') }}
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-sm">
                        <CardContent class="space-y-1 p-4">
                            <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Range coverage') }}</div>
                            <div class="text-2xl font-bold text-foreground">{{ metrics.total.coverage_pct }}%</div>
                            <!--
                The band claims to catch 8 days in 10. Much higher means it is
                uselessly wide; much lower means it is overconfident.
              -->
                            <div class="text-xs text-muted-foreground">{{ t('80% is well calibrated') }}</div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-sm">
                        <CardContent class="space-y-1 p-4">
                            <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Self-correction') }}</div>
                            <div class="text-2xl font-bold text-foreground">×{{ metrics.bias_factor }}</div>
                            <div class="text-xs text-muted-foreground">{{ t('applied to new forecasts') }}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card class="shadow-sm">
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('Predicted vs actual, by day') }}</h2>
                        <div class="space-y-1.5">
                            <div v-for="day in metrics.recent_days" :key="day.date" class="flex items-center gap-3 text-xs">
                                <span class="w-16 shrink-0 text-muted-foreground">{{ shortDate(day.date) }}</span>
                                <div class="relative h-5 flex-1 overflow-hidden rounded bg-muted">
                                    <!-- the stated range, drawn behind both bars -->
                                    <div
                                        class="absolute inset-y-0 bg-sky-500/15"
                                        :style="{ left: `${(day.p10 / maxRecent) * 100}%`, width: `${((day.p90 - day.p10) / maxRecent) * 100}%` }"
                                    ></div>
                                    <div
                                        class="absolute inset-y-0 left-0 border-r-2 border-sky-500"
                                        :style="{ width: `${(day.predicted / maxRecent) * 100}%` }"
                                    ></div>
                                    <div
                                        class="absolute inset-y-1.5 left-0 rounded-r"
                                        :class="day.in_band ? 'bg-emerald-500/70' : 'bg-red-500/70'"
                                        :style="{ width: `${(day.actual / maxRecent) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="w-28 shrink-0 text-right text-muted-foreground">
                                    {{ day.actual }} / <span class="text-sky-600 dark:text-sky-400">{{ day.predicted }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                            <span class="flex items-center gap-1.5"
                                ><span class="h-2.5 w-4 border-r-2 border-sky-500 bg-sky-500/15"></span>{{ t('Predicted and its range') }}</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span class="h-2.5 w-4 rounded bg-emerald-500/70"></span>{{ t('Actual, inside range') }}</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span class="h-2.5 w-4 rounded bg-red-500/70"></span>{{ t('Actual, outside range') }}</span
                            >
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-4 lg:grid-cols-2">
                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <h2 class="mb-1 text-sm font-semibold text-foreground">{{ t('By lead time') }}</h2>
                            <p class="mb-3 text-xs text-muted-foreground">
                                {{
                                    t(
                                        'A forecast for tomorrow should beat one for next month. If it does not, the model is not using recent information.',
                                    )
                                }}
                            </p>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase">
                                        <th class="pb-2 text-left font-semibold">{{ t('Lead time') }}</th>
                                        <th class="pb-2 text-right font-semibold">{{ t('Days') }}</th>
                                        <th class="pb-2 text-right font-semibold">{{ t('Accuracy') }}</th>
                                        <th class="pb-2 text-right font-semibold">{{ t('Bias') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="row in orderedLeadTimes" :key="row.bucket">
                                        <td class="py-2 text-foreground">{{ row.bucket }}</td>
                                        <td class="py-2 text-right text-muted-foreground">{{ row.observations }}</td>
                                        <td class="py-2 text-right font-medium" :class="accuracyClass(row.accuracy_pct)">{{ row.accuracy_pct }}%</td>
                                        <td class="py-2 text-right text-muted-foreground">
                                            {{ (row.bias_pct ?? 0) > 0 ? '+' : '' }}{{ row.bias_pct }}%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <h2 class="mb-1 text-sm font-semibold text-foreground">{{ t('By segment') }}</h2>
                            <p class="mb-3 text-xs text-muted-foreground">
                                {{
                                    t(
                                        'A segment that is consistently wrong usually means its clients are misclassified or its seasonal curve is off.',
                                    )
                                }}
                            </p>
                            <table v-if="segmentRows.length" class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase">
                                        <th class="pb-2 text-left font-semibold">{{ t('Segment') }}</th>
                                        <th class="pb-2 text-right font-semibold">{{ t('Actual') }}</th>
                                        <th class="pb-2 text-right font-semibold">{{ t('Accuracy') }}</th>
                                        <th class="pb-2 text-right font-semibold">{{ t('Bias') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="row in segmentRows" :key="row.segment">
                                        <td class="py-2 text-foreground">{{ row.segment }}</td>
                                        <td class="py-2 text-right text-muted-foreground">{{ row.actual_units.toLocaleString() }}</td>
                                        <td class="py-2 text-right font-medium" :class="accuracyClass(row.accuracy_pct)">
                                            {{ row.accuracy_pct !== null ? `${row.accuracy_pct}%` : '—' }}
                                        </td>
                                        <td class="py-2 text-right text-muted-foreground">
                                            {{ row.bias_pct !== null ? `${row.bias_pct > 0 ? '+' : ''}${row.bias_pct}%` : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="py-6 text-center text-sm text-muted-foreground">{{ t('No segment-level history yet.') }}</p>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
