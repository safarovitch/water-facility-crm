<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { RotateCcw, Pause, Play, XCircle, Package, Calendar, MapPin, Clock } from 'lucide-vue-next';
import { useLocale } from '@/composables/useLocale';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

const { t: tl } = useLocale();
const { t } = useI18n();

interface SubscriptionItem {
  id: number;
  quantity: number;
  product: { id: number; name: string; price: string; sale_price: string };
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  total_amount: string;
  created_at_human: string;
  created_at_formatted: string;
}

interface Subscription {
  id: number;
  status: string;
  frequency: string;
  interval_days: number | null;
  day_of_week: number | null;
  day_of_month: number | null;
  time_slot: string | null;
  delivery_address: string;
  notes: string | null;
  next_delivery_at: string | null;
  last_generated_at: string | null;
  paused_at: string | null;
  cancelled_at: string | null;
  created_at_human: string;
  created_at_formatted: string;
  client: { id: number; name: string; email: string; phone: string | null };
  items: SubscriptionItem[];
}

const props = defineProps<{
  subscription: Subscription;
  recentOrders: Order[];
}>();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('Subscriptions'), href: '/admin/subscriptions' },
  { title: `#${props.subscription.id}`, href: '#' },
]);

const statusBadgeClass: Record<string, string> = {
  active: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400',
  paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-400',
};

const frequencyLabels = computed((): Record<string, string> => ({
  weekly: t('Every week'),
  biweekly: t('Every 2 weeks'),
  monthly: t('Every month'),
  custom: t('Custom interval'),
}));

