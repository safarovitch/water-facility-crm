import { reactive } from 'vue';

export interface Toast {
  id: number;
  message: string;
  type: 'success' | 'error' | 'info' | 'warning';
  duration?: number;
}

const state = reactive({
  toasts: [] as Toast[],
});

let nextId = 0;

export function useToast() {
  const add = (message: string, type: Toast['type'] = 'info', duration = 5000) => {
    const id = nextId++;
    const toast: Toast = { id, message, type, duration };
    state.toasts.push(toast);

    if (duration > 0) {
      setTimeout(() => {
        remove(id);
      }, duration);
    }

    return id;
  };

  const remove = (id: number) => {
    const index = state.toasts.findIndex((t) => t.id === id);
    if (index !== -1) {
      state.toasts.splice(index, 1);
    }
  };

  const success = (message: string, duration?: number) => add(message, 'success', duration);
  const error = (message: string, duration?: number) => add(message, 'error', duration);
  const info = (message: string, duration?: number) => add(message, 'info', duration);
  const warning = (message: string, duration?: number) => add(message, 'warning', duration);

  return {
    toasts: state.toasts,
    add,
    remove,
    success,
    error,
    info,
    warning,
  };
}
