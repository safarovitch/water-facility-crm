<script setup lang="ts">
/**
 * The daily production plan.
 *
 * Design brief: one number, big, in plain words. Everything that explains the
 * number is available but subordinate to it, and nothing on the page uses
 * statistical vocabulary — "likely orders" rather than "P50", "enough for 41
 * more bottles" rather than a coverage ratio. The forecasting dashboards are
 * where the model is discussed; this page is where a decision gets made.
 */
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, Check, ChevronLeft, ChevronRight, ClipboardCheck, Package } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface DayRow {
    date: string;
    weekday: number;
    confirmed: number;
    predicted: number;
    needed: number;
    opening: number;
    recorded: number;
    to_fill: number;
    closing: number;
}

interface Material {
    id: number;
    name: string;
    unit: string;
    per_bottle: number;
    current_stock: number;
    covers: number;
    is_reusable: boolean;
}

interface ProductPlan {
    product_id: number;
    name: Record<string, string> | string | null;
    ready_now: number;
    has_count: boolean;
    counted_on: string | null;
    needed: number;
    to_fill: number;
    recorded: number;
    confirmed: number;
    predicted: number;
    days: DayRow[];
    materials: Material[];
}

const props = defineProps<{
    from: string;
    to: string;
    plan: {
        from: string;
        to: string;
        is_range: boolean;
        day_count: number;
        products: ProductPlan[];
        totals: { needed: number; to_fill: number; recorded: number; ready: number };
        needs_stock_count: boolean;
    };
}>();

const { t } = useI18n();
const page = usePage();

const canRecord = computed(() => !!page.props.auth.can?.recordProduction);

const breadcrumbs = computed((): BreadcrumbItem[] => [{ title: t('Production'), href: '/admin/production' }]);

const availableLocales = (page.props.available_locales as string[]) ?? [];

const productName = (name: Record<string, string> | string | null): string => {
    if (!name) return t('Product');
    if (typeof name === 'string') return name;
    for (const locale of availableLocales) if (name[locale]) return name[locale];
    return Object.values(name)[0] || t('Product');
};

const iso = (d: Date) => d.toLocaleDateString('en-CA');
const today = iso(new Date());
const tomorrow = iso(new Date(Date.now() + 86400000));

const go = (from: string, to: string) => router.get('/admin/production', { from, to }, { preserveState: true, preserveScroll: true });

const shiftDay = (delta: number) => {
    const start = new Date(props.from);
    const end = new Date(props.to);
    start.setDate(start.getDate() + delta);
    end.setDate(end.getDate() + delta);
    go(iso(start), iso(end));
};

const pickWeek = () => {
    const start = new Date(props.from);
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    go(iso(start), iso(end));
};

const longDate = (isoDate: string) => new Date(isoDate).toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' });

const shortDate = (isoDate: string) => new Date(isoDate).toLocaleDateString('ru-RU', { weekday: 'short', day: 'numeric', month: 'short' });

const rangeLabel = computed(() => (props.plan.is_range ? `${longDate(props.from)} — ${longDate(props.to)}` : longDate(props.from)));

const isToday = computed(() => !props.plan.is_range && props.from === today);

// --- recording -------------------------------------------------------------

const recordingFor = ref<number | null>(null);
const recordForm = useForm({ product_id: 0, date: props.from, units: 0, notes: '' });

const openRecord = (product: ProductPlan) => {
    recordingFor.value = product.product_id;
    recordForm.product_id = product.product_id;
    // Default to the day being viewed, or today when looking at a range.
    recordForm.date = props.plan.is_range ? today : props.from;
    recordForm.units = product.recorded || product.to_fill;
};

const submitRecord = () => {
    recordForm.post('/admin/production/record', {
        preserveScroll: true,
        onSuccess: () => {
            recordingFor.value = null;
            recordForm.reset('notes');
        },
    });
};

// --- stock count -----------------------------------------------------------

const countingFor = ref<number | null>(null);
const countForm = useForm({ product_id: 0, units: 0, date: today });

const openCount = (product: ProductPlan) => {
    countingFor.value = product.product_id;
    countForm.product_id = product.product_id;
    countForm.units = product.ready_now;
    countForm.date = today;
};

const submitCount = () => {
    countForm.post('/admin/production/count', {
        preserveScroll: true,
        onSuccess: () => (countingFor.value = null),
    });
};

// Keep the record form's date in step when the user navigates dates.
watch(
    () => props.from,
    (value) => {
        recordForm.date = props.plan.is_range ? today : value;
    },
);

const expanded = ref<number | null>(null);
const toggle = (id: number) => (expanded.value = expanded.value === id ? null : id);
</script>

