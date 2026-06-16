<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronLeft, ChevronRight, CalendarClock, AlertTriangle, Users2 } from 'lucide-vue-next';

interface BasketItem {
  name: Record<string, string> | string | null;
  qty: number;
}

interface Prediction {
  client_id: number;
  client_name: string;
  date: string; // YYYY-MM-DD
  overdue: boolean;
  confidence: 'high' | 'medium' | 'low';
  last_order: string;
  cadence_days: number;
  order_count: number;
  basket: BasketItem[];
}

const props = defineProps<{
  month: string; // YYYY-MM
  predictions: Prediction[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Forecasts', href: '/admin/forecasts/index' },
];

const availableLocales = (usePage().props.available_locales as string[]) ?? [];

const productName = (name: Record<string, string> | string | null): string => {
  if (!name) return 'Item';
  if (typeof name === 'string') return name;
  for (const locale of availableLocales) {
    if (name[locale]) return name[locale];
  }
  return Object.values(name)[0] || 'Item';
};

// Predictions bucketed by their YYYY-MM-DD date string.
const byDate = computed<Record<string, Prediction[]>>(() => {
  const map: Record<string, Prediction[]> = {};
  for (const p of props.predictions) {
    (map[p.date] ??= []).push(p);
  }
  return map;
});

const [year, monthIndex] = props.month.split('-').map(Number); // monthIndex is 1-based
const todayStr = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD, local

const pad = (n: number) => String(n).padStart(2, '0');
const dateStr = (day: number) => `${year}-${pad(monthIndex)}-${pad(day)}`;

const monthLabel = computed(() =>
  new Date(year, monthIndex - 1, 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
);

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Calendar cells: leading blanks (Mon-first) then each day of the month.
const cells = computed<(number | null)[]>(() => {
  const firstWeekday = (new Date(year, monthIndex - 1, 1).getDay() + 6) % 7; // 0 = Mon
  const daysInMonth = new Date(year, monthIndex, 0).getDate();
  const out: (number | null)[] = Array(firstWeekday).fill(null);
  for (let d = 1; d <= daysInMonth; d++) out.push(d);
  while (out.length % 7 !== 0) out.push(null);
  return out;
});

const dayHasOverdue = (day: number) => (byDate.value[dateStr(day)] ?? []).some((p) => p.overdue);

// Default selection: today if it falls in this month and has predictions,
// otherwise the first day of the month that has any.
const firstPopulatedDay = (): string | null => {
  for (const cell of cells.value) {
    if (cell !== null && byDate.value[dateStr(cell)]?.length) return dateStr(cell);
  }
  return null;
};
const selectedDate = ref<string | null>(
  byDate.value[todayStr]?.length ? todayStr : firstPopulatedDay(),
);

const selectedPredictions = computed<Prediction[]>(() =>
  selectedDate.value ? (byDate.value[selectedDate.value] ?? []) : [],
);

const selectedLabel = computed(() =>
  selectedDate.value
    ? new Date(selectedDate.value).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : null,
);

const selectDay = (day: number) => {
  selectedDate.value = dateStr(day);
};

const shiftMonth = (delta: number) => {
  const d = new Date(year, monthIndex - 1 + delta, 1);
  const target = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
  router.get('/admin/forecasts/index', { month: target }, { preserveState: true, preserveScroll: true });
};

const confidenceClass: Record<string, string> = {
  high: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400',
  medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400',
  low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};

const basketSummary = (basket: BasketItem[]): string =>
  basket.length ? basket.map((b) => `${productName(b.name)} ×${b.qty}`).join(', ') : '—';
</script>

<template>
  <Head title="Forecasts" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-4 md:space-y-6 container mx-auto px-4 md:px-0">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">Order Forecasts</h1>
          <p class="text-sm text-muted-foreground mt-1">
            Probable next orders for repeat clients. Pick a day to see who's likely to order and what.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="icon" class="h-10 w-10" @click="shiftMonth(-1)" title="Previous month">
            <ChevronLeft class="h-4 w-4" />
          </Button>
          <span class="min-w-[10rem] text-center font-bold text-lg">{{ monthLabel }}</span>
          <Button variant="outline" size="icon" class="h-10 w-10" @click="shiftMonth(1)" title="Next month">
            <ChevronRight class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <!-- Calendar grid -->
      <Card class="shadow-sm">
        <CardContent class="p-3 md:p-4">
          <div class="grid grid-cols-7 gap-1 md:gap-2">
            <div
              v-for="label in weekdayLabels"
              :key="label"
              class="text-center text-[10px] md:text-xs uppercase tracking-wider text-muted-foreground font-bold py-1"
            >
              {{ label }}
            </div>

            <template v-for="(cell, idx) in cells" :key="idx">
              <div v-if="cell === null" class="min-h-[3rem] md:min-h-[3.5rem]" />
              <button
                v-else
                type="button"
                @click="selectDay(cell)"
                class="min-h-[3rem] md:min-h-[3.5rem] rounded-lg border p-1 md:p-2 flex flex-col items-start justify-between text-left transition-colors hover:bg-muted/50"
                :class="[
                  selectedDate === dateStr(cell) ? 'ring-2 ring-primary border-primary' : 'border-border/60',
                  dayHasOverdue(cell) ? 'bg-amber-50 dark:bg-amber-900/20' : '',
                  dateStr(cell) === todayStr ? 'font-extrabold' : '',
                ]"
              >
                <span
                  class="text-xs md:text-sm"
                  :class="dateStr(cell) === todayStr ? 'text-primary' : 'text-foreground'"
                >{{ cell }}</span>
                <Badge
                  v-if="byDate[dateStr(cell)]?.length"
                  variant="outline"
                  class="border-transparent text-[10px] px-1.5 h-5 self-end font-semibold"
                  :class="dayHasOverdue(cell)
                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'"
                >
                  {{ byDate[dateStr(cell)].length }}
                </Badge>
              </button>
            </template>
          </div>
        </CardContent>
      </Card>

      <!-- Selected day -->
      <Card class="shadow-sm">
        <CardContent class="p-0">
          <div class="p-4 border-b bg-gray-50/50 dark:bg-gray-800/30 flex items-center gap-2">
            <CalendarClock class="h-4 w-4 text-muted-foreground" />
            <span class="font-bold text-foreground">{{ selectedLabel ?? 'Select a day' }}</span>
            <span v-if="selectedPredictions.length" class="text-sm text-muted-foreground">
              · {{ selectedPredictions.length }} client{{ selectedPredictions.length === 1 ? '' : 's' }}
            </span>
          </div>

          <!-- Desktop table -->
          <div v-if="selectedPredictions.length" class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                <tr>
                  <th class="px-6 py-3 font-semibold">Client</th>
                  <th class="px-6 py-3 font-semibold">Probable items</th>
                  <th class="px-6 py-3 font-semibold">Confidence</th>
                  <th class="px-6 py-3 font-semibold">History</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                <tr v-for="p in selectedPredictions" :key="p.client_id" class="hover:bg-muted/40 transition-colors">
                  <td class="px-6 py-4">
                    <div class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                      {{ p.client_name }}
                      <Badge
                        v-if="p.overdue"
                        variant="outline"
                        class="border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[10px] px-1.5 h-5 font-semibold gap-1"
                      >
                        <AlertTriangle class="h-3 w-3" /> Overdue
                      </Badge>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                    <div v-if="p.basket.length" class="space-y-0.5">
                      <div v-for="(b, i) in p.basket" :key="i">
                        {{ productName(b.name) }} <span class="text-muted-foreground">×{{ b.qty }}</span>
                      </div>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                  </td>
                  <td class="px-6 py-4">
                    <Badge variant="outline" class="border-transparent capitalize font-semibold" :class="confidenceClass[p.confidence]">
                      {{ p.confidence }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4 text-xs text-muted-foreground">
                    {{ p.order_count }} orders · ~every {{ p.cadence_days }}d<br />
                    last {{ new Date(p.last_order).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile cards -->
          <div v-if="selectedPredictions.length" class="md:hidden divide-y divide-border/60">
            <div v-for="p in selectedPredictions" :key="p.client_id" class="p-4">
              <div class="flex items-start justify-between mb-2">
                <span class="font-bold text-gray-900 dark:text-white">{{ p.client_name }}</span>
                <Badge variant="outline" class="border-transparent capitalize font-semibold text-[10px] h-5 px-1.5" :class="confidenceClass[p.confidence]">
                  {{ p.confidence }}
                </Badge>
              </div>
              <Badge
                v-if="p.overdue"
                variant="outline"
                class="border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[10px] px-1.5 h-5 font-semibold gap-1 mb-2"
              >
                <AlertTriangle class="h-3 w-3" /> Overdue
              </Badge>
              <div class="text-sm text-gray-700 dark:text-gray-300">{{ basketSummary(p.basket) }}</div>
              <div class="text-[10px] text-muted-foreground mt-1">
                {{ p.order_count }} orders · ~every {{ p.cadence_days }}d · last
                {{ new Date(p.last_order).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) }}
              </div>
            </div>
          </div>

          <!-- Empty -->
          <div v-if="!selectedPredictions.length" class="px-6 py-12 text-center text-muted-foreground">
            <div class="flex flex-col items-center justify-center opacity-60">
              <Users2 class="h-10 w-10 mb-3 text-gray-400" />
              <p class="font-medium text-sm">No clients predicted to order on this day.</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
