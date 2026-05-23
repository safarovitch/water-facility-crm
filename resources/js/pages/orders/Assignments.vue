<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem, type Order, type User } from '@/types';
import { assign as assignRoute } from '@/routes/admin/orders';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader as DHeader, DialogTitle } from '@/components/ui/dialog';
import { 
  Users2, 
  Package, 
  Search,
  Filter,
  Check,
  ChevronDown,
  X,
  User as UserIcon,
  Truck,
  Box
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface CourierOption extends User {
  avatar_url: string;
  statusLabel: string;
  statusHtmlClass: string;
  orders_count: number;
  last_active_at: string | null;
}

const props = defineProps<{
  couriers: CourierOption[];
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
    const q = searchQuery.value.toLowerCase();
    const matchesSearch = !searchQuery.value ||
      order.order_number.toLowerCase().includes(q) ||
      order.client?.name?.toLowerCase().includes(q) ||
      order.contact_name?.toLowerCase().includes(q);
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

const assignModalOrderId = ref<number | null>(null);
const courierSearch = ref('');

const filteredCouriers = computed(() => {
  if (!courierSearch.value) return props.couriers;
  return props.couriers.filter(c => 
    c.name.toLowerCase().includes(courierSearch.value.toLowerCase())
  );
});

const isCurrierOnline = (courier: CourierOption) => {
  const lastActive = courier.last_active_at ? new Date(courier.last_active_at) : null;
  if (!lastActive) return false;
  return (new Date().getTime() - lastActive.getTime()) < 5 * 60 * 1000;
};

const openSelect = (orderId: number) => {
  assignModalOrderId.value = orderId;
  courierSearch.value = '';
};

const handleAssign = (courierId: number | null) => {
  if (!assignModalOrderId.value) return;
  
  router.patch(assignRoute({ order: assignModalOrderId.value }).url, {
    courier_id: courierId,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      assignModalOrderId.value = null;
    }
  });
};

const currentOrderToAssign = computed(() => {
  if (!assignModalOrderId.value) return null;
  return props.orders.find(o => o.id === assignModalOrderId.value) || null;
});

const isModalOpen = computed({
  get: () => assignModalOrderId.value !== null,
  set: (val) => {
    if (!val) assignModalOrderId.value = null;
  }
});

</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Currier Assignments" />

    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Currier Assignments</h1>
          <p class="text-muted-foreground mt-1 text-sm font-medium">Manage and monitor active delivery assignments.</p>
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
                <tr v-for="order in filteredOrders" :key="order.id" class="bg-white dark:bg-sidebar hover:bg-muted/5 transition-colors">
                  <td class="px-6 py-4">
                    <div class="flex flex-col">
                      <span class="font-black text-foreground">#{{ order.order_number }}</span>
                      <span class="text-[10px] text-muted-foreground font-bold">{{ order.created_at_human }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-col">
                      <span class="font-bold text-xs leading-tight">{{ order.contact_name || order.client?.name }}</span>
                      <span class="text-[10px] text-muted-foreground truncate max-w-xs">{{ order.delivery_address }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <Badge :class="['px-2 py-0.5 text-[9px] font-black uppercase rounded-lg border', statusColor(order.status)]">
                      {{ order.status.replace('_', ' ') }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4">
                    <div class="relative w-56">
                        <!-- Pop-up Trigger -->
                        <Button
                          @click="openSelect(order.id)" 
                          variant="outline" 
                          class="w-full justify-between h-9 px-3 rounded-xl hover:border-primary/50 transition-all font-normal shadow-none" 
                          :class="order.courier ? 'text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-900/20' : ''"
                        >
                          <span v-if="order.courier" class="flex items-center gap-2 truncate text-xs">
                            <span class="w-1.5 h-1.5 min-w-[6px] rounded-full bg-blue-500"></span>
                            <span class="font-bold truncate">{{ order.courier.name }}</span>
                          </span>
                          <span v-else class="text-xs">Assign...</span>
                          <ChevronDown class="h-3 w-3 opacity-40 flex-shrink-0 ml-1" />
                        </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
      
      <!-- Courier Assignment Modal -->
      <Dialog v-model:open="isModalOpen">
        <DialogContent class="sm:max-w-xl">
          <DHeader>
            <DialogTitle class="text-xl font-bold">Assign Currier</DialogTitle>
            <DialogDescription v-if="currentOrderToAssign">
              Select an available currier to process Order #<span class="font-bold text-foreground">{{ currentOrderToAssign.order_number }}</span>.
            </DialogDescription>
          </DHeader>
          
          <div class="py-2 space-y-2 lg:max-h-[60vh] sm:max-h-[80vh] overflow-y-auto pr-2">
            <!-- Modal Internal Search -->
            <div class="relative mb-3">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input 
                v-model="courierSearch" 
                placeholder="Search curriers by name..." 
                class="pl-9 h-10 w-full rounded-xl bg-muted/40"
              />
            </div>

            <!-- Unassign option -->
            <button 
              @click="handleAssign(null)"
              class="w-full flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:bg-red-50 dark:hover:bg-red-900/20 hover:border-red-200 dark:hover:border-red-800 transition-all text-left"
              :class="{ 'opacity-50 pointer-events-none': currentOrderToAssign && !currentOrderToAssign.courier_id }"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <Box class="w-5 h-5 text-gray-400" />
                </div>
                <div>
                  <p class="font-bold text-gray-900 dark:text-gray-100">Unassign Order</p>
                  <p class="text-xs text-gray-500">Remove currently assigned currier.</p>
                </div>
              </div>
            </button>

            <!-- Currier list -->
            <button 
              v-for="courier in filteredCouriers" 
              :key="courier.id"
              @click="handleAssign(courier.id)"
              class="w-full flex items-center justify-between p-4 rounded-xl border transition-all text-left relative overflow-hidden group"
              :class="(currentOrderToAssign && currentOrderToAssign.courier_id === courier.id) ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 dark:bg-gray-800/50 dark:hover:bg-gray-800'"
            >
              <!-- Selection indicator -->
              <div v-if="currentOrderToAssign && currentOrderToAssign.courier_id === courier.id" class="absolute top-0 right-0 w-12 h-12 flex items-start justify-end p-1.5 opacity-20 bg-blue-500 rounded-bl-[100%]">
                <Check class="w-4 h-4 text-white translate-x-1 -translate-y-1" />
              </div>

              <div class="flex items-center gap-4 relative z-10 w-full">
                <!-- Avatar with status indicator -->
                <div class="relative">
                  <img :src="courier.avatar_url" :alt="courier.name" class="w-12 h-12 rounded-full object-cover border-2 shadow-sm" :class="(currentOrderToAssign && currentOrderToAssign.courier_id === courier.id) ? 'border-blue-500' : 'border-white dark:border-gray-900'" />
                  <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white dark:border-gray-800" :class="isCurrierOnline(courier) ? 'bg-green-500' : 'bg-gray-400'" :title="isCurrierOnline(courier) ? 'Online' : 'Offline'"></span>
                </div>
                
                <div class="flex-1 min-w-0">
                  <p class="font-bold text-gray-900 dark:text-gray-100 truncate flex items-center gap-2">
                    {{ courier.name }}
                    <span v-if="currentOrderToAssign && currentOrderToAssign.courier_id === courier.id" class="text-[10px] uppercase font-black tracking-wider text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/50 px-2 py-0.5 rounded text-center">Current</span>
                  </p>
                  <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                      <div class="w-2 h-2 rounded-full" :class="isCurrierOnline(courier) ? 'bg-green-500' : 'bg-gray-400'"></div>
                      {{ isCurrierOnline(courier) ? 'Online' : 'Offline' }}
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex items-center gap-1" :class="courier.orders_count > 0 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                      <Box class="w-3 h-3" />
                      {{ courier.orders_count }} active tasks
                    </span>
                  </div>
                </div>
              </div>
            </button>
            
            <div v-if="filteredCouriers.length === 0" class="py-8 text-center bg-gray-50 dark:bg-gray-800/20 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                <Users2 class="h-8 w-8 mx-auto text-gray-400 mb-2 opacity-50" />
                <p class="text-sm font-bold text-muted-foreground">No curriers found</p>
            </div>
          </div>
        </DialogContent>
      </Dialog>
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
