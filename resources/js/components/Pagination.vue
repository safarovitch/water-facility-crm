<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const props = defineProps<{
  /** A Laravel paginator object (flat) or an API-resource paginator ({ meta, links }). */
  paginator: any;
}>();

// Normalise both shapes: raw LengthAwarePaginator (flat) and resource collections ({ meta }).
const meta = computed(() => props.paginator?.meta ?? props.paginator ?? {});
const links = computed<PaginationLink[]>(() => props.paginator?.meta?.links ?? props.paginator?.links ?? []);

const lastPage = computed(() => Number(meta.value.last_page ?? 1));
const from = computed(() => meta.value.from ?? 0);
const to = computed(() => meta.value.to ?? 0);
const total = computed(() => meta.value.total ?? 0);

function go(url: string | null) {
  if (!url) return;
  // link.url already carries the active filters when the controller uses ->withQueryString().
  router.get(url, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
  <div
    v-if="lastPage > 1"
    class="px-6 py-4 border-t flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-gray-50/50 dark:bg-gray-800/30"
  >
    <p v-if="total" class="text-sm text-muted-foreground">
      Showing {{ from }}–{{ to }} of {{ total }}
    </p>
    <div class="flex flex-wrap gap-1">
      <template v-for="(link, key) in links" :key="key">
        <Button
          v-if="link.url === null"
          disabled
          variant="outline"
          size="sm"
          class="opacity-50 h-8"
          v-html="link.label"
        />
        <Button
          v-else
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          class="h-8"
          @click="go(link.url)"
          v-html="link.label"
        />
      </template>
    </div>
  </div>
</template>
