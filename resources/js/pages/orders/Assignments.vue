<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { type BreadcrumbItem, type Order, type User } from '@/types';
import { assign as assignRoute } from '@/routes/orders';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { 
  Users2, 
  Package, 
  Search,
  Filter,
  Check,
  ChevronDown,
  X,
  User as UserIcon,
  Truck
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';

const props = defineProps<{
  couriers: User[];
  orders: Order[];
  statuses: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: '/orders/index' },
  { title: 'Currier Assignments', href: '/orders/assignments' },
];

const selectedStatus = ref<string>('ready');
const searchQuery = ref('');

const filteredOrders = computed(() => {
  return props.orders.filter(order => {
    const matchesStatus = !selectedStatus.value || order.status === selectedStatus.value;
    const matchesSearch = !searchQuery.value || 
      order.order_number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      order.client?.name?.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchesStatus && matchesSearch;
  });
});

const statusColor = (status: string) => {
  const m: Record<string, string> = {
    pending:       'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
    confirmed:     'bg-blue-500/10 text-blue-600 border-blue-500/20',
    in_production: 'bg-purple-500/10 text-purple-600 border-purple-500/20',
    ready:         'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
    accepted:      'bg-indigo-500/10 text-indigo-600 border-indigo-500/20',
    in_transit:    'bg-orange-500/10 text-orange-600 border-orange-500/20',
    delivered:     'bg-green-500/10 text-green-600 border-green-500/20',
    cancelled:     'bg-red-500/10 text-red-600 border-red-500/20',
  };
  return m[status] ?? 'bg-gray-500/10 text-gray-600 border-gray-500/20';
};

// ── Searchable Currier Select State ──────────────────────────────────────────

const activeOrderId = ref<number | null>(null);
const courierSearch = ref('');
const selectRefs = ref<Record<number, HTMLElement>>({});

const filteredCouriers = computed(() => {
  if (!courierSearch.value) return props.couriers;
  return props.couriers.filter(c => 
    c.name.toLowerCase().includes(courierSearch.value.toLowerCase())
  );
});

const openSelect = (orderId: number) => {
  if (activeOrderId.value === orderId) {
    activeOrderId.value = null;
  } else {
    activeOrderId.value = orderId;
    courierSearch.value = '';
  }
};

const handleAssign = (orderId: number, courierId: number | null) => {
  router.patch(assignRoute({ order: orderId }).url, {
    courier_id: courierId,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      activeOrderId.value = null;
    }
  });
};

const clickOutsideTarget = ref<HTMLElement | null>(null);
onClickOutside(clickOutsideTarget, (event) => {
    // Prevent closing if we clicked the trigger of the active order
    const triggerClass = 'select-trigger-' + activeOrderId.value;
    if (event.target instanceof HTMLElement && event.target.closest('.' + triggerClass)) {
        return;
    }
    activeOrderId.value = null;
});

