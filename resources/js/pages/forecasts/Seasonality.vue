<script setup lang="ts">
import SeasonalityCurve from '@/components/forecasting/SeasonalityCurve.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Info, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface MonthPoint {
    month: number;
    index: number;
    prior: number;
    observed: number | null;
    source: string;
    sample_size: number;
}

interface Curve {
    segment: string;
    label: string;
    months: MonthPoint[];
}

defineProps<{
    curves: Curve[];
    status: { months_of_history: number; months_required: number; months_remaining: number; learning: boolean };
    limits: { floor: number; ceiling: number };
}>();

const { t } = useI18n();

const canEdit = computed(() => !!usePage().props.auth.can?.manageSeasonality);

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('Forecasts'), href: '/admin/forecasts/index' },
    { title: t('Seasonality'), href: '/admin/forecasts/seasonality' },
]);

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const expanded = ref<string | null>(null);
const draft = ref<Record<string, string>>({});

const toggle = (segment: string) => {
    expanded.value = expanded.value === segment ? null : segment;
};

const key = (segment: string, month: number) => `${segment}-${month}`;

const save = (segment: string, month: number) => {
    const raw = draft.value[key(segment, month)];
    const value = raw === undefined || raw === '' ? null : Number(raw);

    router.post(
        '/admin/forecasts/seasonality',
        { segment, month, index: value },
        { preserveScroll: true, onSuccess: () => delete draft.value[key(segment, month)] },
    );
};

const reset = (segment: string, month: number) => {
    router.post('/admin/forecasts/seasonality', { segment, month, index: null }, { preserveScroll: true });
};

const sourceLabel: Record<string, string> = {
    prior: 'Default',
    blended: 'Part measured',
    learned: 'Measured',
    manual: 'Set by hand',
};

const sourceClass: Record<string, string> = {
    prior: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    blended: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    learned: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    manual: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

/** Biggest swing in a curve, as a one-glance summary of how seasonal it is. */
const swing = (curve: Curve) => {
    const values = curve.months.map((m) => m.index);
    return `${Math.min(...values).toFixed(2)} – ${Math.max(...values).toFixed(2)}`;
};
</script>

<template>
    <Head :title="t('Seasonality')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto space-y-4 px-4 md:space-y-6 md:px-0">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">{{ t('Seasonality') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {{
                        t(
                            "How demand in each segment rises and falls across the year. 1.00 is that segment's own yearly average, so 1.40 means 40% busier than usual — not 40% of the business.",
                        )
                    }}
                </p>
            </div>

            <Card class="shadow-sm">
                <CardContent class="flex items-start gap-3 p-4">
                    <Info class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                    <div class="text-sm">
                        <p v-if="status.learning" class="text-foreground">
                            {{ t('Curves are being measured from') }} {{ status.months_of_history }} {{ t('months of order history.') }}
                        </p>
                        <p v-else class="text-foreground">
                            {{ t('Curves are the built-in defaults.') }}
                            {{ t('Measuring them from real data needs') }} {{ status.months_required }} {{ t('months of history; there are') }}
                            {{ status.months_of_history }}.
                            {{ t('The switch happens automatically — no action needed.') }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{
                                t(
                                    'A month you set by hand is never overwritten by recalculation, and the rest of the year rescales around it so the year still averages 1.00.',
                                )
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="curve in curves" :key="curve.segment" class="shadow-sm">
                    <CardContent class="p-4">
                        <button type="button" class="flex w-full items-start justify-between gap-3 text-left" @click="toggle(curve.segment)">
                            <div>
                                <div class="font-semibold text-foreground">{{ t(curve.label) }}</div>
                                <div class="mt-0.5 text-xs text-muted-foreground">{{ t('Range') }} {{ swing(curve) }}</div>
                            </div>
                            <Badge variant="secondary" class="shrink-0">
                                {{ expanded === curve.segment ? t('Hide months') : t('Show months') }}
                            </Badge>
                        </button>

                        <div class="mt-3">
                            <SeasonalityCurve :months="curve.months" />
                        </div>

                        <div v-if="expanded === curve.segment" class="mt-4 space-y-1.5 border-t border-border pt-3">
                            <div v-for="month in curve.months" :key="month.month" class="flex items-center gap-2 text-sm">
                                <span class="w-9 shrink-0 text-muted-foreground">{{ t(monthNames[month.month - 1]) }}</span>

                                <span
                                    class="w-24 shrink-0 rounded px-1.5 py-0.5 text-center text-[10px] font-medium tracking-wide uppercase"
                                    :class="sourceClass[month.source] ?? sourceClass.prior"
                                >
                                    {{ t(sourceLabel[month.source] ?? month.source) }}
                                </span>

                                <template v-if="canEdit">
                                    <Input
                                        :model-value="draft[key(curve.segment, month.month)] ?? month.index.toFixed(2)"
                                        type="number"
                                        :min="limits.floor"
                                        :max="limits.ceiling"
                                        step="0.01"
                                        class="h-8 w-24"
                                        @update:model-value="(v: string | number) => (draft[key(curve.segment, month.month)] = String(v))"
                                        @keyup.enter="save(curve.segment, month.month)"
                                    />
                                    <Button
                                        v-if="draft[key(curve.segment, month.month)] !== undefined"
                                        size="sm"
                                        class="h-8"
                                        @click="save(curve.segment, month.month)"
                                    >
                                        {{ t('Save') }}
                                    </Button>
                                    <Button
                                        v-else-if="month.source === 'manual'"
                                        size="sm"
                                        variant="ghost"
                                        class="h-8"
                                        :title="t('Reset to calculated value')"
                                        @click="reset(curve.segment, month.month)"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" />
                                    </Button>
                                </template>
                                <span v-else class="w-24 font-medium text-foreground">{{ month.index.toFixed(2) }}</span>

                                <span class="ml-auto text-xs text-muted-foreground">
                                    {{ t('default') }} {{ month.prior.toFixed(2) }}
                                    <template v-if="month.sample_size > 0"> · n={{ month.sample_size }}</template>
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
