<script setup lang="ts">
import DemandChart from '@/components/forecasting/DemandChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Info, PackageSearch, Route as RouteIcon, Sparkles, Target } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Day {
    date: string;
    weekday: number;
    orders: number;
    predicted_orders: number;
    committed_orders: number;
    units: number;
    predicted_units: number;
    committed_units: number;
    units_p10: number;
    units_p90: number;
    revenue: number;
}

interface SegmentRow {
    segment: string;
    label: string;
    orders: number;
    units: number;
    revenue: number;
    clients: number;
}

interface Material {
    id: number;
    name: string;
    unit: string;
    is_reusable: boolean;
    current_stock: number;
    gross_required: number;
    net_required: number;
    circulating: number | null;
    return_rate: number | null;
    shortfall: number;
    purchase_cost: number;
    daily_burn: number;
    days_of_cover: number | null;
    deposit_at_risk: number;
}

const props = defineProps<{
    horizon: number;
    forecast: {
        from: string;
        to: string;
        days: Day[];
        segments: SegmentRow[];
        products: { product_id: number; name: Record<string, string> | string | null; units: number; revenue: number }[];
        totals: {
            orders: number;
            predicted_orders: number;
            committed_orders: number;
            units: number;
            units_p10: number;
            units_p90: number;
            revenue: number;
        };
        seasonality: { months_of_history: number; months_required: number; months_remaining: number; learning: boolean };
        bias_factor: number;
        model: { clients_modelled: number; clients_churned: number; clients_subscribed: number };
    };
    procurement: { materials: Material[]; total_purchase: number; deposit_at_risk: number };
    unmeasured: Record<number, string>;
    accuracy: { observations: number; accuracy_pct: number | null; bias_pct: number | null; coverage_pct: number | null };
    segments: { value: string; label: string }[];
    filters: { segments: string[] };
    narrative: string | null;
    aiEnabled: boolean;
}>();

const { t } = useI18n();
const page = usePage();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('Forecasts'), href: '/admin/forecasts/index' },
    { title: t('Demand'), href: '/admin/forecasts/demand' },
]);

const currency = computed(() => page.props.currency as string);
const availableLocales = (page.props.available_locales as string[]) ?? [];

const productName = (name: Record<string, string> | string | null): string => {
    if (!name) return t('Item');
    if (typeof name === 'string') return name;
    for (const locale of availableLocales) if (name[locale]) return name[locale];
    return Object.values(name)[0] || t('Item');
};

const horizons = [7, 14, 30, 60, 90];

const setHorizon = (days: number) =>
    router.get('/admin/forecasts/demand', { horizon: days, segments: props.filters.segments }, { preserveState: true, preserveScroll: true });

const toggleSegment = (value: string) => {
    const next = props.filters.segments.includes(value) ? props.filters.segments.filter((s) => s !== value) : [...props.filters.segments, value];
    router.get('/admin/forecasts/demand', { horizon: props.horizon, segments: next }, { preserveState: true, preserveScroll: true });
};

/*
 * Bottles for one chosen day.
 *
 * Read straight out of `forecast.days`, which the horizon already fetched —
 * picking a date is a lens on data that is on the page, not another request.
 * Dates outside the horizon simply have no row; the input is clamped to the
 * window rather than silently showing zero.
 */
const selectedDate = ref(props.forecast.from);

const selectedDay = computed(() => props.forecast.days.find((d) => d.date === selectedDate.value) ?? null);

// Narrowing the horizon can strand the chosen date outside it; snap back
// rather than leaving the card empty.
watch(
    () => [props.forecast.from, props.forecast.to],
    () => {
        if (selectedDate.value < props.forecast.from || selectedDate.value > props.forecast.to) {
            selectedDate.value = props.forecast.from;
        }
    },
);

const dateLabel = computed(() =>
    new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'long', weekday: 'long' }),
);

