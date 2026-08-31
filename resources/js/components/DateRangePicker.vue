<script setup lang="ts">
/**
 * A from–to date range picker: one control instead of the two native date
 * inputs it replaces, with a month grid and the presets people actually reach
 * for ("this month", "last 30 days").
 *
 * Hand-rolled on purpose — it matches DeliveryTimePicker.vue and keeps the
 * project free of a calendar dependency. Dates are plain `YYYY-MM-DD` strings
 * throughout, the same shape the query string and the controller's whereDate()
 * expect, so nothing has to parse or re-serialise them.
 */
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(
  defineProps<{
    from: string;
    to: string;
    placeholder?: string;
  }>(),
  { placeholder: '' },
);

const emit = defineEmits<{
  (e: 'update:from', value: string): void;
  (e: 'update:to', value: string): void;
  (e: 'change'): void;
}>();

const { t, locale } = useI18n();

const open = ref(false);
const hovered = ref('');

const pad = (n: number) => String(n).padStart(2, '0');

/** Local-time ISO date. `toISOString()` would shift the day in UTC+5. */
const iso = (date: Date): string => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const parse = (value: string): Date | null => {
  if (!value) return null;
  const [y, m, d] = value.split('-').map(Number);
  return y && m && d ? new Date(y, m - 1, d) : null;
};

const intl = computed(() => (locale.value === 'ru' ? 'ru-RU' : 'en-GB'));

const display = (value: string): string => {
  const date = parse(value);
  return date ? `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}` : '';
};

const hasRange = computed(() => Boolean(props.from || props.to));

const label = computed(() => {
  if (props.from && props.to) return `${display(props.from)} — ${display(props.to)}`;
  if (props.from) return `${display(props.from)} — …`;
  if (props.to) return `… — ${display(props.to)}`;
  return props.placeholder || t('Any date');
});

// The month on screen. Opens on the range's start so reopening a set filter
// shows what is selected rather than today.
const viewMonth = ref(parse(props.from) ?? parse(props.to) ?? new Date());

watch(open, (isOpen) => {
  if (isOpen) {
    viewMonth.value = parse(props.from) ?? parse(props.to) ?? new Date();
    hovered.value = '';
  }
});

const monthLabel = computed(() =>
  viewMonth.value.toLocaleDateString(intl.value, { month: 'long', year: 'numeric' }),
);

// Monday-first week, as read in both ru and en-GB.
const weekdays = computed(() => {
  const monday = new Date(2024, 0, 1);
  return Array.from({ length: 7 }, (_, i) => {
    const day = new Date(monday);
    day.setDate(monday.getDate() + i);
    return day.toLocaleDateString(intl.value, { weekday: 'short' });
  });
});

/** Six weeks of days, padded from the surrounding months so the grid is stable. */
const days = computed(() => {
  const first = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth(), 1);
  const offset = (first.getDay() + 6) % 7; // Sunday=0 → Monday-first
  const start = new Date(first);
  start.setDate(first.getDate() - offset);

  return Array.from({ length: 42 }, (_, i) => {
    const date = new Date(start);
    date.setDate(start.getDate() + i);
    return {
      value: iso(date),
      day: date.getDate(),
      outside: date.getMonth() !== viewMonth.value.getMonth(),
    };
  });
});

const today = iso(new Date());

// While only the start is picked, the hovered day previews the other end.
const rangeEnd = computed(() => (props.from && !props.to && hovered.value ? hovered.value : props.to));

const inRange = (value: string): boolean => {
  const start = props.from;
  const end = rangeEnd.value;
  if (!start || !end) return false;
  return value >= (start < end ? start : end) && value <= (start < end ? end : start);
};

const isEdge = (value: string): boolean => value === props.from || value === props.to;

const shiftMonth = (delta: number) => {
  viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() + delta, 1);
};

const commit = (from: string, to: string) => {
  emit('update:from', from);
  emit('update:to', to);
  emit('change');
};

/**
 * First click starts a range, second closes it. Clicking before the start
 * re-anchors rather than producing an inverted range.
 */
const pick = (value: string) => {
  if (!props.from || props.to) {
    emit('update:from', value);
    emit('update:to', '');
    return;
  }

  if (value < props.from) {
    emit('update:from', value);
    return;
  }

  commit(props.from, value);
  open.value = false;
};

