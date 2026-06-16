<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
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

const pad = (n: number) => String(n).padStart(2, '0');

// Derive the displayed month reactively from props so router navigation
// (preserveState) updates everything without re-creating the component.
const year = computed(() => Number(props.month.split('-')[0]));
const monthIndex = computed(() => Number(props.month.split('-')[1])); // 1-based
const dateStr = (day: number) => `${year.value}-${pad(monthIndex.value)}-${pad(day)}`;
const todayStr = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD, local

const monthLabel = computed(() =>
  new Date(year.value, monthIndex.value - 1, 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
);

// Predictions bucketed by their YYYY-MM-DD date string.
const byDate = computed<Record<string, Prediction[]>>(() => {
  const map: Record<string, Prediction[]> = {};
  for (const p of props.predictions) {
    (map[p.date] ??= []).push(p);
  }
  return map;
});

// Month dropdown: a rolling window starting one month before the current
// month, always including whichever month is currently displayed.
const monthOptions = computed(() => {
  const opts: { value: string; label: string }[] = [];
  const base = new Date();
  const start = new Date(base.getFullYear(), base.getMonth() - 1, 1);
  for (let i = 0; i < 15; i++) {
    const d = new Date(start.getFullYear(), start.getMonth() + i, 1);
    opts.push({
      value: `${d.getFullYear()}-${pad(d.getMonth() + 1)}`,
      label: d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
    });
  }
  if (!opts.some((o) => o.value === props.month)) {
    opts.push({ value: props.month, label: monthLabel.value });
  }
  return opts.sort((a, b) => a.value.localeCompare(b.value));
});

// Day dropdown: only days that actually have predicted orders this month.
const dayOptions = computed(() =>
  Object.keys(byDate.value)
    .sort()
    .map((ds) => ({
      value: ds,
      label: new Date(ds).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }),
      count: byDate.value[ds].length,
      overdue: byDate.value[ds].some((p) => p.overdue),
    })),
);

// Default selection: today if it falls in this month and has predictions,
// otherwise the first populated day. Re-evaluated whenever the month changes.
const defaultDay = (): string | null => {
  if (byDate.value[todayStr]?.length) return todayStr;
  return dayOptions.value[0]?.value ?? null;
};

const selectedDate = ref<string | null>(null);
watch(() => props.month, () => { selectedDate.value = defaultDay(); }, { immediate: true });

const selectedPredictions = computed<Prediction[]>(() =>
  selectedDate.value ? (byDate.value[selectedDate.value] ?? []) : [],
);

const selectedLabel = computed(() =>
  selectedDate.value
    ? new Date(selectedDate.value).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : null,
);

const goToMonth = (value: string) => {
  router.get('/admin/forecasts/index', { month: value }, { preserveState: true, preserveScroll: true });
};

const shiftMonth = (delta: number) => {
  const d = new Date(year.value, monthIndex.value - 1 + delta, 1);
  goToMonth(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
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
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">Order Forecasts</h1>
        <p class="text-sm text-muted-foreground mt-1">
          Probable next orders for repeat clients. Pick a month and day to see who's likely to order and what.
        </p>
      </div>

      <!-- Toolbar: month + day selectors with prev/next -->
      <Card class="shadow-sm">
        <CardContent class="p-4 grid grid-cols-1 md:flex md:flex-wrap md:items-end gap-3">
          <div class="space-y-1 w-full md:w-56">
            <Label class="text-xs uppercase tracking-wider text-muted-foreground">Month</Label>
            <div class="flex items-center gap-2">
              <Button variant="outline" size="icon" class="h-10 md:h-9 w-10 md:w-9 shrink-0" @click="shiftMonth(-1)" title="Previous month">
                <ChevronLeft class="h-4 w-4" />
              </Button>
              <select
                :value="props.month"
                @change="goToMonth(($event.target as HTMLSelectElement).value)"
                class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              >
                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
              <Button variant="outline" size="icon" class="h-10 md:h-9 w-10 md:w-9 shrink-0" @click="shiftMonth(1)" title="Next month">
                <ChevronRight class="h-4 w-4" />
              </Button>
            </div>
          </div>

          <div class="space-y-1 w-full md:w-64">
            <Label class="text-xs uppercase tracking-wider text-muted-foreground">Day</Label>
            <select
              v-model="selectedDate"
              :disabled="!dayOptions.length"
              class="flex h-10 md:h-9 w-full rounded-md border border-input bg-white dark:bg-gray-900 px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
            >
              <option v-if="!dayOptions.length" :value="null">No predicted days this month</option>
              <option v-for="d in dayOptions" :key="d.value" :value="d.value">
                {{ d.label }} — {{ d.count }} client{{ d.count === 1 ? '' : 's' }}{{ d.overdue ? ' • overdue' : '' }}
              </option>
            </select>
          </div>
        </CardContent>
      </Card>

      <!-- Forecast table -->
      <Card class="shadow-sm">
        <CardContent class="p-0">
          <div class="p-4 border-b bg-gray-50/50 dark:bg-gray-800/30 flex items-center gap-2">
            <CalendarClock class="h-4 w-4 text-muted-foreground" />
            <span class="font-bold text-foreground">{{ selectedLabel ?? 'No day selected' }}</span>
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
              <p class="font-medium text-sm">
                {{ dayOptions.length ? 'No clients predicted to order on this day.' : 'No clients predicted to order this month.' }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