/** Widest segment bar, for scaling the inline share bars. */
const maxSegmentUnits = computed(() => Math.max(1, ...props.forecast.segments.map((s) => s.units)));

/**
 * How wide the range is relative to the forecast. This is the number that
 * says whether the forecast is precise enough to buy against.
 */
const bandWidthPct = computed(() => {
    const { units, units_p10, units_p90 } = props.forecast.totals;
    return units > 0 ? Math.round(((units_p90 - units_p10) / units) * 100) : 0;
});

const shortfalls = computed(() => props.procurement.materials.filter((m) => m.shortfall > 0));
const unmeasuredNames = computed(() => Object.values(props.unmeasured));

const coverClass = (days: number | null) => {
    if (days === null) return 'text-muted-foreground';
    if (days <= 3) return 'text-red-600 dark:text-red-400 font-semibold';
    if (days <= 7) return 'text-amber-600 dark:text-amber-400 font-medium';
    return 'text-foreground';
};
</script>

<template>
    <Head :title="t('Demand forecast')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto space-y-4 px-4 md:space-y-6 md:px-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">{{ t('Demand forecast') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Expected orders and bottle volume, by day, segment and product.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link href="/admin/forecasts/routes">
                        <Button variant="outline" size="sm"><RouteIcon class="mr-1.5 h-4 w-4" />{{ t('Route plan') }}</Button>
                    </Link>
                    <Link href="/admin/forecasts/accuracy">
                        <Button variant="outline" size="sm"><Target class="mr-1.5 h-4 w-4" />{{ t('Accuracy') }}</Button>
                    </Link>
                </div>
            </div>

            <!--
        The most important caveat on the page. While the business has less than
        a full seasonal cycle of history, these curves are assumptions, and
        saying so is more useful than a confident number nobody can audit.
      -->
            <Card v-if="!forecast.seasonality.learning" class="border-amber-300 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/30">
                <CardContent class="flex items-start gap-3 p-4">
                    <Info class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="text-sm">
                        <div class="font-semibold text-amber-900 dark:text-amber-200">{{ t('Seasonality is running on defaults') }}</div>
                        <p class="mt-1 text-amber-800 dark:text-amber-300">
                            {{ t('There are') }} {{ forecast.seasonality.months_of_history }}
                            {{ t('months of order history; measuring the real seasonal pattern needs') }}
                            {{ forecast.seasonality.months_required }}.
                            {{ t('Until then the forecast uses the built-in curve for each segment — check it matches what you see.') }}
                        </p>
                        <Link
                            href="/admin/forecasts/seasonality"
                            class="mt-2 inline-block text-sm font-medium text-amber-900 underline dark:text-amber-200"
                        >
                            {{ t('Review seasonality curves') }}
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!--
        Bottles for a single day. The headline cards below cover the whole
        horizon; this answers the only question the warehouse asks in the
        morning — how many go out today.
      -->
            <Card class="border-sky-200 shadow-sm dark:border-sky-900/60">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <label for="forecast-date" class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ t('Bottles to sell on') }}
                        </label>
                        <input
                            id="forecast-date"
                            v-model="selectedDate"
                            type="date"
                            :min="forecast.from"
                            :max="forecast.to"
                            class="mt-1 block rounded-md border border-border bg-background px-2.5 py-1.5 text-sm text-foreground"
                        />
                    </div>

                    <div v-if="selectedDay" class="text-right">
                        <div class="text-4xl font-bold text-foreground">{{ Math.round(selectedDay.units).toLocaleString() }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ dateLabel }} · {{ Math.round(selectedDay.units_p10).toLocaleString() }} –
                            {{ Math.round(selectedDay.units_p90).toLocaleString() }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ Math.round(selectedDay.committed_units).toLocaleString() }} {{ t('already ordered') }}
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        {{ t('Pick a date inside the forecast window, or widen the horizon.') }}
                    </div>
                </CardContent>
            </Card>

            <!-- horizon + segment filters -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Horizon') }}</span>
                <Button v-for="days in horizons" :key="days" :variant="horizon === days ? 'default' : 'outline'" size="sm" @click="setHorizon(days)">
                    {{ days }} {{ t('days') }}
                </Button>
                <span class="ml-2 hidden text-xs text-muted-foreground md:inline">{{ forecast.from }} → {{ forecast.to }}</span>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="segment in segments"
                    :key="segment.value"
                    type="button"
                    class="rounded-full border px-2.5 py-1 text-xs transition-colors"
                    :class="
                        filters.segments.includes(segment.value)
                            ? 'border-sky-500 bg-sky-500/10 text-sky-700 dark:text-sky-300'
                            : 'border-border text-muted-foreground hover:border-foreground/30'
                    "
                    @click="toggleSegment(segment.value)"
                >
                    {{ t(segment.label) }}
                </button>
            </div>

            <!-- headline numbers -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Expected units') }}</div>
                        <div class="text-2xl font-bold text-foreground">{{ forecast.totals.units.toLocaleString() }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ forecast.totals.units_p10.toLocaleString() }} – {{ forecast.totals.units_p90.toLocaleString() }}
                            <span class="ml-1">(±{{ Math.round(bandWidthPct / 2) }}%)</span>
                        </div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Expected orders') }}</div>
                        <div class="text-2xl font-bold text-foreground">{{ forecast.totals.orders.toLocaleString() }}</div>
                        <div class="text-xs text-muted-foreground">{{ forecast.totals.committed_orders }} {{ t('already placed') }}</div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Expected revenue') }}</div>
                        <div class="text-2xl font-bold text-foreground">
                            {{ Math.round(forecast.totals.revenue).toLocaleString() }}
                            <span class="text-sm font-normal text-muted-foreground">{{ currency }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('To purchase') }}</div>
                        <div class="text-2xl font-bold text-foreground">
                            {{ Math.round(procurement.total_purchase).toLocaleString() }}
                            <span class="text-sm font-normal text-muted-foreground">{{ currency }}</span>
                        </div>
                        <div class="text-xs text-muted-foreground">{{ shortfalls.length }} {{ t('materials short') }}</div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="narrative" class="shadow-sm">
                <CardContent class="flex items-start gap-3 p-4">
                    <Sparkles class="mt-0.5 h-4 w-4 shrink-0 text-sky-500" />
                    <div>
                        <p class="text-sm leading-relaxed text-foreground">{{ narrative }}</p>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{ t('Written by AI from the figures above. The figures themselves are calculated, not generated.') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="shadow-sm">
                <CardContent class="p-4">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('Units per day') }}</h2>
                    <DemandChart :days="forecast.days" />
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- by segment -->
                <Card class="shadow-sm">
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('By segment') }}</h2>
                        <div v-if="forecast.segments.length" class="space-y-2.5">
                            <div v-for="row in forecast.segments" :key="row.segment">
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-foreground">{{ t(row.label) }}</span>
                                    <span class="text-muted-foreground">
                                        <span class="font-medium text-foreground">{{ row.units.toLocaleString() }}</span>
                                        · {{ row.clients }} {{ t('clients') }}
                                    </span>
                                </div>
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-sky-500" :style="{ width: `${(row.units / maxSegmentUnits) * 100}%` }"></div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="py-6 text-center text-sm text-muted-foreground">{{ t('No demand predicted for this period.') }}</p>
                    </CardContent>
                </Card>

                <!-- by product -->
                <Card class="shadow-sm">
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('By product') }}</h2>
                        <table v-if="forecast.products.length" class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase">
                                    <th class="pb-2 text-left font-semibold">{{ t('Product') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Units') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="product in forecast.products" :key="product.product_id">
                                    <td class="py-2 text-foreground">{{ productName(product.name) }}</td>
                                    <td class="py-2 text-right font-medium text-foreground">{{ product.units.toLocaleString() }}</td>
                                    <td class="py-2 text-right text-muted-foreground">{{ Math.round(product.revenue).toLocaleString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-6 text-center text-sm text-muted-foreground">{{ t('No demand predicted for this period.') }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- procurement -->
            <Card class="shadow-sm">
                <CardContent class="p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <PackageSearch class="h-4 w-4" />{{ t('What to buy') }}
                        </h2>
                        <span v-if="procurement.deposit_at_risk > 0" class="text-xs text-muted-foreground">
                            {{ t('Deposit at risk') }}: {{ Math.round(procurement.deposit_at_risk).toLocaleString() }} {{ currency }}
                        </span>
                    </div>

                    <div
                        v-if="unmeasuredNames.length"
                        class="mb-3 flex items-start gap-2 rounded-md border border-border bg-muted/40 p-2.5 text-xs text-muted-foreground"
                    >
                        <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>
                            {{ t('No return history yet for') }}: {{ unmeasuredNames.join(', ') }}.
                            {{ t('Their replacement figures assume nothing comes back, so they are a worst case rather than a measurement.') }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table v-if="procurement.materials.length" class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase">
                                    <th class="pb-2 text-left font-semibold">{{ t('Material') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Needed') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Return rate') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('In stock') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Cover') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Shortfall') }}</th>
                                    <th class="pb-2 text-right font-semibold">{{ t('Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="material in procurement.materials" :key="material.id">
                                    <td class="py-2">
                                        <div class="text-foreground">{{ material.name }}</div>
                                        <div v-if="material.is_reusable" class="text-xs text-muted-foreground">
                                            {{ t('Reusable') }} · {{ material.circulating?.toLocaleString() }} {{ t('in circulation') }}
                                        </div>
                                    </td>
                                    <td class="py-2 text-right font-medium text-foreground">
                                        {{ material.net_required.toLocaleString() }}
                                        <span class="text-xs font-normal text-muted-foreground">{{ material.unit }}</span>
                                    </td>
                                    <td class="py-2 text-right text-muted-foreground">
                                        <span v-if="material.return_rate !== null">{{ Math.round(material.return_rate * 100) }}%</span>
                                        <span v-else-if="material.is_reusable" class="text-amber-600 dark:text-amber-400">{{ t('unknown') }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="py-2 text-right text-muted-foreground">{{ material.current_stock.toLocaleString() }}</td>
                                    <td class="py-2 text-right" :class="coverClass(material.days_of_cover)">
                                        <span v-if="material.days_of_cover !== null">{{ material.days_of_cover }} {{ t('d') }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td
                                        class="py-2 text-right"
                                        :class="material.shortfall > 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-muted-foreground'"
                                    >
                                        {{ material.shortfall.toLocaleString() }}
                                    </td>
                                    <td class="py-2 text-right text-foreground">
                                        {{ material.purchase_cost > 0 ? Math.round(material.purchase_cost).toLocaleString() : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-6 text-center text-sm text-muted-foreground">
                            {{ t('No bill of materials linked to the forecast products yet.') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- model transparency -->
            <Card class="shadow-sm">
                <CardContent class="grid grid-cols-2 gap-4 p-4 text-sm md:grid-cols-5">
                    <div>
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Clients modelled') }}</div>
                        <div class="mt-1 font-semibold text-foreground">{{ forecast.model.clients_modelled }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('On subscription') }}</div>
                        <div class="mt-1 font-semibold text-foreground">{{ forecast.model.clients_subscribed }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Gone quiet') }}</div>
                        <div class="mt-1 font-semibold text-foreground">{{ forecast.model.clients_churned }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Self-correction') }}</div>
                        <div class="mt-1 font-semibold text-foreground">×{{ forecast.bias_factor }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Recent accuracy') }}</div>
                        <div class="mt-1 font-semibold text-foreground">
                            <span v-if="accuracy.observations > 0">{{ accuracy.accuracy_pct }}%</span>
                            <Badge v-else variant="secondary">{{ t('not scored yet') }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
