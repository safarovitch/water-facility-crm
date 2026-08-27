<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import NumberField from '@/components/NumberField.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ChevronLeft, ChevronRight, MapPin, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Stop {
    type: 'committed' | 'predicted';
    order_id: number | null;
    client_id: number;
    client_name: string;
    address: string | null;
    units: number;
    probability: number;
    sequence?: number;
}

interface RouteRow {
    number: number;
    stops: Stop[];
    stop_count: number;
    units: number;
    load_pct: number;
    distance_km: number;
    duration_min: number;
    predicted_stops: number;
}

const props = defineProps<{
    date: string;
    plan: {
        date: string;
        routes: RouteRow[];
        unlocated: Stop[];
        summary: {
            stops: number;
            committed_stops: number;
            predicted_stops: number;
            unlocated_stops: number;
            units: number;
            routes: number;
            vehicle_capacity: number;
            avg_load_pct: number;
            total_distance_km: number;
            geocoded_pct: number;
        };
        settings: { capacity: number; max_stops: number; min_probability: number };
    };
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('Forecasts'), href: '/admin/forecasts/index' },
    { title: t('Route plan'), href: '/admin/forecasts/routes' },
]);

const capacity = ref(props.plan.settings.capacity);
const minProbability = ref(props.plan.settings.min_probability);

const reload = (params: Record<string, string | number>) =>
    router.get(
        '/admin/forecasts/routes',
        { date: props.date, capacity: capacity.value, min_probability: minProbability.value, ...params },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );

const shiftDay = (delta: number) => {
    const d = new Date(props.date);
    d.setDate(d.getDate() + delta);
    reload({ date: d.toLocaleDateString('en-CA') });
};

const dateLabel = computed(() => new Date(props.date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }));

const loadClass = (pct: number) => {
    if (pct >= 85) return 'text-emerald-600 dark:text-emerald-400';
    if (pct >= 60) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
};

const expanded = ref<number | null>(null);
const toggle = (n: number) => (expanded.value = expanded.value === n ? null : n);
</script>

