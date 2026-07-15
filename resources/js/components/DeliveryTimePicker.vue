<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(
  defineProps<{
    modelValue: string | null;
    autoDefault?: boolean;
    minDate?: string | null;
    id?: string;
  }>(),
  {
    autoDefault: false,
    minDate: null,
    id: undefined,
  },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();
const { t } = useI18n();

const pad = (n: number) => String(n).padStart(2, '0');

// Half-hour delivery slots from 09:00 to 20:00 inclusive.
const slots = computed(() => {
  const out: string[] = [];
  for (let m = 9 * 60; m <= 20 * 60; m += 30) {
    out.push(`${pad(Math.floor(m / 60))}:${pad(m % 60)}`);
  }
  return out;
});

const datePart = ref('');
const timePart = ref('');

const fmtDate = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

// Snap an arbitrary HH:mm into the 09:00–20:00 half-hour grid.
const snapTime = (hhmm: string): string => {
  const [h, m] = hhmm.split(':').map(Number);
  let tod = (h || 0) * 60 + (m || 0);
  tod = Math.min(20 * 60, Math.max(9 * 60, tod));
  const snapped = Math.round((tod - 9 * 60) / 30) * 30 + 9 * 60;
  return `${pad(Math.floor(snapped / 60))}:${pad(snapped % 60)}`;
};

// Default delivery target: now + 10h, snapped to a slot. After 20:00 → next
// day 09:00; before 09:00 → same day 09:00.
const computeDefault = (): { date: string; time: string } => {
  const d = new Date();
  d.setSeconds(0, 0);
  d.setMinutes(d.getMinutes() + 600);
  const tod = Math.round((d.getHours() * 60 + d.getMinutes()) / 30) * 30;
  if (tod > 20 * 60) {
    d.setDate(d.getDate() + 1);
    return { date: fmtDate(d), time: '09:00' };
  }
  if (tod < 9 * 60) {
    return { date: fmtDate(d), time: '09:00' };
  }
  return { date: fmtDate(d), time: `${pad(Math.floor(tod / 60))}:${pad(tod % 60)}` };
};

// Parse an incoming "YYYY-MM-DDTHH:mm" value into date + snapped slot.
const parse = (value: string) => {
  const [date, time = ''] = value.split('T');
  datePart.value = date;
  timePart.value = snapTime(time.slice(0, 5) || '09:00');
};

const applyDefault = () => {
  const def = computeDefault();
  datePart.value = def.date;
  timePart.value = def.time;
  emit('update:modelValue', `${def.date}T${def.time}`);
};

onMounted(() => {
  if (props.modelValue) {
    parse(props.modelValue);
  } else if (props.autoDefault) {
    applyDefault();
  }
});

// React to external changes (form reset, repeat modal re-opening, etc.).
watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      parse(value);
    } else if (props.autoDefault) {
      applyDefault();
    } else {
      datePart.value = '';
      timePart.value = '';
    }
  },
);

watch([datePart, timePart], () => {
  // A slot is always selected, so the value hinges on the date: cleared date
  // emits '' (creation paths then fall back to the server default).
  emit('update:modelValue', datePart.value && timePart.value ? `${datePart.value}T${timePart.value}` : '');
});
</script>

<template>
  <div class="flex gap-2">
    <input
      :id="id"
      type="date"
      :min="minDate ?? undefined"
      v-model="datePart"
      class="flex h-9 flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-sky-400 dark:border-gray-600 dark:bg-input/30 dark:text-white"
    />
    <select
      v-model="timePart"
      :aria-label="t('Delivery time slot')"
      class="flex h-9 rounded-md border border-input bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-sky-400 dark:border-gray-600 dark:bg-input/30 dark:text-white"
    >
      <option v-for="slot in slots" :key="slot" :value="slot">{{ slot }}</option>
    </select>
  </div>
</template>