const daysOfWeek = computed(() => [t('Sunday'), t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday')]);

const orderStatusClass: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400',
  confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-400',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-400',
};
</script>

<template>
  <Head :title="t('Subscription #{id}', { id: subscription.id })" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 container mx-auto px-4 md:px-0">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight">{{ t('Subscription #{id}', { id: subscription.id }) }}</h1>
            <Badge variant="outline" class="capitalize border-transparent font-semibold" :class="statusBadgeClass[subscription.status]">
              {{ t(subscription.status) }}
            </Badge>
          </div>
          <p class="text-sm text-muted-foreground mt-1">
            {{ subscription.client.name }} &middot; {{ t('Created') }} {{ subscription.created_at_human }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Button v-if="subscription.status === 'active'" variant="outline" class="gap-2 text-yellow-600 border-yellow-300 hover:bg-yellow-50" @click="router.patch(`/admin/subscriptions/${subscription.id}/pause`)">
            <Pause class="h-4 w-4" /> {{ t('Pause') }}
          </Button>
          <Button v-if="subscription.status === 'paused'" variant="outline" class="gap-2 text-green-600 border-green-300 hover:bg-green-50" @click="router.patch(`/admin/subscriptions/${subscription.id}/resume`)">
            <Play class="h-4 w-4" /> {{ t('Resume') }}
          </Button>
          <Button v-if="subscription.status !== 'cancelled'" variant="outline" class="gap-2 text-red-600 border-red-300 hover:bg-red-50" @click="router.patch(`/admin/subscriptions/${subscription.id}/cancel`)">
            <XCircle class="h-4 w-4" /> {{ t('Cancel') }}
          </Button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details -->
        <div class="lg:col-span-1 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle class="text-base flex items-center gap-2">
                <RotateCcw class="h-4 w-4 text-muted-foreground" /> {{ t('Schedule') }}
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div>
                <p class="text-xs text-muted-foreground uppercase font-bold tracking-wider">{{ t('Frequency') }}</p>
                <p class="text-sm font-medium mt-0.5">{{ frequencyLabels[subscription.frequency] ?? subscription.frequency }}</p>
                <p v-if="subscription.frequency === 'custom'" class="text-xs text-muted-foreground">{{ t('Every {n} days', { n: subscription.interval_days ?? 0 }) }}</p>
              </div>
              <div v-if="subscription.day_of_week !== null">
                <p class="text-xs text-muted-foreground uppercase font-bold tracking-wider">{{ t('Preferred Day') }}</p>
                <p class="text-sm font-medium mt-0.5">{{ daysOfWeek[subscription.day_of_week] }}</p>
              </div>
              <div v-if="subscription.day_of_month !== null">
                <p class="text-xs text-muted-foreground uppercase font-bold tracking-wider">{{ t('Day of Month') }}</p>
                <p class="text-sm font-medium mt-0.5">{{ subscription.day_of_month }}</p>
              </div>
              <div v-if="subscription.time_slot">
                <p class="text-xs text-muted-foreground uppercase font-bold tracking-wider">{{ t('Time Slot') }}</p>
                <p class="text-sm font-medium mt-0.5 capitalize">{{ subscription.time_slot }}</p>
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase font-bold tracking-wider">{{ t('Next Delivery') }}</p>
                <p class="text-sm font-bold mt-0.5" :class="subscription.next_delivery_at ? 'text-primary' : 'text-muted-foreground'">
                  {{ subscription.next_delivery_at ?? t('Not scheduled') }}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base flex items-center gap-2">
                <MapPin class="h-4 w-4 text-muted-foreground" /> {{ t('Delivery') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p class="text-sm text-gray-700 dark:text-gray-300">{{ subscription.delivery_address }}</p>
              <p v-if="subscription.notes" class="text-xs text-muted-foreground mt-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-100 dark:border-yellow-900/40">
                {{ subscription.notes }}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base flex items-center gap-2">
                <Package class="h-4 w-4 text-muted-foreground" /> {{ t('Products') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div v-for="item in subscription.items" :key="item.id" class="flex items-center justify-between">
                  <span class="text-sm font-medium">{{ tl(item.product.name) }}</span>
                  <span class="text-sm font-bold text-muted-foreground">x{{ item.quantity }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Generated Orders -->
        <div class="lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-base flex items-center gap-2">
                <Calendar class="h-4 w-4 text-muted-foreground" /> {{ t('Generated Orders') }}
              </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
              <!-- Desktop -->
              <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left">
                  <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                      <th class="px-6 py-3 font-semibold">{{ t('Order #') }}</th>
                      <th class="px-6 py-3 font-semibold">{{ t('Status') }}</th>
                      <th class="px-6 py-3 font-semibold text-right">{{ t('Total') }}</th>
                      <th class="px-6 py-3 font-semibold text-right">{{ t('Created') }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-border/60">
                    <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-muted/40 transition-colors">
                      <td class="px-6 py-3">
                        <Link :href="`/admin/orders/${order.id}`" class="font-mono font-bold text-primary hover:underline">{{ order.order_number }}</Link>
                      </td>
                      <td class="px-6 py-3">
                        <Badge variant="outline" class="capitalize border-transparent text-[10px] font-semibold" :class="orderStatusClass[order.status] ?? ''">{{ t(order.status) }}</Badge>
                      </td>
                      <td class="px-6 py-3 text-right font-bold">{{ order.total_amount }}</td>
                      <td class="px-6 py-3 text-right text-xs text-muted-foreground">{{ order.created_at_human }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Mobile -->
              <div class="md:hidden divide-y divide-border/60">
                <Link v-for="order in recentOrders" :key="order.id" :href="`/admin/orders/${order.id}`" class="block p-4 active:bg-muted/30 transition-colors">
                  <div class="flex items-center justify-between">
                    <span class="font-mono font-bold text-primary">{{ order.order_number }}</span>
                    <Badge variant="outline" class="capitalize border-transparent text-[10px] font-semibold" :class="orderStatusClass[order.status] ?? ''">{{ t(order.status) }}</Badge>
                  </div>
                  <div class="flex items-center justify-between mt-2 text-sm">
                    <span class="text-muted-foreground">{{ order.created_at_human }}</span>
                    <span class="font-bold">{{ order.total_amount }}</span>
                  </div>
                </Link>
              </div>

              <div v-if="recentOrders.length === 0" class="p-8 text-center text-muted-foreground">
                <Clock class="h-8 w-8 mx-auto mb-2 opacity-40" />
                <p class="text-sm">{{ t('No orders generated yet.') }}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
