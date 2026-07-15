<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    LayoutGrid,
    ShoppingCart,
    Users2,
    Truck,
    ClipboardList,
    ArrowRight,
    ToggleRight
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();
const user = computed(() => page.props.auth.user);
const can = computed(() => page.props.auth.can ?? {});
const pendingCount = computed(() => (page.props as any).pending_orders_count ?? 0);
const userRoles = computed(() => user.value?.roles ?? []);
const isCourier = computed(() => userRoles.value.map((r: string) => r.toLowerCase()).includes('currier'));

const switchToAdmin = () => {
    router.post('/admin/switch-mode');
};

const quickActions = computed(() => {
    const actions = [];

    actions.push({
        title: isCourier.value && !can.value.assignCurriers ? t('My Deliveries') : t('Orders'),
        description: isCourier.value && !can.value.assignCurriers ? t('Assigned delivery tasks') : t('View and manage orders'),
        href: '/admin/orders/index',
        icon: ShoppingCart,
        badge: pendingCount.value > 0 ? pendingCount.value : null,
    });

    // Assignments overview is for courier managers / admins only.
    if (can.value.assignCurriers) {
        actions.push({
            title: t('Currier Assignments'),
            description: t('Assign couriers to orders'),
            href: '/admin/orders/assignments',
            icon: Truck,
            badge: null,
        });
    }

    if (can.value.manageClients) {
        actions.push({
            title: t('Clients'),
            description: t('Customer database'),
            href: '/admin/clients/index',
            icon: Users2,
            badge: null,
        });
    }

    actions.push({
        title: can.value.viewAdminStats ? t('Admin Dashboard') : t('My Dashboard'),
        description: can.value.viewAdminStats ? t('Full statistics & metrics') : t('Your deliveries & earnings'),
        href: '/admin',
        icon: LayoutGrid,
        badge: null,
    });

    return actions;
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight">{{ t('Hello, {name}', { name: user.name }) }}</h1>
                <p class="text-muted-foreground mt-1">{{ t('Switch to admin mode for full access to management tools.') }}</p>
            </div>
            <Button @click="switchToAdmin" class="gap-2 h-11 md:h-10 w-full md:w-auto">
                <ToggleRight class="h-4 w-4" />
                {{ t('Switch to Admin Mode') }}
            </Button>
        </div>

        <div v-if="pendingCount > 0" class="rounded-xl border border-primary/20 bg-primary/5 p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-primary/20 p-2.5">
                    <ClipboardList class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <p class="font-bold text-primary">{{ t('{count} pending orders', { count: pendingCount }) }}</p>
                    <p class="text-xs text-primary/70">{{ t('Awaiting processing or dispatch') }}</p>
                </div>
            </div>
            <Link href="/admin/orders/index?status=pending">
                <Button variant="outline" size="sm" class="border-primary/30 text-primary">
                    {{ t('View') }} <ArrowRight class="h-3 w-3 ml-1" />
                </Button>
            </Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Link v-for="action in quickActions" :key="action.href" :href="action.href" class="block">
                <Card class="h-full hover:shadow-md hover:border-primary/30 transition-all active:scale-[0.98]">
                    <CardContent class="p-5 flex items-start gap-4">
                        <div class="rounded-lg bg-muted p-2.5 shrink-0">
                            <component :is="action.icon" class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-semibold text-foreground">{{ action.title }}</p>
                                <Badge v-if="action.badge" variant="default" class="h-5 px-1.5 text-[10px]">{{ action.badge }}</Badge>
                            </div>
                            <p class="text-sm text-muted-foreground mt-0.5">{{ action.description }}</p>
                        </div>
                        <ArrowRight class="h-4 w-4 text-muted-foreground shrink-0 mt-1" />
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
