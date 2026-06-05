import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

interface SortConfig {
  column: string;
  direction: 'asc' | 'desc';
}

export function useTableSort(initialSort?: SortConfig, preserveParams?: Record<string, any>) {
  const sort = ref<SortConfig | null>(initialSort || null);

  const isSorted = (column: string): boolean => sort.value?.column === column;

  const getSortDirection = (column: string): 'asc' | 'desc' | null => {
    if (!isSorted(column)) return null;
    return sort.value!.direction;
  };

  const toggleSort = (column: string) => {
    if (isSorted(column)) {
      sort.value = sort.value!.direction === 'asc'
        ? { column, direction: 'desc' }
        : null;
    } else {
      sort.value = { column, direction: 'asc' };
    }
  };

  const applySortToRouter = (url: string, additionalParams?: Record<string, any>) => {
    const params = {
      ...preserveParams,
      ...additionalParams,
      ...(sort.value && { sort_by: sort.value.column, sort_dir: sort.value.direction })
    };

    router.get(url, params, { preserveState: true, preserveScroll: true });
  };

  const getSortIcon = (column: string): string => {
    if (!isSorted(column)) return '⇅';
    return getSortDirection(column) === 'asc' ? '↑' : '↓';
  };

  return {
    sort,
    isSorted,
    getSortDirection,
    toggleSort,
    applySortToRouter,
    getSortIcon,
  };
}
