<script setup lang="ts">
import { ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown, Globe } from 'lucide-vue-next';
import { useLandingI18n, type Locale } from '@/composables/useLandingI18n';

const { locale, setLocale, supportedLocales } = useLandingI18n();
const open = ref(false);
const root = ref<HTMLElement | null>(null);

onClickOutside(root, () => (open.value = false));

const choose = (code: Locale) => {
  setLocale(code);
  open.value = false;
};
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      @click="open = !open"
      class="inline-flex h-10 items-center gap-2 rounded-full border border-slate-200 bg-white/70 px-4 text-sm font-medium text-slate-700 hover:border-slate-300"
      :aria-expanded="open"
      aria-haspopup="listbox"
    >
      <Globe class="h-4 w-4" />
      <span>{{ supportedLocales.find((l) => l.code === locale)?.short ?? 'EN' }}</span>
      <ChevronDown class="h-3.5 w-3.5 opacity-60" />
    </button>

    <ul
      v-if="open"
      role="listbox"
      class="absolute right-0 z-50 mt-2 w-36 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-slate-900/5"
    >
      <li v-for="l in supportedLocales" :key="l.code">
        <button
          type="button"
          @click="choose(l.code)"
          role="option"
          :aria-selected="l.code === locale"
          class="flex w-full items-center justify-between px-3 py-2 text-sm transition"
          :class="
            l.code === locale
              ? 'bg-sky-50 text-sky-700 font-semibold'
              : 'text-slate-700 hover:bg-slate-50'
          "
        >
          <span>{{ l.label }}</span>
          <span class="text-[10px] font-mono text-slate-400">{{ l.short }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