<template>
    <Head :title="t('Production')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto max-w-3xl space-y-4 px-4 pb-10 md:px-0">
            <!-- Date bar: the only control most users will ever touch. -->
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <Button variant="outline" size="icon" :aria-label="t('Previous')" @click="shiftDay(-1)">
                        <ChevronLeft class="h-5 w-5" />
                    </Button>
                    <div class="text-center">
                        <div class="text-lg font-bold text-foreground first-letter:uppercase md:text-xl">{{ rangeLabel }}</div>
                        <div v-if="isToday" class="text-xs font-medium tracking-wider text-sky-600 uppercase dark:text-sky-400">
                            {{ t('Today') }}
                        </div>
                    </div>
                    <Button variant="outline" size="icon" :aria-label="t('Next')" @click="shiftDay(1)">
                        <ChevronRight class="h-5 w-5" />
                    </Button>
                </div>

                <div class="flex flex-wrap justify-center gap-2">
                    <Button :variant="!plan.is_range && from === today ? 'default' : 'outline'" size="sm" @click="go(today, today)">
                        {{ t('Today') }}
                    </Button>
                    <Button :variant="!plan.is_range && from === tomorrow ? 'default' : 'outline'" size="sm" @click="go(tomorrow, tomorrow)">
                        {{ t('Tomorrow') }}
                    </Button>
                    <Button :variant="plan.is_range ? 'default' : 'outline'" size="sm" @click="pickWeek">
                        <CalendarDays class="mr-1.5 h-4 w-4" />{{ t('7 days') }}
                    </Button>
                </div>
            </div>

            <p v-if="!plan.products.length" class="py-12 text-center text-sm text-muted-foreground">
                {{ t('No products to plan for yet.') }}
            </p>

            <div v-for="product in plan.products" :key="product.product_id" class="space-y-3">
                <!-- The answer. -->
                <Card class="overflow-hidden shadow-sm">
                    <CardContent class="p-0">
                        <div
                            class="px-6 py-8 text-center"
                            :class="product.to_fill > 0 ? 'bg-sky-50 dark:bg-sky-950/30' : 'bg-emerald-50 dark:bg-emerald-950/30'"
                        >
                            <template v-if="product.to_fill > 0">
                                <div class="text-sm font-semibold tracking-wider text-sky-700 uppercase dark:text-sky-300">
                                    {{ t('Fill') }}
                                </div>
                                <div class="my-1 text-6xl font-black text-sky-900 tabular-nums md:text-7xl dark:text-sky-100">
                                    {{ product.to_fill }}
                                </div>
                                <div class="text-lg font-medium text-sky-800 dark:text-sky-200">{{ productName(product.name) }}</div>
                            </template>
                            <template v-else>
                                <Check class="mx-auto mb-2 h-12 w-12 text-emerald-600 dark:text-emerald-400" />
                                <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                                    {{ t('Nothing to fill') }}
                                </div>
                                <div class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                                    {{ t('Stock already covers this period.') }}
                                </div>
                            </template>
                        </div>

                        <!-- Where the number comes from, in three lines. -->
                        <div class="space-y-2 px-6 py-4 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{{ t('Needed in total') }}</span>
                                <span class="font-semibold text-foreground tabular-nums">{{ product.needed }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{{ t('Already filled and ready') }}</span>
                                <span class="font-semibold text-foreground tabular-nums">− {{ product.ready_now }}</span>
                            </div>
                            <div v-if="product.recorded > 0" class="flex items-center justify-between">
                                <span class="text-muted-foreground">{{ t('Filled during this period') }}</span>
                                <span class="font-semibold text-foreground tabular-nums">− {{ product.recorded }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-border pt-2">
                                <span class="font-semibold text-foreground">{{ t('To fill') }}</span>
                                <span class="text-lg font-bold text-foreground tabular-nums">{{ product.to_fill }}</span>
                            </div>
                        </div>

                        <div v-if="canRecord" class="border-t border-border px-6 py-3">
                            <Button v-if="recordingFor !== product.product_id" class="w-full" @click="openRecord(product)">
                                <ClipboardCheck class="mr-2 h-4 w-4" />{{ t('Record what we filled') }}
                            </Button>

                            <form v-else class="space-y-3" @submit.prevent="submitRecord">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-foreground">
                                        {{ t('How many did we fill?') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <Input v-model.number="recordForm.units" type="number" min="0" class="h-11 text-lg" autofocus />
                                        <Input v-model="recordForm.date" type="date" class="h-11 w-40" />
                                    </div>
                                    <p v-if="recordForm.errors.units" class="mt-1 text-xs text-red-600">{{ recordForm.errors.units }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <Button type="submit" class="flex-1" :disabled="recordForm.processing">{{ t('Save') }}</Button>
                                    <Button type="button" variant="outline" @click="recordingFor = null">{{ t('Cancel') }}</Button>
                                </div>
                            </form>
                        </div>
                    </CardContent>
                </Card>

                <!--
                  Without a stock count the ready figure has no anchor, so the
                  plan silently assumes an empty warehouse and over-fills. Ask
                  for the count rather than quietly being wrong.
                -->
                <Card v-if="!product.has_count" class="border-amber-300 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                    <CardContent class="p-4">
                        <div class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                            {{ t('How many bottles are ready right now?') }}
                        </div>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                            {{ t('Nobody has counted the finished stock yet, so the plan assumes there are none and may ask you to fill too many.') }}
                        </p>
                        <form v-if="canRecord" class="mt-3 flex gap-2" @submit.prevent="submitCount">
                            <Input
                                :model-value="countingFor === product.product_id ? countForm.units : 0"
                                type="number"
                                min="0"
                                class="h-11 max-w-[140px] text-lg"
                                @focus="openCount(product)"
                                @update:model-value="(v: string | number) => (countForm.units = Number(v))"
                            />
                            <Button type="submit" :disabled="countForm.processing">{{ t('Save count') }}</Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Breakdown, deliberately below the fold of the answer. -->
                <Card class="shadow-sm">
                    <CardContent class="p-4">
                        <button type="button" class="flex w-full items-center justify-between text-left" @click="toggle(product.product_id)">
                            <span class="text-sm font-semibold text-foreground">{{ t('Where this number comes from') }}</span>
                            <ChevronRight
                                class="h-4 w-4 text-muted-foreground transition-transform"
                                :class="expanded === product.product_id ? 'rotate-90' : ''"
                            />
                        </button>

                        <div v-if="expanded === product.product_id" class="mt-3 space-y-4 border-t border-border pt-3">
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">{{ t('Confirmed orders') }}</span>
                                    <span class="font-medium text-foreground tabular-nums">{{ product.confirmed }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">{{ t('Likely orders (not placed yet)') }}</span>
                                    <span class="font-medium text-foreground tabular-nums">{{ product.predicted }}</span>
                                </div>
                            </div>

                            <div v-if="plan.is_range" class="overflow-x-auto">
                                <table class="w-full min-w-[420px] text-sm">
                                    <thead>
                                        <tr class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase">
                                            <th class="pb-2 text-left font-semibold">{{ t('Day') }}</th>
                                            <th class="pb-2 text-right font-semibold">{{ t('Needed') }}</th>
                                            <th class="pb-2 text-right font-semibold">{{ t('In stock') }}</th>
                                            <th class="pb-2 text-right font-semibold">{{ t('Fill') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-for="day in product.days" :key="day.date">
                                            <td class="py-2 text-foreground first-letter:uppercase">{{ shortDate(day.date) }}</td>
                                            <td class="py-2 text-right text-muted-foreground tabular-nums">{{ day.needed }}</td>
                                            <td class="py-2 text-right text-muted-foreground tabular-nums">{{ day.opening }}</td>
                                            <td
                                                class="py-2 text-right font-semibold tabular-nums"
                                                :class="day.to_fill > 0 ? 'text-sky-700 dark:text-sky-300' : 'text-muted-foreground'"
                                            >
                                                {{ day.to_fill }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Materials: one sentence, then the detail. -->
                <Card v-if="product.materials.length" class="shadow-sm">
                    <CardContent class="p-4">
                        <div class="flex items-start gap-3">
                            <Package class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <div
                                    class="text-sm font-semibold"
                                    :class="product.materials[0].covers < product.to_fill ? 'text-red-700 dark:text-red-400' : 'text-foreground'"
                                >
                                    {{ t('Materials cover') }} {{ product.materials[0].covers }} {{ t('more bottles') }}
                                    <template v-if="product.materials[0].covers < product.to_fill"> — {{ t('not enough for this plan') }} </template>
                                </div>
                                <div class="mt-2 space-y-1">
                                    <div
                                        v-for="material in product.materials"
                                        :key="material.id"
                                        class="flex items-center justify-between text-xs text-muted-foreground"
                                    >
                                        <span>{{ material.name }}</span>
                                        <span class="tabular-nums">
                                            {{ material.current_stock }} {{ material.unit }} →
                                            <span class="font-medium">{{ material.covers }}</span>
                                        </span>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ t('Stock is reduced when an order is placed, so this is what is left beyond orders already taken.') }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
