<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem, type Order, type User } from '@/types';
import { assign as assignRoute } from '@/routes/orders';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
  Users2, 
  Package, 
  Clock, 
  MapPin, 
  User as UserIcon, 
  ArrowRightLeft,
  ChevronRight,
  TrendingUp,
  Truck,
  AlertCircle
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
  couriers: User[];
  unassignedOrders: Order[];
  assignedOrders: Order[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Orders', href: '/orders/index' },
  { title: 'Currier Assignments', href: '/orders/assignments' },
];

const selectedOrderForAssignment = ref<number | null>(null);

const assignmentForm = useForm({
  courier_id: null as number | null,
});

const handleAssign = (orderId: number, courierId: string | number | null) => {
  assignmentForm.courier_id = courierId ? Number(courierId) : null;
  assignmentForm.patch(assignRoute({ order: orderId }).url, {
    preserveScroll: true,
  });
};

const getOrdersForCourier = (courierId: number) => {
  return props.assignedOrders.filter(order => order.courier_id === courierId);
};

const statusColor = (status: string) => {
  const m: Record<string, string> = {
    pending:       'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
    confirmed:     'bg-blue-500/10 text-blue-600 border-blue-500/20',
    in_production: 'bg-purple-500/10 text-purple-600 border-purple-500/20',
    ready:         'bg-orange-500/10 text-orange-600 border-orange-500/20',
    delivered:     'bg-green-500/10 text-green-600 border-green-500/20',
    cancelled:     'bg-red-500/10 text-red-600 border-red-500/20',
  };
  return m[status] ?? 'bg-gray-500/10 text-gray-600 border-gray-500/20';
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Currier Assignments" />

    <div class="p-6 max-w-screen-2xl mx-auto space-y-8">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Currier Assignments</h1>
          <p class="text-muted-foreground mt-1 text-sm font-medium">Manage order distribution across your delivery team.</p>
        </div>
        
        <div class="flex items-center gap-4 bg-white dark:bg-sidebar p-3 rounded-2xl shadow-sm border border-sidebar-border/60">
            <div class="flex items-center gap-3 px-4 py-1 border-r border-sidebar-border/60">
                <div class="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                    <Users2 class="h-4 w-4" />
                </div>
                <div>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-none">Curriers</p>
                    <p class="text-sm font-black">{{ couriers.length }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-1">
                <div class="h-8 w-8 rounded-lg bg-orange-500/10 flex items-center justify-center text-orange-600">
                    <Package class="h-4 w-4" />
                </div>
                <div>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-none">Unassigned</p>
                    <p class="text-sm font-black">{{ unassignedOrders.length }}</p>
                </div>
            </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-4 gap-8">
        <!-- Sidebar: Unassigned Orders -->
        <div class="xl:col-span-1 space-y-6">
          <Card class="border-sidebar-border/60 shadow-md h-full min-h-[600px] flex flex-col">
            <CardHeader class="pb-3 border-b bg-muted/30">
              <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                  <AlertCircle class="h-4 w-4 text-orange-500" />
                  Unassigned Queue
                </CardTitle>
                <Badge variant="outline" class="bg-orange-500/5 text-orange-600 border-orange-500/20 text-[10px] font-black">{{ unassignedOrders.length }}</Badge>
              </div>
            </CardHeader>
            <CardContent class="pt-5 flex-1 overflow-y-auto space-y-4">
              <div v-if="unassignedOrders.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="h-12 w-12 rounded-full bg-green-500/10 flex items-center justify-center text-green-600 mb-3">
                  <Package class="h-6 w-6" />
                </div>
                <p class="text-xs font-bold text-muted-foreground">All orders assigned!</p>
              </div>
              
              <div v-for="order in unassignedOrders" :key="order.id" class="p-4 rounded-xl border border-sidebar-border/60 bg-white dark:bg-sidebar hover:border-primary/40 transition-all shadow-sm group">
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <p class="text-xs font-black group-hover:text-primary transition-colors">#{{ order.order_number }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ order.created_at_human }}</p>
                  </div>
                  <Badge :class="['px-2 py-0.5 text-[9px] font-black uppercase rounded-lg border', statusColor(order.status)]">
                    {{ order.status }}
                  </Badge>
                </div>
                
                <div class="space-y-2 mb-4">
                  <div class="flex items-center gap-2">
                    <UserIcon class="h-3 w-3 text-muted-foreground opacity-60" />
                    <p class="text-[11px] font-bold truncate">{{ order.client?.name }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <MapPin class="h-3 w-3 text-muted-foreground opacity-60" />
                    <p class="text-[10px] text-muted-foreground truncate leading-tight">{{ order.delivery_address }}</p>
                  </div>
                </div>

                <select 
                  :value="order.courier_id ?? ''" 
                  @change="(e) => handleAssign(order.id, (e.target as HTMLSelectElement).value || null)"
                  class="w-full h-9 px-3 text-[11px] font-bold rounded-xl border border-dashed border-sidebar-border bg-transparent outline-none focus:ring-1 focus:ring-primary transition-all"
                >
                  <option value="">Assign to currier...</option>
                  <option v-for="courier in couriers" :key="courier.id" :value="courier.id">
                    {{ courier.name }}
                  </option>
                </select>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Main Workspace: Courier Grid -->
        <div class="xl:col-span-3">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card v-for="courier in couriers" :key="courier.id" class="border-sidebar-border/60 hover:shadow-lg transition-all overflow-hidden flex flex-col h-full">
              <CardHeader class="pb-3 border-b bg-white dark:bg-sidebar">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center transform group-hover:scale-110 transition-transform">
                    <UserIcon class="h-5 w-5 text-primary" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <CardTitle class="text-sm font-black truncate">{{ courier.name }}</CardTitle>
                    <div class="flex items-center gap-1.5 mt-0.5">
                      <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse" v-if="courier.status === 'active'"></div>
                      <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">{{ getOrdersForCourier(courier.id).length }} Orders</p>
                    </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent class="p-4 flex-1 bg-muted/10 overflow-y-auto max-h-[500px] space-y-3">
                <div v-if="getOrdersForCourier(courier.id).length === 0" class="py-12 text-center">
                  <Truck class="h-8 w-8 text-muted-foreground/30 mx-auto mb-2 opacity-50" />
                  <p class="text-[10px] font-bold text-muted-foreground opacity-60">No orders assigned yet.</p>
                </div>
                
                <div v-for="order in getOrdersForCourier(courier.id)" :key="order.id" class="p-3 bg-white dark:bg-sidebar rounded-xl border border-sidebar-border/40 shadow-sm hover:border-primary/20 transition-all flex items-center justify-between group">
                  <div class="flex-1 min-w-0 pr-2">
                    <div class="flex items-center gap-2 mb-1">
                      <p class="text-[11px] font-black group-hover:text-primary transition-colors">#{{ order.order_number }}</p>
                      <Badge :class="['px-1.5 py-0 h-4 text-[8px] font-black uppercase rounded-md border', statusColor(order.status)]">
                        {{ order.status }}
                      </Badge>
                    </div>
                    <div class="flex items-center gap-1.5 max-w-full">
                      <MapPin class="h-2.5 w-2.5 text-muted-foreground opacity-60 shrink-0" />
                      <p class="text-[9px] text-muted-foreground truncate">{{ order.delivery_address }}</p>
                    </div>
                  </div>
                  
                  <select 
                    :value="order.courier_id ?? ''" 
                    @change="(e) => handleAssign(order.id, (e.target as HTMLSelectElement).value || null)"
                    class="w-8 h-8 p-0 border-none bg-muted/50 hover:bg-muted focus:ring-0 rounded-lg shrink-0 text-center flex items-center justify-center outline-none appearance-none cursor-pointer"
                  >
                    <option value="">✕</option>
                    <option v-for="c in couriers.filter(c => c.id !== courier.id)" :key="c.id" :value="c.id">
                      {{ c.name }}
                    </option>
                  </select>
                </div>
              </CardContent>
              <div class="p-3 bg-muted/5 border-t border-sidebar-border/40">
                  <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground">
                      <span>Total Volume</span>
                      <span class="text-foreground tracking-tighter">{{ getOrdersForCourier(courier.id).reduce((sum, o) => sum + Number(o.total_amount), 0).toFixed(2) }} TJS</span>
                  </div>
              </div>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: hsl(var(--muted-foreground) / 0.1);
  border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
  background: hsl(var(--muted-foreground) / 0.2);
}
</style>