<template>
    <Head :title="t('Route plan')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto space-y-4 px-4 md:space-y-6 md:px-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">{{ t('Route plan') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Confirmed orders plus confident predictions, grouped into vehicle runs.') }}
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <Button variant="outline" size="icon" @click="shiftDay(-1)"><ChevronLeft class="h-4 w-4" /></Button>
                    <span class="min-w-[180px] text-center text-sm font-medium text-foreground">{{ dateLabel }}</span>
                    <Button variant="outline" size="icon" @click="shiftDay(1)"><ChevronRight class="h-4 w-4" /></Button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Runs needed') }}</div>
                        <div class="text-2xl font-bold text-foreground">{{ plan.summary.routes }}</div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Stops') }}</div>
                        <div class="text-2xl font-bold text-foreground">{{ plan.summary.stops }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ plan.summary.committed_stops }} {{ t('confirmed') }} · {{ plan.summary.predicted_stops }} {{ t('predicted') }}
                        </div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Units') }}</div>
                        <div class="text-2xl font-bold text-foreground">{{ plan.summary.units.toLocaleString() }}</div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <!--
              The number that decides cost per delivery: two half-empty runs
              cost the same fuel and driver-day as one full one.
            -->
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Average load') }}</div>
                        <div class="text-2xl font-bold" :class="loadClass(plan.summary.avg_load_pct)">{{ plan.summary.avg_load_pct }}%</div>
                    </CardContent>
                </Card>
                <Card class="shadow-sm">
                    <CardContent class="space-y-1 p-4">
                        <div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{{ t('Distance') }}</div>
                        <div class="text-2xl font-bold text-foreground">
                            {{ plan.summary.total_distance_km }} <span class="text-sm font-normal text-muted-foreground">km</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {{ t('Vehicle capacity') }}
                    </label>
                    <NumberField v-model="capacity" :min="1" class="w-40" @keyup.enter="reload({})" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {{ t('Min. confidence') }}
                    </label>
                    <NumberField v-model="minProbability" :min="0" :max="1" :step="0.05" class="w-40" @keyup.enter="reload({})" />
                </div>
                <Button size="sm" @click="reload({})">{{ t('Recalculate') }}</Button>
            </div>

            <Card
                v-if="plan.summary.unlocated_stops > 0"
                class="border-amber-300 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30"
            >
                <CardContent class="p-4">
                    <div class="flex items-start gap-3 text-sm">
                        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <div class="font-semibold text-amber-900 dark:text-amber-200">
                                {{ plan.summary.unlocated_stops }} {{ t('stops have no coordinates and could not be routed') }}
                            </div>
                            <p class="mt-1 text-amber-800 dark:text-amber-300">
                                {{
                                    t(
                                        "They still count toward the day's volume, but a courier has to be given them by hand. Adding a location to these clients' addresses is the single biggest improvement available to this plan.",
                                    )
                                }}
                            </p>
                            <ul class="mt-2 space-y-0.5 text-xs text-amber-800 dark:text-amber-300">
                                <li v-for="stop in plan.unlocated" :key="`${stop.client_id}-${stop.order_id}`">
                                    {{ stop.client_name }} — {{ stop.units }} {{ t('units') }}
                                    <span v-if="stop.address"> · {{ stop.address }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div v-if="plan.routes.length" class="space-y-3">
                <Card v-for="route in plan.routes" :key="route.number" class="shadow-sm">
                    <CardContent class="p-4">
                        <button
                            type="button"
                            class="flex w-full flex-wrap items-center justify-between gap-3 text-left"
                            @click="toggle(route.number)"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400">
                                    <Truck class="h-4 w-4" />
                                </div>
                                <div>
                                    <div class="font-semibold text-foreground">{{ t('Run') }} {{ route.number }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ route.stop_count }} {{ t('stops') }} · {{ route.distance_km }} km · ~{{
                                            Math.round((route.duration_min / 60) * 10) / 10
                                        }}
                                        {{ t('h') }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-foreground">{{ route.units }} / {{ plan.summary.vehicle_capacity }}</div>
                                    <div class="text-xs" :class="loadClass(route.load_pct)">{{ route.load_pct }}% {{ t('loaded') }}</div>
                                </div>
                                <Badge v-if="route.predicted_stops > 0" variant="secondary"> {{ route.predicted_stops }} {{ t('predicted') }} </Badge>
                            </div>
                        </button>

                        <div v-if="expanded === route.number" class="mt-3 border-t border-border pt-3">
                            <ol class="space-y-1.5">
                                <li v-for="stop in route.stops" :key="`${stop.client_id}-${stop.order_id}`" class="flex items-start gap-3 text-sm">
                                    <span
                                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground"
                                    >
                                        {{ stop.sequence }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-foreground">{{ stop.client_name }}</span>
                                            <Badge v-if="stop.type === 'predicted'" variant="outline" class="text-[10px]">
                                                {{ t('predicted') }} {{ Math.round(stop.probability * 100) }}%
                                            </Badge>
                                            <Link
                                                v-if="stop.order_id"
                                                :href="`/admin/orders/${stop.order_id}`"
                                                class="text-xs text-sky-600 underline dark:text-sky-400"
                                            >
                                                #{{ stop.order_id }}
                                            </Link>
                                        </div>
                                        <div v-if="stop.address" class="flex items-center gap-1 text-xs text-muted-foreground">
                                            <MapPin class="h-3 w-3 shrink-0" />{{ stop.address }}
                                        </div>
                                    </div>
                                    <span class="shrink-0 text-sm font-medium text-foreground">{{ stop.units }}</span>
                                </li>
                            </ol>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-else-if="plan.summary.unlocated_stops === 0" class="shadow-sm">
                <CardContent class="p-8 text-center text-sm text-muted-foreground">
                    {{ t('Nothing to deliver on this date.') }}
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
