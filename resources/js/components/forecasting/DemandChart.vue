<script setup lang="ts">
/**
 * Daily demand with its uncertainty band, drawn as inline SVG.
 *
 * No chart library: this repo has none, and pulling one in for two charts
 * would be the largest dependency in package.json (see XlsxWriter for the
 * same call made server-side).
 *
 * The band is the point of the chart. A bare line invites staff to stock to
 * the mean, which by definition runs out half the time; showing P10-P90
 * makes the planning decision — how much cover to buy — visible.
 */
import { useI18n } from '@/composables/useI18n';
import { computed, ref } from 'vue';

interface Day {
    date: string;
    units: number;
    units_p10: number;
    units_p90: number;
    committed_units: number;
    predicted_units: number;
    orders: number;
}

const props = withDefaults(defineProps<{ days: Day[]; height?: number }>(), { height: 220 });

const { t } = useI18n();

const width = 880;
const pad = { top: 16, right: 16, bottom: 28, left: 44 };

const hovered = ref<number | null>(null);

const innerWidth = computed(() => width - pad.left - pad.right);
const innerHeight = computed(() => props.height - pad.top - pad.bottom);

const maxY = computed(() => {
    const peak = Math.max(1, ...props.days.map((d) => d.units_p90));
    // Round the ceiling up to something a human reads easily, so the axis
    // labels land on round numbers rather than on 137.4.
    const step = Math.pow(10, Math.floor(Math.log10(peak))) / 2;
    return Math.ceil(peak / step) * step;
});

const x = (i: number) => {
    if (props.days.length <= 1) return pad.left + innerWidth.value / 2;
    return pad.left + (i / (props.days.length - 1)) * innerWidth.value;
};

const y = (value: number) => pad.top + innerHeight.value - (value / maxY.value) * innerHeight.value;

/** Upper edge left-to-right, then lower edge right-to-left: one closed band. */
const bandPath = computed(() => {
    if (!props.days.length) return '';
    const upper = props.days.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(d.units_p90)}`).join(' ');
    const lower = props.days.map((d, i) => `L${x(props.days.length - 1 - i)},${y(props.days[props.days.length - 1 - i].units_p10)}`).join(' ');
    return `${upper} ${lower} Z`;
});

const linePath = computed(() => props.days.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(d.units)}`).join(' '));

const committedPath = computed(() => props.days.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(d.committed_units)}`).join(' '));

const ticks = computed(() => {
    const count = 4;
    return Array.from({ length: count + 1 }, (_, i) => (maxY.value / count) * i);
});

/** Roughly six x labels regardless of horizon length. */
const xLabels = computed(() => {
    const step = Math.max(1, Math.ceil(props.days.length / 6));
    return props.days.map((d, i) => ({ i, date: d.date })).filter(({ i }) => i % step === 0);
});

const shortDate = (iso: string) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

const hoveredDay = computed(() => (hovered.value === null ? null : (props.days[hovered.value] ?? null)));
const bandWidth = computed(() => (props.days.length ? innerWidth.value / props.days.length : 0));
</script>

<template>
    <div class="relative">
        <svg :viewBox="`0 0 ${width} ${height}`" class="h-auto w-full" role="img" :aria-label="t('Daily demand forecast')">
            <!-- horizontal gridlines -->
            <g class="text-border">
                <line
                    v-for="tick in ticks"
                    :key="`g-${tick}`"
                    :x1="pad.left"
                    :x2="width - pad.right"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    stroke="currentColor"
                    stroke-width="1"
                    :stroke-dasharray="tick === 0 ? '0' : '3 3'"
                    opacity="0.5"
                />
            </g>

            <!-- y axis labels -->
            <g class="fill-muted-foreground" font-size="10">
                <text v-for="tick in ticks" :key="`t-${tick}`" :x="pad.left - 8" :y="y(tick) + 3" text-anchor="end">
                    {{ Math.round(tick) }}
                </text>
            </g>

            <!-- P10-P90 band -->
            <path :d="bandPath" class="fill-sky-500/15" />

            <!-- committed (already-placed) demand -->
            <path :d="committedPath" fill="none" class="stroke-emerald-500" stroke-width="1.5" stroke-dasharray="4 3" />

            <!-- expected total -->
            <path :d="linePath" fill="none" class="stroke-sky-600 dark:stroke-sky-400" stroke-width="2" />

            <!-- x axis labels -->
            <g class="fill-muted-foreground" font-size="10">
                <text v-for="label in xLabels" :key="label.date" :x="x(label.i)" :y="height - 8" text-anchor="middle">
                    {{ shortDate(label.date) }}
                </text>
            </g>

            <!-- hover marker -->
            <g v-if="hovered !== null && hoveredDay">
                <line
                    :x1="x(hovered)"
                    :x2="x(hovered)"
                    :y1="pad.top"
                    :y2="height - pad.bottom"
                    class="stroke-foreground"
                    stroke-width="1"
                    opacity="0.35"
                />
                <circle :cx="x(hovered)" :cy="y(hoveredDay.units)" r="3.5" class="fill-sky-600 dark:fill-sky-400" />
            </g>

            <!-- transparent hit areas, one per day -->
            <rect
                v-for="(day, i) in days"
                :key="`h-${day.date}`"
                :x="x(i) - bandWidth / 2"
                :y="pad.top"
                :width="bandWidth"
                :height="innerHeight"
                fill="transparent"
                @mouseenter="hovered = i"
                @mouseleave="hovered = null"
            />
        </svg>

        <div
            v-if="hoveredDay"
            class="pointer-events-none absolute top-2 right-2 rounded-md border border-border bg-popover px-3 py-2 text-xs shadow-md"
        >
            <div class="font-semibold text-foreground">{{ shortDate(hoveredDay.date) }}</div>
            <div class="mt-1 space-y-0.5 text-muted-foreground">
                <div>
                    {{ t('Expected') }}: <span class="font-medium text-foreground">{{ hoveredDay.units }}</span>
                </div>
                <div>{{ t('Range') }}: {{ hoveredDay.units_p10 }} – {{ hoveredDay.units_p90 }}</div>
                <div>{{ t('Already ordered') }}: {{ hoveredDay.committed_units }}</div>
                <div>{{ t('Orders') }}: {{ hoveredDay.orders }}</div>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 bg-sky-600 dark:bg-sky-400"></span>{{ t('Expected units') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 bg-sky-500/25"></span>{{ t('P10–P90 range') }}</span>
            <span class="flex items-center gap-1.5"
                ><span class="h-0.5 w-4 border-t-2 border-dashed border-emerald-500"></span>{{ t('Already ordered') }}</span
            >
        </div>
    </div>
</template>