</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Currier Assignments" />

    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Currier Assignments</h1>
          <p class="text-muted-foreground mt-1 text-sm font-medium">Manage and monitor active delivery assignments in real-time.</p>
        </div>
        
        <div class="flex items-center gap-4 bg-white dark:bg-sidebar p-2 px-3 rounded-2xl shadow-sm border border-sidebar-border/60">
            <div class="flex items-center gap-3 px-4 py-1 border-r border-sidebar-border/60">
                <Users2 class="h-4 w-4 text-primary" />
                <div class="text-[11px] font-black uppercase tracking-widest text-muted-foreground">
                    Curriers: {{ couriers.length }}
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-1">
                <Package class="h-4 w-4 text-emerald-500" />
                <div class="text-[11px] font-black uppercase tracking-widest text-muted-foreground">
                    Active Orders: {{ orders.length }}
                </div>
            </div>
        </div>
      </div>

      <Card class="border-sidebar-border/60 shadow-md">
        <CardHeader class="pb-4">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="relative w-full md:w-80">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Search order # or client..." 
                        class="pl-9 rounded-xl border-sidebar-border/60 focus-visible:ring-primary"
                    />
                </div>
                
                <div class="flex items-center gap-2 ml-2">
                    <Filter class="h-4 w-4 text-muted-foreground" />
                    <select 
                        v-model="selectedStatus"
                        class="h-9 px-3 text-xs font-bold rounded-xl border border-sidebar-border/60 bg-transparent outline-none focus:ring-1 focus:ring-primary appearance-none pr-8 relative cursor-pointer"
                    >
                        <option value="">All Active Statuses</option>
                        <option v-for="(val, key) in statuses" :key="val" :value="val">
                            {{ key }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" size="sm" class="rounded-xl h-9 font-bold text-xs" @click="selectedStatus = 'ready'">
                    Default: Ready
                </Button>
                <Button variant="ghost" size="sm" class="rounded-xl h-9 font-bold text-xs" @click="selectedStatus = ''; searchQuery = ''">
                    Clear All
                </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent class="p-0 overflow-visible">
          <div class="relative">
            <table class="w-full text-sm text-left">
              <thead class="text-[10px] font-black uppercase tracking-widest text-muted-foreground border-y bg-muted/30 sticky top-0 z-20">
                <tr>
                  <th class="px-6 py-4">Order Details</th>
                  <th class="px-6 py-4">Client</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4">Assignment</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar-border/40">
                <tr v-if="filteredOrders.length === 0" class="bg-white dark:bg-sidebar">
                    <td colspan="4" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center gap-2 opacity-40">
                            <Truck class="h-10 w-10 mb-2" />
                            <p class="font-black tracking-widest uppercase text-xs">No orders found matching filters</p>
                        </div>
                    </td>
                </tr>
                <tr v-for="order in filteredOrders" :key="order.id" class="bg-white dark:bg-sidebar hover:bg-muted/5 transition-colors" :class="{ 'z-50 relative': activeOrderId === order.id }">
                  <td class="px-6 py-4">
                    <div class="flex flex-col">
                      <span class="font-black text-foreground">#{{ order.order_number }}</span>
                      <span class="text-[10px] text-muted-foreground font-bold">{{ order.created_at_human }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-col">
                      <span class="font-bold text-xs leading-tight">{{ order.client?.name }}</span>
                      <span class="text-[10px] text-muted-foreground truncate max-w-xs">{{ order.delivery_address }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <Badge :class="['px-2 py-0.5 text-[9px] font-black uppercase rounded-lg border', statusColor(order.status)]">
                      {{ order.status.replace('_', ' ') }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4">
                    <div class="relative w-48" :ref="el => { if (el) selectRefs[order.id] = el as HTMLElement }">
                        <!-- Searchable Select Trigger -->
                        <div 
                          @click="openSelect(order.id)"
                          :class="['flex items-center justify-between h-9 px-3 border border-sidebar-border/60 rounded-xl cursor-pointer hover:border-primary/50 transition-all bg-white/50 dark:bg-sidebar/50 shadow-sm', 'select-trigger-' + order.id]"
                        >
                            <span class="text-[11px] font-bold truncate">
                                {{ order.courier?.name ?? 'Select Currier...' }}
                            </span>
                            <ChevronDown class="h-3 w-3 opacity-40" />
                        </div>

                        <!-- Searchable Select Dropdown -->
                        <div 
                          v-if="activeOrderId === order.id" 
                          ref="clickOutsideTarget"
                          class="absolute z-[100] top-11 left-0 w-64 bg-white dark:bg-sidebar border border-sidebar-border shadow-2xl rounded-2xl overflow-hidden animate-in fade-in zoom-in duration-150"
                        >
                            <div class="p-2 border-b bg-muted/20 flex items-center gap-2">
                                <div class="relative flex-1">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3 w-3 text-muted-foreground" />
                                    <input 
                                      v-model="courierSearch"
                                      autoFocus
                                      placeholder="Search..."
                                      class="w-full h-8 pl-8 pr-3 text-xs bg-transparent border-none outline-none focus:ring-0 font-bold"
                                    />
                                </div>
                                <button @click="activeOrderId = null" class="p-1 hover:bg-muted rounded-lg text-muted-foreground transition-colors">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="max-h-60 overflow-y-auto p-1 py-2">
                                <button
                                    @click="handleAssign(order.id, null)"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 rounded-xl transition-colors"
                                >
                                    <X class="h-3 w-3" />
                                    Unassign Order
                                </button>
                                <div class="h-px bg-sidebar-border/40 my-1"></div>
                                <button
                                    v-for="courier in filteredCouriers"
                                    :key="courier.id"
                                    @click="handleAssign(order.id, courier.id)"
                                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold hover:bg-primary/10 hover:text-primary rounded-xl transition-colors group"
                                    :class="{ 'bg-primary/5 text-primary': order.courier_id === courier.id }"
                                >
                                    <div class="flex items-center gap-2">
                                        <UserIcon class="h-3 w-3 opacity-40 group-hover:opacity-100" />
                                        <span>{{ courier.name }}</span>
                                    </div>
                                    <Check v-if="order.courier_id === courier.id" class="h-3 w-3" />
                                </button>
                                <div v-if="filteredCouriers.length === 0" class="py-4 text-center text-[10px] font-bold text-muted-foreground uppercase opacity-40">
                                    No curriers found
                                </div>
                            </div>
                        </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Hidden scrollbar but keeps functionality */
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--sidebar-border)) transparent;
}
</style>
