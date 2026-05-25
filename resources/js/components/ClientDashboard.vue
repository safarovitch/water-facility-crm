<script setup lang="ts">
import { type Order, type User } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/vue3';
import { 
    ShoppingCart, 
    Package, 
    Truck, 
    CheckCircle2, 
    Clock, 
    Phone, 
    User as UserIcon,
    ArrowRight,
    MapPin,
    Calendar
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';

const { t } = useLocale();

const props = defineProps<{
    auth: { user: User };
    activeOrder?: Order;
    orderHistory: Order[];
}>();

const orderStatusSteps = [
    { id: 'pending', label: 'Accepted', icon: Clock },
    { id: 'confirmed', label: 'Confirmed', icon: CheckCircle2 },
    { id: 'in_production', label: 'Preparing', icon: Package },
    { id: 'ready', label: 'Ready', icon: Truck },
    { id: 'delivered', label: 'Delivered', icon: CheckCircle2 },
];

const currentStepIndex = computed(() => {
    if (!props.activeOrder) return -1;
    return orderStatusSteps.findIndex(step => step.id === props.activeOrder?.status);
});

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
        confirmed: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        in_production: 'bg-purple-500/10 text-purple-500 border-purple-500/20',
        ready: 'bg-orange-500/10 text-orange-500 border-orange-500/20',
        delivered: 'bg-green-500/10 text-green-500 border-green-500/20',
        cancelled: 'bg-red-500/10 text-red-500 border-red-500/20',
    };
    return colors[status] || 'bg-gray-500/10 text-gray-500 border-gray-500/20';
};

const facilityPhone = '+992884238383';

</script>

