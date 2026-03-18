<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { CheckCircle2, AlertCircle, Info, AlertTriangle, X } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

const { toasts, remove, success, error } = useToast();
const page = usePage();

// Watch for flash messages from the backend
watch(
  () => page.props.flash,
  (flash: any) => {
    if (flash?.success) {
      success(flash.success);
    }
    if (flash?.error) {
      error(flash.error);
    }
  },
  { deep: true, immediate: true }
);

const icons = {
  success: CheckCircle2,
  error: AlertCircle,
  info: Info,
  warning: AlertTriangle,
};

const styles = {
  success: 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-400',
  error: 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-800 dark:text-red-400',
  info: 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-400',
  warning: 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/30 dark:border-amber-800 dark:text-amber-400',
};

const iconStyles = {
  success: 'text-green-500',
  error: 'text-red-500',
  info: 'text-blue-500',
  warning: 'text-amber-500',
};
</script>

<template>
  <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-[-20px] opacity-0 scale-95"
      enter-to-class="transform translate-y-0 opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
      move-class="transition duration-300 ease-in-out"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-lg backdrop-blur-md transition-all group"
        :class="styles[toast.type]"
      >
        <component :is="icons[toast.type]" class="w-5 h-5 shrink-0 mt-0.5" :class="iconStyles[toast.type]" />
        
        <div class="flex-1 text-sm font-medium leading-relaxed">
          {{ toast.message }}
        </div>

        <button
          @click="remove(toast.id)"
          class="shrink-0 p-1 rounded-md hover:bg-black/5 dark:hover:bg-white/10 transition-colors opacity-0 group-hover:opacity-100"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
