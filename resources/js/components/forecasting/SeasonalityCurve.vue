<script setup lang="ts">
/**
 * A segment's 12-month demand curve, with the prior drawn behind it.
 *
 * Showing both matters: the gap between the two lines is exactly "how much of
 * this curve did we measure versus assume", which is the question a manager
 * should ask before trusting a summer forecast.
 */
import { computed } from 'vue';

interface MonthPoint {
    month: number;
    index: number;
    prior: number;
    source: string;
    sample_size: number;
}

const props = withDefaults(defineProps<{ months: MonthPoint[]; height?: number; showPrior?: boolean }>(), {
    height: 90,
    showPrior: true,
});

const width = 360;
const pad = { top: 8, right: 8, bottom: 16, left: 8 };

const maxY = computed(() => Math.max(1.6, ...props.months.map((m) => Math.max(m.index, m.prior))) * 1.05);

const x = (i: number) => pad.left + (i / 11) * (width - pad.left - pad.right);
const y = (v: number) => pad.top + (props.height - pad.top - pad.bottom) * (1 - v / maxY.value);

const path = (key: 'index' | 'prior') => props.months.map((m, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(m[key])}`).join(' ');

const baseline = computed(() => y(1));
const labels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
</script>

<template>
    <svg :viewBox="`0 0 ${width} ${height}`" class="h-auto w-full">
        <!-- the 1.0 line: a segment's own yearly average -->
        <line :x1="pad.left" :x2="width - pad.right" :y1="baseline" :y2="baseline" class="stroke-border" stroke-width="1" stroke-dasharray="3 3" />

        <path v-if="showPrior" :d="path('prior')" fill="none" class="stroke-muted-foreground/40" stroke-width="1.5" stroke-dasharray="4 3" />
        <path :d="path('index')" fill="none" class="stroke-sky-600 dark:stroke-sky-400" stroke-width="2" />

        <circle
            v-for="(m, i) in months"
            :key="m.month"
            :cx="x(i)"
            :cy="y(m.index)"
            :r="m.source === 'manual' ? 3.5 : 2.5"
            :class="m.source === 'manual' ? 'fill-amber-500' : m.source === 'prior' ? 'fill-muted-foreground/50' : 'fill-sky-600 dark:fill-sky-400'"
        >
            <title>{{ labels[i] }}: {{ m.index.toFixed(2) }} ({{ m.source }}, n={{ m.sample_size }})</title>
        </circle>

        <g class="fill-muted-foreground" font-size="8">
            <text v-for="(label, i) in labels" :key="`l-${i}`" :x="x(i)" :y="height - 4" text-anchor="middle">{{ label }}</text>
        </g>
    </svg>
</template>
