<script setup lang="ts">
import { computed } from 'vue';
import { Clock, CheckCircle2, Package, Sparkles, Truck, MapPin, XCircle } from 'lucide-vue-next';
import CourierTrackingMap from './CourierTrackingMap.vue';

interface Step { id: string; label: string; icon: any }

interface ActiveOrder {
  id: number;
  order_number: string;
  status: string;
  scheduled_delivery_at_human?: string | null;
  scheduled_delivery_at_formatted?: string | null;
  delivery_address?: string | null;
  courier?: {
    id: number;
    name: string;
    phone?: string | null;
    last_location?: { lat: number; lng: number } | null;
  } | null;
}

const props = defineProps<{ order: ActiveOrder }>();

// Mirror app/Enums/OrderStatus.php (cancelled excluded — handled separately).
const steps: Step[] = [
  { id: 'pending',       label: 'Pending',     icon: Clock },
  { id: 'confirmed',     label: 'Confirmed',   icon: CheckCircle2 },
  { id: 'in_production', label: 'Preparing',   icon: Package },
  { id: 'ready',         label: 'Ready',       icon: Sparkles },
  { id: 'accepted',      label: 'Picked up',   icon: Truck },
  { id: 'in_transit',    label: 'On the way',  icon: Truck },
  { id: 'delivered',     label: 'Delivered',   icon: MapPin },
];

const isCancelled = computed(() => props.order.status === 'cancelled');
const currentIndex = computed(() => steps.findIndex((s) => s.id === props.order.status));
const currentStep = computed(() => steps[currentIndex.value] ?? null);
const previousStep = computed(() => (currentIndex.value > 0 ? steps[currentIndex.value - 1] : null));
const nextStep = computed(() => (currentIndex.value >= 0 && currentIndex.value < steps.length - 1 ? steps[currentIndex.value + 1] : null));

const showMap = computed(() => props.order.status === 'in_transit' && !!props.order.courier);
const initialFix = computed(() => props.order.courier?.last_location ?? null);
</script>

<template>
  <section class="rounded-3xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white dark:from-sky-900/10 dark:to-slate-950 dark:border-sky-900/40 shadow-sm overflow-hidden">
    <!-- Cancelled banner -->
    <div v-if="isCancelled" class="px-6 py-5 bg-red-50 dark:bg-red-900/20 flex items-center gap-3">
      <XCircle class="h-6 w-6 text-red-500" />
      <div>
        <p class="font-semibold text-red-900 dark:text-red-100">Order cancelled</p>
        <p class="text-sm text-red-700 dark:text-red-200">#{{ order.order_number }}</p>
      </div>
    </div>

    <div v-else>
      <!-- Header -->
      <div class="px-6 pt-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-sky-600 dark:text-sky-400">Your order</p>
            <p class="font-mono text-xl font-bold text-slate-900 dark:text-slate-100">#{{ order.order_number }}</p>
          </div>
          <div v-if="order.scheduled_delivery_at_human" class="text-right">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Scheduled</p>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ order.scheduled_delivery_at_human }}</p>
          </div>
        </div>
      </div>

      <!-- Big current-stage icon -->
      <div class="px-6 pt-6 pb-4 flex flex-col items-center text-center">
        <div class="h-24 w-24 rounded-full bg-sky-500 text-white flex items-center justify-center shadow-lg shadow-sky-500/30">
          <component v-if="currentStep" :is="currentStep.icon" class="h-12 w-12" />
        </div>
        <p class="mt-4 text-2xl font-bold text-slate-900 dark:text-white">
          {{ currentStep?.label ?? 'In progress' }}
        </p>

        <!-- Previous / next hint -->
        <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
          <div v-if="previousStep" class="flex items-center gap-1.5 opacity-70">
            <component :is="previousStep.icon" class="h-3.5 w-3.5" />
            <span>{{ previousStep.label }}</span>
          </div>
          <span v-if="previousStep && nextStep" class="opacity-30">←  ●  →</span>
          <div v-if="nextStep" class="flex items-center gap-1.5 font-semibold text-sky-600 dark:text-sky-400">
            <span>Next: {{ nextStep.label }}</span>
            <component :is="nextStep.icon" class="h-3.5 w-3.5" />
          </div>
        </div>
      </div>

      <!-- Step rail -->
      <div class="px-6 pb-6">
        <div class="relative flex justify-between">
          <div class="absolute top-4 left-0 right-0 h-0.5 bg-slate-200 dark:bg-slate-700" />
          <div
            class="absolute top-4 left-0 h-0.5 bg-sky-500 transition-all duration-500"
            :style="{ width: `${currentIndex >= 0 ? (currentIndex / (steps.length - 1)) * 100 : 0}%` }"
          />
          <div
            v-for="(step, index) in steps"
            :key="step.id"
            class="relative z-10 flex flex-col items-center gap-1"
          >
            <div
              :class="[
                'flex h-8 w-8 items-center justify-center rounded-full border-2 transition-colors duration-300',
                index <= currentIndex
                  ? 'bg-sky-500 border-sky-500 text-white'
                  : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600 text-slate-400'
              ]"
            >
              <component :is="step.icon" class="h-4 w-4" />
            </div>
            <span
              :class="[
                'text-[10px] font-medium hidden sm:block',
                index <= currentIndex ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400'
              ]"
            >
              {{ step.label }}
            </span>
          </div>
        </div>
      </div>

      <!-- Live courier map (only when in_transit + courier assigned) -->
      <div v-if="showMap" class="px-6 pb-6">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center">
                <Truck class="h-4 w-4 text-sky-600" />
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ order.courier?.name }}</p>
                <p class="text-xs text-slate-500">Your courier is on the way</p>
              </div>
            </div>
            <a
              v-if="order.courier?.phone"
              :href="`tel:${order.courier.phone}`"
              class="text-xs font-semibold text-sky-600 hover:text-sky-700"
            >Call</a>
          </div>
          <CourierTrackingMap
            :initial="initialFix"
            poll-url="/me/active-tracking"
            height="280px"
          />
        </div>
      </div>
    </div>
  </section>
</template>
