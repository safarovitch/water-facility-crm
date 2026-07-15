<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import DeliveryTimePicker from '@/components/DeliveryTimePicker.vue';
import { Minus, Plus, Loader2, RotateCcw } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

interface RepeatItem {
  product_id: number;
  name: string;
  quantity: number;
  is_gift?: boolean;
}

const props = defineProps<{
  open: boolean;
  items: RepeatItem[];
  submitUrl: string;
  orderNumber?: string | null;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();
const { t } = useI18n();

const lines = ref<RepeatItem[]>([]);
const scheduledAt = ref('');
const processing = ref(false);

// Reset the form from the source order each time the modal opens.
watch(
  () => props.open,
  (open) => {
    if (open) {
      lines.value = props.items.map((i) => ({ ...i }));
      scheduledAt.value = '';
    }
  },
);

const totalBottles = computed(() => lines.value.reduce((sum, l) => sum + (Number(l.quantity) || 0), 0));

const dec = (line: RepeatItem) => {
  if (line.quantity > 1) line.quantity--;
};
const inc = (line: RepeatItem) => {
  line.quantity++;
};

const close = () => emit('update:open', false);

const submit = () => {
  if (processing.value || !lines.value.length) return;
  processing.value = true;
  router.post(
    props.submitUrl,
    {
      scheduled_delivery_at: scheduledAt.value || null,
      items: lines.value.map((l) => ({ product_id: l.product_id, quantity: Math.max(1, Number(l.quantity) || 1) })),
    },
    {
      onSuccess: () => close(),
      onFinish: () => {
        processing.value = false;
      },
    },
  );
};
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ t('Repeat order') }}</DialogTitle>
        <DialogDescription v-if="orderNumber">
          {{ t('New order based on') }} <span class="font-mono">#{{ orderNumber }}</span>. {{ t('Adjust the quantities and delivery time.') }}
        </DialogDescription>
        <DialogDescription v-else>
          {{ t('Choose the quantities and a delivery time, then place the order.') }}
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <!-- Items with quantity steppers -->
        <ul class="rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
          <li v-for="line in lines" :key="line.product_id" class="flex items-center gap-3 px-3 py-2.5">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate text-slate-900 dark:text-slate-100">{{ line.name }}</p>
              <span v-if="line.is_gift" class="text-[10px] font-bold uppercase tracking-wide text-pink-600">{{ t('Gift') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <button
                type="button"
                @click="dec(line)"
                :disabled="line.quantity <= 1"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <Minus class="h-4 w-4" />
              </button>
              <input
                type="number"
                min="1"
                v-model.number="line.quantity"
                class="w-12 rounded-lg border border-slate-200 py-1 text-center text-sm font-semibold text-slate-900 focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              />
              <button
                type="button"
                @click="inc(line)"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <Plus class="h-4 w-4" />
              </button>
            </div>
          </li>
          <li v-if="!lines.length" class="px-3 py-3 text-xs italic text-slate-500">{{ t('No items to repeat.') }}</li>
        </ul>
        <p class="text-right text-xs text-slate-500">
          {{ t('Total bottles') }}: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ totalBottles }}</span>
        </p>

        <!-- Delivery time -->
        <div>
          <label class="text-[11px] uppercase tracking-wider text-slate-400">{{ t('Delivery time') }}</label>
          <DeliveryTimePicker v-model="scheduledAt" auto-default class="mt-1" />
          <p class="mt-1 text-[11px] text-slate-400">{{ t('Slots run 09:00–20:00 in half-hour steps.') }}</p>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          @click="close"
          class="inline-flex h-10 items-center rounded-full px-5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          {{ t('Cancel') }}
        </button>
        <button
          type="button"
          @click="submit"
          :disabled="processing || !lines.length"
          class="inline-flex h-10 items-center gap-2 rounded-full bg-sky-500 px-5 text-sm font-semibold text-white shadow-sm hover:bg-sky-600 disabled:opacity-60"
        >
          <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
          <RotateCcw v-else class="h-4 w-4" />
          {{ t('Place order') }}
        </button>
      </div>
    </DialogContent>
  </Dialog>
</template>
