<script setup lang="ts">
import { index, edit, show } from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    User as UserIcon, Mail, Phone, Shield, Calendar,
    ShoppingCart, TrendingUp, TrendingDown, Minus, Package,
    Wallet, XCircle, CheckCircle2, Clock, Truck,
    BarChart3, Star, PhoneCall, PhoneIncoming, PhoneOff, PhoneMissed,
    MapPin, Building, Plus, X, RotateCcw, ShoppingBag, Loader2
} from 'lucide-vue-next';

interface UserAddress {
    id: number;
    label: string;
    address_line: string;
    city: string | null;
    region: string | null;
    is_default: boolean;
}

interface UserPhone {
    id: number;
    label: string;
    phone: string;
    is_default: boolean;
}

interface ProfileUser {
    id: number;
    name: string;
    email: string;
    phone?: string;
    avatar_url?: string;
    status: string;
    roles: string[];
    is_client: boolean;
    is_staff: boolean;
    is_courier: boolean;
    is_self: boolean;
    created_at: string;
    sip_extension?: string;
    user_profile?: {
        type: string;
        company_name: string | null;
        region: string | null;
        notes: string | null;
    } | null;
    addresses: UserAddress[];
    phones: UserPhone[];
    wallet?: {
        balance: string | number;
        currency: string;
    };
}

interface ClientStats {
    totalOrders: number;
    totalSpent: number;
    totalPaid: number;
    pendingOrders: number;
    deliveredOrders: number;
    cancelledOrders: number;
    thisMonthOrders: number;
}

interface StaffStats {
    totalDelivered: number;
    thisMonthDelivered: number;
    lastMonthDelivered: number;
    deliveryTrend: number;
    totalRevenue: number;
    avgOrderValue: number;
    monthlyDeliveries: Array<{ month: string; total: number }>;
    recentActivity: Array<{
        id: number;
        order_number: string;
        client_name: string;
        status: string;
        statusLabel: string;
        total_amount: number;
        created_at: string;
    }>;
}

interface Transaction {
    id: number;
    type: string;
    amount: string | number;
    status: string;
    notes?: string;
    created_at: string;
}

interface OrderItem {
    id: number;
    product_id: number;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    product?: {
        name: string;
    };
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    total_amount: string | number;
    created_at: string;
    created_at_formatted: string;
    items: OrderItem[];
}

interface PaginatedOrders {
    data: Order[];
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    profileUser: ProfileUser;
    clientStats: ClientStats | null;
    staffStats: StaffStats | null;
    callHistory: any[];
    transactions: Transaction[];
    orders: PaginatedOrders;
    statuses: Record<string, string>;
}>();

const isDepositModalOpen = ref(false);
const depositForm = useForm({
    amount: 100,
    notes: 'Admin deposit',
});

const submitDeposit = () => {
    depositForm.post(`/users/${props.profileUser.id}/wallet/deposit`, {
        onSuccess: () => {
            isDepositModalOpen.value = false;
            depositForm.reset();
        },
    });
};

const allOrders = ref<Order[]>([...props.orders.data]);
const nextPageUrl = ref<string | null>(props.orders.next_page_url);
const isLoadingMore = ref(false);
const loadMoreTrigger = ref<HTMLElement | null>(null);

const loadMoreOrders = async () => {
    if (!nextPageUrl.value || isLoadingMore.value) return;
    isLoadingMore.value = true;
    try {
        const response = await axios.get(nextPageUrl.value);
        allOrders.value.push(...response.data.data);
        nextPageUrl.value = response.data.next_page_url;
    } catch (error) {
        console.error('Failed to load more orders:', error);
    } finally {
        isLoadingMore.value = false;
    }
};

useIntersectionObserver(loadMoreTrigger, ([{ isIntersecting }]) => {
    if (isIntersecting) loadMoreOrders();
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: index().url },
    { title: props.profileUser.name, href: show({ user: props.profileUser.id }).url },
];

