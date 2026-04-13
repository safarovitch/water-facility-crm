<script setup lang="ts">
import { useOrderNotifications } from '@/composables/useOrderNotifications';
import { ShoppingCart, X } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

const { incomingOrders } = useOrderNotifications();

const removeOrder = (id: number) => {
  const index = incomingOrders.value.findIndex(o => o.id === id);
  if (index > -1) {
    incomingOrders.value.splice(index, 1);
  }
};
</script>

<template>
  <div class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 max-w-sm w-full">
    <TransitionGroup 
      name="toast"
      enter-active-class="transform transition ease-out duration-300"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 font-medium"
    >
      <div 
        v-for="order in incomingOrders" 
        :key="order.id"
        class="bg-white dark:bg-zinc-900 border-2 border-primary-500 shadow-2xl rounded-xl overflow-hidden animate-order-pulse"
      >
        <div class="p-4 flex items-start gap-4">
          <div class="bg-primary-500 p-2 rounded-lg text-white">
            <ShoppingCart class="w-6 h-6" />
          </div>
          
          <div class="flex-1">
            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 flex justify-between items-center">
              Новый заказ!
              <button @click="removeOrder(order.id)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
                <X class="w-4 h-4" />
              </button>
            </h3>
            
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
              Заказ <span class="font-semibold text-primary-600">#{{ order.order_number }}</span>
            </p>
            
            <div class="mt-2 text-xs text-zinc-500 flex justify-between items-end">
              <div>
                <p>Клиент: {{ order.client_name }}</p>
                <p class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">Сумма: {{ order.total_amount }} TJS</p>
              </div>
              
              <Link 
                :href="'/admin/orders/' + order.id" 
                @click="removeOrder(order.id)"
                class="bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 px-3 py-1.5 rounded-lg font-semibold hover:opacity-90 transition-opacity"
              >
                Открыть
              </Link>
            </div>
          </div>
        </div>
        
        <!-- Progress bar for auto-dismiss -->
        <div class="h-1 bg-primary-100 dark:bg-primary-950 w-full overflow-hidden">
          <div class="h-full bg-primary-500 animate-toast-progress"></div>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.animate-order-pulse {
  animation: border-pulse 2s infinite;
}

@keyframes border-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(var(--primary-500), 0.4);
    border-color: rgba(var(--primary-500), 1);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(var(--primary-500), 0);
    border-color: rgba(var(--primary-500), 0.5);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(var(--primary-500), 0);
    border-color: rgba(var(--primary-500), 1);
  }
}

.animate-toast-progress {
  animation: progress 10s linear forwards;
}

@keyframes progress {
  from { width: 100%; }
  to { width: 0%; }
}
</style>