<template>
    <div class="space-y-8">
        <!-- Header Section -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Welcome back, {{ auth.user.name }}!</h1>
                <p class="text-muted-foreground mt-1">Manage your water deliveries and account settings.</p>
            </div>
            <div class="flex items-center gap-3">
                <Button variant="outline" as-child>
                    <a :href="`tel:${facilityPhone}`" class="flex items-center gap-2">
                        <Phone class="h-4 w-4" />
                        Call Facility
                    </a>
                </Button>
                <Button as-child size="lg" class="bg-blue-600 hover:bg-blue-700">
                    <Link href="/orders/create" class="flex items-center gap-2">
                        <ShoppingCart class="h-5 w-5" />
                        Order Water
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-12">
            <!-- Active Order Tracking -->
            <Card class="md:col-span-8 overflow-hidden border-blue-100 dark:border-blue-900/30">
                <CardHeader class="border-b bg-blue-50/50 dark:bg-blue-950/10">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Truck class="h-5 w-5 text-blue-600" />
                            Active Delivery
                        </CardTitle>
                        <Badge v-if="activeOrder" :class="getStatusColor(activeOrder.status)">
                            {{ activeOrder.statusLabel || activeOrder.status }}
                        </Badge>
                    </div>
                    <CardDescription v-if="activeOrder">
                        Order #{{ activeOrder.order_number }} • Scheduled for {{ activeOrder.scheduled_delivery_at }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="pt-8 pb-8">
                    <div v-if="activeOrder">
                        <!-- Progress Stepper -->
                        <div class="relative flex justify-between">
                            <div class="absolute top-5 left-0 h-0.5 w-full bg-gray-100 dark:bg-gray-800" />
                            <div 
                                class="absolute top-5 left-0 h-0.5 bg-blue-500 transition-all duration-500" 
                                :style="{ width: `${(currentStepIndex / (orderStatusSteps.length - 1)) * 100}%` }"
                            />
                            
                            <div v-for="(step, index) in orderStatusSteps" :key="step.id" class="relative z-10 flex flex-col items-center gap-2">
                                <div 
                                    :class="[
                                        'flex h-10 w-10 items-center justify-center rounded-full border-2 transition-colors duration-300',
                                        index <= currentStepIndex ? 'bg-blue-500 border-blue-500 text-white' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-400'
                                    ]"
                                >
                                    <component :is="step.icon" class="h-5 w-5" />
                                </div>
                                <span :class="['text-xs font-medium', index <= currentStepIndex ? 'text-blue-600' : 'text-gray-400']">
                                    {{ step.label }}
                                </span>
                            </div>
                        </div>

                        <!-- Courier Details -->
                        <div v-if="activeOrder.status !== 'pending'" class="mt-12 flex items-center gap-4 rounded-xl border p-4 bg-gray-50/50 dark:bg-gray-900/50">
                            <div class="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <UserIcon class="h-6 w-6 text-blue-600" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold">{{ activeOrder.courier?.name || 'Assigned Courier' }}</p>
                                <p class="text-xs text-muted-foreground">On their way to your location</p>
                            </div>
                            <Button size="sm" variant="ghost" as-child>
                                <a :href="`tel:${activeOrder.courier?.phone || facilityPhone}`" class="flex items-center gap-1">
                                    <Phone class="h-3 w-3" />
                                    Contact
                                </a>
                            </Button>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                            <Package class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold">No active orders</h3>
                        <p class="text-muted-foreground mb-6">You don't have any ongoing deliveries right now.</p>
                        <Button as-child variant="outline">
                            <Link href="/orders/create">Place a new order</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Personal Info Sidebar -->
            <div class="md:col-span-4 space-y-6">
                <!-- Profile Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <UserIcon class="h-5 w-5 text-gray-500" />
                            Profile Info
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full overflow-hidden">
                                <img :src="auth.user.avatar_url" class="h-full w-full object-cover" />
                            </div>
                            <div>
                                <p class="font-medium">{{ auth.user.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ auth.user.email }}</p>
                            </div>
                        </div>
                        <div class="space-y-3 pt-4 border-t">
                            <div class="flex items-center gap-2 text-sm">
                                <Phone class="h-4 w-4 text-muted-foreground" />
                                <span>{{ auth.user.phone || 'No phone added' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <MapPin class="h-4 w-4 text-muted-foreground" />
                                <span class="truncate">Default delivery address</span>
                            </div>
                        </div>
                        <Button variant="ghost" class="w-full mt-4 justify-between" as-child>
                            <Link href="/settings/profile">
                                Edit Profile
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Support Ticket / Contact -->
                <Card class="bg-gray-900 border-gray-800 text-white">
                    <CardContent class="pt-6">
                        <div class="flex flex-col items-center text-center">
                            <Phone class="h-8 w-8 mb-4 text-blue-400" />
                            <h3 class="font-bold mb-1">Need Help?</h3>
                            <p class="text-sm text-gray-400 mb-6">Contact our facility directly for immediate assistance.</p>
                            <Button class="w-full bg-blue-500 hover:bg-blue-600 border-none" as-child>
                                <a :href="`tel:${facilityPhone}`">Call Support</a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Recent History -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle>Recent Orders</CardTitle>
                    <CardDescription>Your last 5 completed or cancelled orders.</CardDescription>
                </div>
                <Button variant="link" as-child>
                    <Link href="/orders">View all orders</Link>
                </Button>
            </CardHeader>
            <CardContent class="p-0 md:p-6">
                <!-- Desktop Table -->
                <div class="hidden md:block relative w-full overflow-auto">
                    <table class="w-full caption-bottom text-sm">
                        <thead>
                            <tr class="border-b transition-colors">
                                <th class="h-10 px-2 text-left align-middle font-medium text-muted-foreground">Order</th>
                                <th class="h-10 px-2 text-left align-middle font-medium text-muted-foreground">Date</th>
                                <th class="h-10 px-2 text-left align-middle font-medium text-muted-foreground">Items</th>
                                <th class="h-10 px-2 text-left align-middle font-medium text-muted-foreground">Total</th>
                                <th class="h-10 px-2 text-left align-middle font-medium text-muted-foreground">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orderHistory" :key="order.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-2 align-middle font-medium">#{{ order.order_number }}</td>
                                <td class="p-2 align-middle">
                                    <div class="flex items-center gap-2">
                                        <Calendar class="h-3 w-3 text-muted-foreground" />
                                        {{ order.created_at_formatted }}
                                    </div>
                                </td>
                                <td class="p-2 align-middle">
                                    <template v-if="order.items && order.items.length > 0">
                                        {{ t(order.items[0].product.name) }}
                                        <span v-if="order.items.length > 1" class="text-xs text-muted-foreground">
                                            +{{ order.items.length - 1 }} more
                                        </span>
                                    </template>
                                </td>
                                <td class="p-2 align-middle">{{ order.total_amount }}</td>
                                <td class="p-2 align-middle">
                                    <Badge :class="getStatusColor(order.status)">
                                        {{ order.statusLabel || order.status }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="orderHistory.length === 0">
                                <td colspan="5" class="p-8 text-center text-muted-foreground italic">
                                    No order history found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-border/60">
                    <div v-for="order in orderHistory" :key="order.id" class="p-4 active:bg-muted/30 transition-colors">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex flex-col">
                                <span class="font-mono font-bold text-primary text-sm">#{{ order.order_number }}</span>
                                <span class="text-xs text-muted-foreground mt-0.5">{{ order.created_at_formatted }}</span>
                            </div>
                            <Badge :class="getStatusColor(order.status)" class="text-[10px] h-5 px-1.5 shrink-0">
                                {{ order.statusLabel || order.status }}
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between mt-3 pt-2 border-t border-dashed border-border/60">
                            <div class="text-xs text-muted-foreground">
                                <template v-if="order.items && order.items.length > 0">
                                    {{ t(order.items[0].product.name) }}
                                    <span v-if="order.items.length > 1"> +{{ order.items.length - 1 }}</span>
                                </template>
                            </div>
                            <span class="text-sm font-bold text-foreground">{{ order.total_amount }}</span>
                        </div>
                    </div>
                    <div v-if="orderHistory.length === 0" class="p-8 text-center text-muted-foreground italic">
                        No order history found.
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
