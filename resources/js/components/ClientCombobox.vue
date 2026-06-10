<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';

interface UserProfile { company_name: string | null; type: string }
interface Client {
  id: number;
  name: string;
  email: string;
  user_profile?: UserProfile | null;
}

const props = withDefaults(defineProps<{
  modelValue: number | null;
  clients: Client[];
  placeholder?: string;
  /** Forwarded to the trigger so `:required` validation / labels still work. */
  id?: string;
}>(), {
  placeholder: 'Select client...',
});

const emit = defineEmits<{ (e: 'update:modelValue', value: number | null): void }>();

const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const listEl = ref<HTMLElement | null>(null);
const open = ref(false);
const query = ref('');
const highlighted = ref(0);

const label = (c: Client) =>
  c.user_profile?.company_name ? `${c.name} (${c.user_profile.company_name})` : c.name;

const selected = computed(() => props.clients.find((c) => c.id === props.modelValue) ?? null);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return props.clients;
  return props.clients.filter((c) => {
    const haystack = [c.name, c.email, c.user_profile?.company_name]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(q);
  });
});

watch(filtered, () => { highlighted.value = 0; });

const openPanel = async () => {
  if (open.value) return;
  open.value = true;
  query.value = '';
  highlighted.value = Math.max(0, filtered.value.findIndex((c) => c.id === props.modelValue));
  await nextTick();
  searchInput.value?.focus();
  scrollToHighlighted();
};

const closePanel = () => {
  open.value = false;
};

const togglePanel = () => (open.value ? closePanel() : openPanel());

const select = (c: Client) => {
  emit('update:modelValue', c.id);
  closePanel();
};

const clear = (e: Event) => {
  e.stopPropagation();
  emit('update:modelValue', null);
};

const scrollToHighlighted = () => {
  nextTick(() => {
    const el = listEl.value?.querySelector<HTMLElement>(`[data-idx="${highlighted.value}"]`);
    el?.scrollIntoView({ block: 'nearest' });
  });
};

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1);
    scrollToHighlighted();
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlighted.value = Math.max(highlighted.value - 1, 0);
    scrollToHighlighted();
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const c = filtered.value[highlighted.value];
    if (c) select(c);
  } else if (e.key === 'Escape') {
    e.preventDefault();
    closePanel();
  }
};

const onDocPointer = (e: MouseEvent) => {
  if (open.value && root.value && !root.value.contains(e.target as Node)) {
    closePanel();
  }
};

onMounted(() => document.addEventListener('mousedown', onDocPointer));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocPointer));
</script>

<template>
  <div ref="root" class="relative">
    <!-- Trigger -->
    <button
      :id="id"
      type="button"
      @click="togglePanel"
      :aria-expanded="open"
      aria-haspopup="listbox"
      class="border-input flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm dark:bg-input/30 dark:border-gray-600 dark:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <span :class="['truncate text-left', selected ? '' : 'text-muted-foreground']">
        {{ selected ? label(selected) : placeholder }}
      </span>
      <span class="flex items-center gap-1 shrink-0">
        <X
          v-if="selected"
          class="h-4 w-4 text-muted-foreground hover:text-foreground"
          @click="clear"
        />
        <ChevronsUpDown class="h-4 w-4 text-muted-foreground" />
      </span>
    </button>

    <!-- Panel -->
    <div
      v-if="open"
      class="absolute z-50 mt-1 w-full rounded-md border border-input bg-white dark:bg-gray-800 dark:border-gray-700 shadow-lg"
    >
      <div class="relative border-b border-border/60 p-2">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <input
          ref="searchInput"
          v-model="query"
          @keydown="onKeydown"
          type="text"
          placeholder="Search by name or company..."
          class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        />
      </div>

      <ul ref="listEl" role="listbox" class="max-h-64 overflow-y-auto py-1">
        <li
          v-for="(c, idx) in filtered"
          :key="c.id"
          :data-idx="idx"
          role="option"
          :aria-selected="c.id === modelValue"
          @mouseenter="highlighted = idx"
          @click="select(c)"
          :class="[
            'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm',
            idx === highlighted ? 'bg-muted dark:bg-gray-700/60' : '',
          ]"
        >
          <Check
            class="h-4 w-4 shrink-0"
            :class="c.id === modelValue ? 'opacity-100 text-primary' : 'opacity-0'"
          />
          <span class="flex flex-col min-w-0">
            <span class="truncate font-medium text-gray-900 dark:text-white">{{ label(c) }}</span>
            <span v-if="c.email" class="truncate text-xs text-muted-foreground">{{ c.email }}</span>
          </span>
        </li>

        <li v-if="filtered.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
          No clients found.
        </li>
      </ul>
    </div>
  </div>
</template>