const clear = () => {
  commit('', '');
  open.value = false;
};

const shiftDays = (count: number): string => {
  const date = new Date();
  date.setDate(date.getDate() - count);
  return iso(date);
};

const presets = computed(() => {
  const now = new Date();
  const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
  const lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0);

  return [
    { label: t('Today'), from: today, to: today },
    { label: t('Last 7 days'), from: shiftDays(6), to: today },
    { label: t('Last 30 days'), from: shiftDays(29), to: today },
    { label: t('This month'), from: iso(monthStart), to: today },
    { label: t('Last month'), from: iso(lastMonthStart), to: iso(lastMonthEnd) },
  ];
});

const applyPreset = (preset: { from: string; to: string }) => {
  commit(preset.from, preset.to);
  open.value = false;
};
</script>

<template>
  <div class="relative w-full md:w-auto">
    <Popover v-model:open="open">
      <PopoverTrigger as-child>
        <button
          type="button"
          class="flex h-10 w-full items-center gap-2 rounded-md border border-input bg-white px-3 text-sm shadow-sm transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring md:h-9 md:w-auto md:min-w-[15rem] dark:bg-gray-900"
          :class="hasRange ? 'pr-9' : ''"
        >
          <CalendarDays class="h-4 w-4 shrink-0 text-muted-foreground" />
          <span class="flex-1 truncate text-left" :class="hasRange ? 'text-foreground' : 'text-muted-foreground'">
            {{ label }}
          </span>
        </button>
      </PopoverTrigger>

      <PopoverContent class="w-[19rem] p-0 sm:w-auto">
        <div class="flex flex-col sm:flex-row">
          <!-- Presets: the ranges people ask for by name, so the common case
               never needs two clicks on the grid. -->
          <div class="flex shrink-0 flex-wrap gap-1 border-b p-2 sm:w-40 sm:flex-col sm:border-r sm:border-b-0">
            <button
              v-for="preset in presets"
              :key="preset.label"
              type="button"
              class="rounded-md px-2 py-1.5 text-left text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-sm"
              @click="applyPreset(preset)"
            >
              {{ preset.label }}
            </button>
          </div>

          <div class="p-2">
            <div class="mb-2 flex items-center justify-between gap-2">
              <Button variant="ghost" size="icon" class="h-7 w-7" :aria-label="t('Previous month')" @click="shiftMonth(-1)">
                <ChevronLeft class="h-4 w-4" />
              </Button>
              <span class="text-sm font-semibold capitalize">{{ monthLabel }}</span>
              <Button variant="ghost" size="icon" class="h-7 w-7" :aria-label="t('Next month')" @click="shiftMonth(1)">
                <ChevronRight class="h-4 w-4" />
              </Button>
            </div>

            <div class="grid grid-cols-7 gap-0.5" @mouseleave="hovered = ''">
              <div
                v-for="weekday in weekdays"
                :key="weekday"
                class="pb-1 text-center text-[10px] font-bold uppercase tracking-wider text-muted-foreground"
              >
                {{ weekday }}
              </div>

              <button
                v-for="day in days"
                :key="day.value"
                type="button"
                class="h-8 w-8 rounded-md text-xs tabular-nums transition-colors"
                :class="[
                  day.outside ? 'text-muted-foreground/40' : 'text-foreground',
                  isEdge(day.value)
                    ? 'bg-primary font-bold text-primary-foreground hover:bg-primary'
                    : inRange(day.value)
                      ? 'bg-primary/15 text-foreground'
                      : 'hover:bg-muted',
                  day.value === today && !isEdge(day.value) ? 'ring-1 ring-primary/40' : '',
                ]"
                @click="pick(day.value)"
                @mouseenter="hovered = day.value"
              >
                {{ day.day }}
              </button>
            </div>

            <div class="mt-2 flex justify-end border-t pt-2">
              <Button variant="ghost" size="sm" class="h-7 text-xs text-muted-foreground" @click="clear">
                {{ t('Clear') }}
              </Button>
            </div>
          </div>
        </div>
      </PopoverContent>
    </Popover>

    <button
      v-if="hasRange"
      type="button"
      :aria-label="t('Clear')"
      class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
      @click="clear"
    >
      <X class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