const fmt = (n: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n);

const fmtDuration = (secs: number) => {
    if (secs < 60) return `${secs}s`;
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return s > 0 ? `${m}m ${s}s` : `${m}m`;
};

const callStatusColor = (status: string) => {
    const m: Record<string, string> = {
        answered:    'bg-green-500/10 text-green-600 border-green-500/20',
        missed:      'bg-red-500/10 text-red-600 border-red-500/20',
        rejected:    'bg-orange-500/10 text-orange-600 border-orange-500/20',
        unanswered:  'bg-gray-500/10 text-gray-600 border-gray-500/20',
    };
    return m[status] ?? 'bg-gray-500/10 text-gray-600 border-gray-500/20';
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

const trendClass = (n: number) =>
    n > 0 ? 'text-green-600' : n < 0 ? 'text-red-500' : 'text-muted-foreground';

// Compute bar width % for monthly chart
const maxDeliveries = props.staffStats
    ? Math.max(...(props.staffStats.monthlyDeliveries.map((m: any) => m.total) || [1]), 1)
    : 1;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${profileUser.name} — Profile`" />

        <div class="p-6 max-w-screen-2xl mx-auto space-y-8">

            <!-- ── Profile Header ────────────────────────────────────────── -->
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 bg-white dark:bg-sidebar p-6 rounded-2xl shadow-sm border border-sidebar-border/60">
                <div class="flex items-start gap-5">
                    <!-- Avatar -->
                    <div class="relative">
                        <div v-if="!profileUser.avatar_url" class="h-20 w-20 rounded-2xl bg-primary/10 flex items-center justify-center ring-2 ring-border shadow-inner">
                            <Building v-if="profileUser.user_profile?.type === 'company'" class="h-10 w-10 text-primary opacity-80" />
                            <UserIcon v-else class="h-10 w-10 text-primary opacity-80" />
                        </div>
                        <img
                            v-else
                            :src="profileUser.avatar_url"
                            :alt="profileUser.name"
                            class="h-20 w-20 rounded-2xl object-cover ring-2 ring-border shadow-md"
                        />
                        <span
                            class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full border-4 border-white dark:border-sidebar shadow-sm"
                            :class="profileUser.status === 'active' ? 'bg-green-500' : 'bg-gray-400'"
                        />
                    </div>

                    <!-- Info -->
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-extrabold tracking-tight text-foreground">{{ profileUser.name }}</h1>
                            <Badge v-if="profileUser.is_self" variant="outline" class="bg-primary/5 text-primary border-primary/20 backdrop-blur-sm">Your Profile</Badge>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge
                                v-for="role in profileUser.roles"
                                :key="role"
                                variant="secondary"
                                class="text-[10px] font-black uppercase py-0.5 px-2 tracking-wider"
                            >
                                <Shield class="mr-1.5 h-3 w-3 opacity-60" />
                                {{ role }}
                            </Badge>
                            <span class="text-[10px] font-bold text-muted-foreground ml-1 uppercase tracking-widest flex items-center gap-1.5 opacity-60">
                                <Calendar class="h-3 w-3" />
                                Since {{ profileUser.created_at }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    <Button variant="outline" as-child size="sm" class="rounded-xl h-10 px-4">
                        <Link :href="edit({ user: profileUser.id }).url">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Profile
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <!-- ════ SIDEBAR COLUMN ════ -->
                <div class="space-y-6">
                    
                    <!-- Contact Details -->
                    <Card class="overflow-hidden border-sidebar-border/60">
                        <CardHeader class="pb-3 border-b bg-muted/30">
                            <CardTitle class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                <UserIcon class="h-4 w-4" />
                                Contact Profile
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="pt-5 space-y-5">
                            <div>
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-1.5 opacity-70">Email Address</p>
                                <div class="flex items-center gap-2 group">
                                    <Mail class="h-4 w-4 text-primary opacity-60" />
                                    <p class="text-sm font-medium">{{ profileUser.email }}</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-2.5 opacity-70">Phone Numbers</p>
                                <div v-if="profileUser.phones && profileUser.phones.length > 0" class="space-y-2.5">
                                    <div v-for="phone in profileUser.phones" :key="phone.id" class="flex items-center justify-between group p-2 rounded-xl hover:bg-muted/50 transition-colors border border-transparent hover:border-sidebar-border/50">
                                        <div class="flex items-center gap-3">
                                            <div class="bg-primary/10 p-2 rounded-lg text-primary">
                                                <Phone class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold flex items-center gap-2">
                                                    {{ phone.phone }}
                                                    <span v-if="phone.is_default" class="text-[9px] bg-green-500/10 text-green-600 px-1.5 py-0.5 rounded uppercase font-black tracking-tighter border border-green-500/20">Primary</span>
                                                </p>
                                                <p class="text-[10px] text-muted-foreground">{{ phone.label }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="profileUser.phone" class="flex items-center gap-3 p-2 rounded-xl border border-sidebar-border/40">
                                    <div class="bg-primary/10 p-2 rounded-lg text-primary">
                                        <Phone class="h-4 w-4" />
                                    </div>
                                    <p class="text-sm font-bold">{{ profileUser.phone }}</p>
                                </div>
                                <p v-else class="text-xs text-muted-foreground italic pl-2">No phone numbers assigned.</p>
                            </div>

                            <div v-if="profileUser.sip_extension">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-1.5 opacity-70">SIP Extension</p>
                                <div class="flex items-center gap-2">
                                    <div class="bg-orange-500/10 p-2 rounded-lg text-orange-600">
                                        <PhoneCall class="h-4 w-4" />
                                    </div>
                                    <p class="text-sm font-mono font-bold">{{ profileUser.sip_extension }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Addresses -->
                    <Card class="overflow-hidden border-sidebar-border/60">
                        <CardHeader class="pb-3 border-b bg-muted/30">
                            <CardTitle class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                Delivery Addresses
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="pt-5 space-y-4">
                            <div v-if="profileUser.addresses && profileUser.addresses.length > 0" class="space-y-4">
                                <div v-for="address in profileUser.addresses" :key="address.id" class="flex gap-3 items-start border-l-2 border-primary/20 pl-4 py-1.5 group hover:border-primary transition-colors">
                                    <MapPin class="h-5 w-5 text-muted-foreground/60 shrink-0 mt-0.5 group-hover:text-primary transition-colors" />
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <p class="text-sm font-black">{{ address.label }}</p>
                                            <span v-if="address.is_default" class="text-[9px] bg-blue-500/10 text-blue-600 px-1.5 py-0.5 rounded uppercase font-black border border-blue-500/20">Default</span>
                                        </div>
                                        <p class="text-sm text-foreground/80 leading-relaxed">{{ address.address_line }}</p>
                                        <p v-if="address.city || address.region" class="text-[11px] text-muted-foreground mt-1 font-medium">{{ address.city }}{{ address.city && address.region ? ', ' : '' }}{{ address.region }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground italic">No addresses assigned.</p>
                        </CardContent>
                    </Card>

                    <!-- Financial Overview (Clients Only) -->
                    <Card v-if="profileUser.is_client" class="overflow-hidden border-blue-500/20 shadow-blue-500/5">
                        <CardHeader class="pb-3 border-b border-blue-100 dark:border-blue-900/30 bg-blue-50/30 dark:bg-blue-900/10">
                            <CardTitle class="text-xs font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 flex items-center gap-2">
                                <Wallet class="h-4 w-4" />
                                Digital Wallet
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <!-- Balance -->
                            <div class="p-6 text-center border-b border-sidebar-border/40">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-1 opacity-70">Current Balance</p>
                                <div class="flex items-baseline justify-center gap-1.5">
                                    <span class="text-3xl font-black tracking-tighter">{{ fmt(Number(profileUser.wallet?.balance || 0)) }}</span>
                                    <span class="text-xs font-bold text-muted-foreground">{{ profileUser.wallet?.currency || 'TJS' }}</span>
                                </div>
                                <Button variant="outline" size="sm" class="mt-4 w-full h-9 rounded-xl border-blue-200 hover:bg-blue-50 hover:text-blue-600 transition-all font-bold gap-2" @click="isDepositModalOpen = true">
                                    <Plus class="h-4 w-4" />
                                    Manual Deposit
                                </Button>
                            </div>

                            <!-- Recent Transactions -->
                            <div class="p-5">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-4 opacity-70">Recent Transactions</p>
                                <div v-if="transactions.length === 0" class="py-4 text-center text-xs text-muted-foreground italic">
                                    No transaction history.
                                </div>
                                <ul v-else class="space-y-3">
                                    <li v-for="tx in transactions" :key="tx.id" class="flex items-center justify-between group">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-full" :class="{
                                                'bg-green-100 text-green-600 dark:bg-green-900/30': tx.type === 'deposit' || tx.type === 'refund',
                                                'bg-red-100 text-red-600 dark:bg-red-900/30': tx.type === 'withdrawal' || tx.type === 'payment'
                                            }">
                                                <Plus v-if="tx.type === 'deposit'" class="h-3 w-3" />
                                                <X v-else-if="tx.type === 'withdrawal' || tx.type === 'payment'" class="h-3 w-3" />
                                                <RotateCcw v-else class="h-3 w-3" />
                                            </div>
                                            <div>
                                                <p class="text-xs font-black capitalize">{{ tx.type }}</p>
                                                <p class="text-[9px] text-muted-foreground">{{ tx.created_at }}</p>
                                            </div>
                                        </div>
                                        <p class="text-xs font-black" :class="tx.type === 'deposit' ? 'text-green-600' : 'text-red-500'">
                                            {{ tx.type === 'deposit' ? '+' : '-' }}{{ fmt(Number(tx.amount)) }}
                                        </p>
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                </div>

                <!-- ════ MAIN CONTENT COLUMN ════ -->
                <div class="xl:col-span-2 space-y-8">

                    <!-- CLIENT STATS SECTION -->
                    <template v-if="clientStats">
                        <div class="grid gap-4 grid-cols-2 md:grid-cols-4">
                            <Card class="border-sidebar-border/50 shadow-none">
                                <CardContent class="pt-6">
                                    <div class="flex flex-col gap-2">
                                        <div class="h-9 w-9 rounded-xl bg-blue-500/10 flex items-center justify-center">
                                            <ShoppingCart class="h-5 w-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-tighter">Total Orders</p>
                                            <p class="text-2xl font-black">{{ clientStats.totalOrders }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card class="border-sidebar-border/50 shadow-none">
                                <CardContent class="pt-6">
                                    <div class="flex flex-col gap-2">
                                        <div class="h-9 w-9 rounded-xl bg-green-500/10 flex items-center justify-center">
                                            <CheckCircle2 class="h-5 w-5 text-green-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-tighter">Delivered</p>
                                            <p class="text-2xl font-black">{{ clientStats.deliveredOrders }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card class="border-sidebar-border/50 shadow-none">
                                <CardContent class="pt-6">
                                    <div class="flex flex-col gap-2">
                                        <div class="h-9 w-9 rounded-xl bg-yellow-500/10 flex items-center justify-center">
                                            <Clock class="h-5 w-5 text-yellow-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-tighter">Active</p>
                                            <p class="text-2xl font-black">{{ clientStats.pendingOrders }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card class="border-sidebar-border/50 shadow-none">
                                <CardContent class="pt-6">
                                    <div class="flex flex-col gap-2">
                                        <div class="h-9 w-9 rounded-xl bg-primary/10 flex items-center justify-center">
                                            <TrendingUp class="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-tighter">This Month</p>
                                            <p class="text-2xl font-black">{{ clientStats.thisMonthOrders }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- STAFF / COURIER PERFORMANCE SECTION -->
                    <template v-if="staffStats">
                        <div class="grid gap-4 grid-cols-2 md:grid-cols-4">
                            <Card class="border-sidebar-border/50 shadow-none">
                                <CardContent class="pt-6 text-left">
                                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-1 opacity-70">Total Delivered</p>
                                    <p class="text-3xl font-black tracking-tighter">{{ staffStats.totalDelivered }}</p>
                                    <div class="mt-2 flex items-center gap-1 text-[11px]" :class="trendClass(staffStats.deliveryTrend)">
                                        <TrendingUp v-if="staffStats.deliveryTrend > 0" class="h-3 w-3" />
                                        <TrendingDown v-else-if="staffStats.deliveryTrend < 0" class="h-3 w-3" />
                                        {{ Math.abs(staffStats.deliveryTrend) }}% vs last month
                                    </div>
                                </CardContent>
                            </Card>
                            <Card class="border-sidebar-border/50 shadow-none">
                                <CardContent class="pt-6 text-left">
                                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-1 opacity-70">Revenue Handled</p>
                                    <p class="text-2xl font-black tracking-tighter">{{ fmt(staffStats.totalRevenue) }}</p>
                                    <p class="text-[10px] text-muted-foreground font-medium mt-1">Total across all orders</p>
                                </CardContent>
                            </Card>
                            <Card class="border-sidebar-border/50 shadow-none md:col-span-2">
                                <CardHeader class="pb-2 pt-4 flex flex-row items-center justify-between">
                                    <CardTitle class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground opacity-70">Delivery Trend (6M)</CardTitle>
                                </CardHeader>
                                <CardContent class="pt-0">
                                    <div class="flex items-end gap-1.5 h-16 pt-2">
                                        <div v-for="m in staffStats.monthlyDeliveries" :key="m.month" class="group relative flex-1">
                                            <div class="w-full bg-primary/20 rounded-t-sm transition-all group-hover:bg-primary/40" :style="{ height: `${(m.total / maxDeliveries) * 100}%` }"></div>
                                            <!-- Tooltip -->
                                            <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-popover text-popover-foreground text-[9px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity font-bold shadow-sm border whitespace-nowrap z-10">
                                                {{ m.total }} calls
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- Order History (Unified for everyone) -->
                    <Card class="border-sidebar-border/60">
                        <CardHeader class="flex flex-row items-center justify-between gap-4 border-b bg-muted/20 py-5">
                            <div>
                                <CardTitle class="text-lg font-black flex items-center gap-2">
                                    <ShoppingBag class="h-5 w-5 text-primary" />
                                    Order History
                                </CardTitle>
                                <CardDescription class="text-xs">Comprehensive log of all orders related to this user.</CardDescription>
                            </div>
                            <Link href="/orders" class="text-xs font-bold text-primary hover:underline">View All Orders</Link>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="orders.total === 0" class="p-16 text-center">
                                <ShoppingBag class="h-10 w-10 text-muted-foreground/30 mx-auto mb-4" />
                                <p class="text-muted-foreground text-sm font-medium">No order history found for this account.</p>
                            </div>
                            <div v-else>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left">
                                        <thead>
                                            <tr class="bg-muted/30 border-b border-sidebar-border/50">
                                                <th class="px-6 py-4 text-[10px] font-black text-muted-foreground uppercase tracking-widest">Order</th>
                                                <th class="px-6 py-4 text-[10px] font-black text-muted-foreground uppercase tracking-widest">Status</th>
                                                <th class="px-6 py-4 text-[10px] font-black text-muted-foreground uppercase tracking-widest">Details</th>
                                                <th class="px-6 py-4 text-[10px] font-black text-muted-foreground uppercase tracking-widest text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-sidebar-border/50">
                                            <tr v-for="order in allOrders" :key="order.id" class="hover:bg-muted/20 transition-colors group">
                                                <td class="px-6 py-5">
                                                    <Link :href="`/orders/${order.id}`" class="flex flex-col hover:underline">
                                                        <span class="text-sm font-black text-foreground group-hover:text-primary transition-colors">#{{ order.order_number }}</span>
                                                        <span class="text-[10px] text-muted-foreground font-medium group-hover:text-muted-foreground/80">{{ order.created_at_formatted }}</span>
                                                    </Link>
                                                </td>
                                                <td class="px-6 py-5">
                                                    <Badge :class="['px-2 py-0.5 text-[10px] font-black uppercase rounded-lg border-2', statusColor(order.status)]">
                                                        {{ order.status }}
                                                    </Badge>
                                                </td>
                                                <td class="px-6 py-5">
                                                    <div class="flex items-center gap-2 group-hover:translate-x-0.5 transition-transform">
                                                        <Package class="h-3.5 w-3.5 text-muted-foreground opacity-60" />
                                                        <p class="text-xs text-muted-foreground font-medium truncate max-w-[180px]">
                                                            {{ order.items.map(i => i.product?.name).filter(Boolean).join(', ') || 'No products listed' }}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-5 text-right font-black text-sm tabular-nums">
                                                    {{ order.total_amount }} <span class="text-[10px] text-muted-foreground font-bold">TJS</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Infinite Scroll Loader -->
                                <div v-if="nextPageUrl" ref="loadMoreTrigger" class="py-10 flex flex-col items-center justify-center gap-3 border-t bg-muted/5">
                                    <div v-if="isLoadingMore" class="flex items-center gap-3 text-sm font-bold text-muted-foreground animate-pulse">
                                        <Loader2 class="h-5 w-5 animate-spin text-primary" />
                                        Loading historical records...
                                    </div>
                                    <div v-else class="h-10"></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- CALL HISTORY -->
                    <Card class="border-sidebar-border/60">
                        <CardHeader class="flex flex-row items-center justify-between pb-3 bg-muted/10 border-b">
                            <div>
                                <CardTitle class="text-lg font-black flex items-center gap-2">
                                    <PhoneCall class="h-5 w-5 text-primary" />
                                    Call Activity
                                </CardTitle>
                                <CardDescription class="text-xs">Recent voice communication logs associated with this account.</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="callHistory.length === 0" class="p-16 text-center">
                                <PhoneOff class="h-10 w-10 text-muted-foreground/30 mx-auto mb-4" />
                                <p class="text-muted-foreground text-sm font-medium">No call logs recorded for this user.</p>
                            </div>
                            <div v-else class="max-h-[600px] overflow-y-auto">
                                <ul class="divide-y divide-sidebar-border/50">
                                    <li v-for="call in callHistory" :key="call.id" class="px-6 py-5 hover:bg-muted/30 transition-colors group flex flex-col">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center gap-4">
                                                <!-- Status Icon -->
                                                <div class="h-12 w-12 shrink-0 flex items-center justify-center rounded-2xl border-2 border-sidebar-border/70 group-hover:border-primary/30 transition-all font-bold" :class="{
                                                    'bg-blue-500/5 text-blue-600': call.direction === 'inbound' && call.status === 'answered',
                                                    'bg-green-500/5 text-green-600': call.direction === 'outbound' && call.status === 'answered',
                                                    'bg-red-500/5 text-red-600': call.status !== 'answered',
                                                }">
                                                    <component :is="call.direction === 'inbound' ? PhoneIncoming : (call.status === 'answered' ? PhoneCall : PhoneOff)" class="h-5 w-5" />
                                                </div>

                                                <div>
                                                    <div class="font-bold text-sm text-foreground mb-1">
                                                        {{ call.description }}
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-muted-foreground font-medium">
                                                        <span class="flex items-center gap-1.5 opacity-70">
                                                            <Calendar class="h-3.5 w-3.5" />
                                                            {{ call.created_at }}
                                                        </span>
                                                        <Badge :class="['px-2 py-0 h-4 text-[9px] font-black uppercase rounded-md border', callStatusColor(call.status)]">
                                                            {{ call.status }}
                                                        </Badge>
                                                        <span v-if="call.status === 'answered'" class="font-black bg-muted px-2 py-0.5 rounded text-foreground/70">
                                                            {{ fmtDuration(call.duration) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all scale-95 group-hover:scale-100">
                                                <Button v-if="call.phone" variant="ghost" size="icon" as-child class="h-10 w-10 text-primary hover:text-primary hover:bg-primary/5 rounded-2xl border border-sidebar-border/40 shadow-sm">
                                                    <a :href="`tel:${call.phone}`" title="Call Back">
                                                        <PhoneCall class="h-4.5 w-4.5" />
                                                    </a>
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Audio Recording Player -->
                                        <div v-if="call.recording_url" class="mt-4 w-full">
                                            <audio controls preload="none" class="h-10 w-full rounded-xl" :src="call.recording_url"></audio>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                </div>
            </div>

            <!-- Admin Deposit Modal -->
            <div v-if="isDepositModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm animate-in fade-in duration-200">
                <Card class="w-full max-w-md shadow-2xl animate-in zoom-in-95 duration-200 border-sidebar-border/60">
                    <CardHeader class="border-b bg-muted/30">
                        <div class="flex justify-between items-center">
                            <CardTitle class="text-lg font-black flex items-center gap-2">
                                <Wallet class="h-5 w-5 text-blue-600" />
                                Manual Deposit
                            </CardTitle>
                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full" @click="isDepositModalOpen = false">
                                <X class="h-5 w-5" />
                            </Button>
                        </div>
                        <CardDescription>Directly add funds to {{ profileUser.name }}'s digital wallet.</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-6 space-y-6">
                        <div class="space-y-2.5">
                            <label class="text-[10px] font-black text-muted-foreground uppercase tracking-widest pl-1">Deposit Amount ({{ profileUser.wallet?.currency || 'TJS' }})</label>
                            <div class="relative">
                                <Plus class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                                <input v-model="depositForm.amount" type="number" step="1" min="1" class="w-full text-4xl font-black p-6 pl-12 bg-muted/30 border-2 border-sidebar-border rounded-2xl focus:border-primary focus:ring-4 focus:ring-primary/5 outline-none transition-all" />
                            </div>
                        </div>
                        <div class="space-y-2.5">
                            <label class="text-[10px] font-black text-muted-foreground uppercase tracking-widest pl-1">Transaction Note</label>
                            <input v-model="depositForm.notes" type="text" placeholder="e.g., Manual top-up via cash" class="w-full p-4 bg-muted/30 border border-sidebar-border rounded-xl focus:border-primary outline-none transition-all text-sm font-medium" />
                        </div>
                        
                        <div class="p-4 bg-blue-500/5 border border-blue-500/10 rounded-2xl flex items-start gap-4">
                            <Clock class="h-5 w-5 text-blue-600 shrink-0 mt-0.5" />
                            <p class="text-[11px] text-blue-700/80 font-medium leading-relaxed">
                                This action is permanent and will trigger a transaction record. The balance will be updated instantly for the user.
                            </p>
                        </div>

                        <Button @click="submitDeposit" :disabled="depositForm.processing" class="w-full h-14 rounded-2xl font-black text-lg bg-blue-600 hover:bg-blue-700 text-white shadow-xl shadow-blue-600/20 gap-3 transition-all active:scale-[0.98]">
                            <Plus v-if="!depositForm.processing" class="h-6 w-6" />
                            <Loader2 v-else class="h-6 w-6 animate-spin" />
                            {{ depositForm.processing ? 'Processing...' : 'Confirm Deposit' }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
