<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index, create, edit } from '@/routes/products';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, Link, router, useForm } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlusCircle, Search, Edit, PackageOpen } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Products',
    href: index().url
  },
];

interface Paginated<T> {
  data: T[];
  links: any[];
  current_page: number;
  last_page: number;
  total: number;
}

interface Product {
  id: number;
  name: Record<string, string>;
  sku: string;
  slug: string;
  short_description: Record<string, string> | null;
  price: string;
  sale_price: string;
  currency: string;
  quantity: number;
  status: string;
  image_url: string | null;
}

const props = defineProps<{
  products: Paginated<Product>;
  filters?: {
    search?: string;
  };
}>();

const availableLocales = usePage().props.available_locales as string[];

const getLocalizedValue = (translations: Record<string, string> | null | undefined) => {
  if (!translations) return '—';
  for (const locale of availableLocales) {
    if (translations[locale]) return translations[locale];
  }
  return Object.values(translations)[0] || '—';
};

const filterForm = useForm({
  search: props.filters?.search || '',
});

const applyFilters = () => {
  router.get('/products', filterForm.data(), { preserveState: true, preserveScroll: true });
};
</script>

<template>
  <Head title="Products" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-4 md:space-y-6 container mx-auto px-4 md:px-0">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground">Products</h1>
          <p class="text-sm text-muted-foreground mt-1">Manage your catalog of items and stock.</p>
        </div>
        <Link :href="create().url" class="w-full md:w-auto">
          <Button class="w-full md:w-auto gap-2 shadow-sm font-semibold rounded-xl h-11 md:h-10">
            <PlusCircle class="h-4 w-4" /> Add Product
          </Button>
        </Link>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-0">
            <!-- Filters -->
            <div class="p-4 bg-gray-50/50 dark:bg-gray-800/30 grid grid-cols-1 md:flex md:flex-wrap gap-3 items-end border-b">
                <div class="space-y-1 relative w-full md:w-64 max-w-sm">
                    <Label class="text-xs uppercase tracking-wider text-muted-foreground">Search</Label>
                    <Search class="absolute left-2.5 top-7 h-4 w-4 text-muted-foreground" />
                    <Input v-model="filterForm.search" placeholder="Search by name or sku..." class="h-10 md:h-9 w-full bg-white dark:bg-gray-900 border-input shadow-sm pl-9" @keyup.enter="applyFilters" />
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <Button @click="applyFilters" variant="secondary" size="sm" class="h-10 md:h-9 flex-1 md:flex-none">Search</Button>
                </div>
            </div>

            <!-- Table (Desktop) -->
            <div class="hidden md:block relative overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold w-16">Image</th>
                            <th class="px-6 py-4 font-semibold">Name & SKU</th>
                            <th class="px-6 py-4 font-semibold text-right">Price</th>
                            <th class="px-6 py-4 font-semibold text-center">Stock</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 bg-white dark:bg-background">
                        <tr v-for="product in products.data" :key="product.id" class="hover:bg-muted/40 transition-colors group">
                            <td class="px-6 py-4">
                              <div v-if="product.image_url" class="w-12 h-12 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <img :src="product.image_url" :alt="getLocalizedValue(product.name)" class="w-full h-full object-cover" />
                              </div>
                              <div v-else class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-200 border border-gray-200 dark:border-gray-700">
                                {{ getLocalizedValue(product.name).charAt(0).toUpperCase() }}
                              </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900 dark:text-white">{{ getLocalizedValue(product.name) }}</div>
                                <div class="text-xs text-muted-foreground mt-0.5 font-mono">{{ product.sku }}</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-medium text-gray-900 dark:text-white">
                                  {{ product.price }} {{ product.currency }}
                                </div>
                                <div v-if="product.sale_price && parseFloat(product.sale_price) > 0" class="text-xs text-gray-400 line-through mt-0.5">
                                  {{ product.sale_price }} {{ product.currency }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-bold text-base" :class="product.quantity <= 0 ? 'text-red-500' : 'text-green-600'">{{ product.quantity }}</span> 
                            </td>
                            <td class="px-6 py-4">
                                <Badge :variant="product.status === 'active' ? 'default' : (product.status === 'draft' ? 'secondary' : 'destructive')" class="capitalize">
                                    {{ product.status }}
                                </Badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="edit(product.id).url">
                                      <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/40">
                                          <Edit class="h-4 w-4" />
                                      </Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="md:hidden divide-y divide-border/60">
                <div v-for="product in products.data" :key="product.id" class="p-4 bg-white dark:bg-background active:bg-muted/30 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 shrink-0 bg-gray-100 dark:bg-gray-800 rounded-xl overflow-hidden flex items-center justify-center border border-gray-200 dark:border-gray-700 shadow-sm text-lg font-bold text-primary/40">
                            <img v-if="product.image_url" :src="product.image_url" class="h-full w-full object-cover" />
                            <span v-else>{{ getLocalizedValue(product.name).charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-gray-900 dark:text-white text-base leading-tight truncate">{{ getLocalizedValue(product.name) }}</div>
                                <Badge :variant="product.status === 'active' ? 'default' : (product.status === 'draft' ? 'secondary' : 'destructive')" class="capitalize whitespace-nowrap text-[10px] h-5 px-1.5 shrink-0">
                                    {{ product.status }}
                                </Badge>
                            </div>
                            <div class="text-[10px] text-muted-foreground mt-1 font-mono uppercase tracking-wider">SKU: {{ product.sku }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Price</span>
                            <span class="font-bold text-sm text-gray-900 dark:text-white">{{ product.price }} <span class="text-[10px] font-normal">{{ product.currency }}</span></span>
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold text-right">Stock</span>
                            <span class="font-bold text-sm" :class="product.quantity <= 0 ? 'text-red-500' : 'text-green-600'">{{ product.quantity }}</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-dashed border-border/60">
                        <Link :href="edit(product.id).url" class="w-full">
                          <Button variant="secondary" size="sm" class="w-full h-10 rounded-lg shadow-sm border border-border/50">
                              <Edit class="h-4 w-4 mr-1.5" /> Edit Product
                          </Button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="products.data.length === 0" class="px-6 py-12 text-center text-muted-foreground">
                <div class="flex flex-col items-center justify-center opacity-60">
                    <PackageOpen class="h-10 w-10 mb-3 text-gray-400" />
                    <p class="font-medium text-sm">No products found.</p>
                </div>
            </div>
            
            <!-- Pagination -->
            <div v-if="products.last_page > 1" class="px-6 py-4 border-t flex flex-wrap gap-1 bg-gray-50/50 dark:bg-gray-800/30">
                <div v-for="(link, key) in products.links" :key="key">
                    <Button v-if="link.url === null" disabled variant="outline" size="sm" class="opacity-50 h-8" v-html="link.label" />
                    <Button v-else :variant="link.active ? 'default' : 'outline'" size="sm" class="h-8" @click="router.get(link.url, filterForm.data(), { preserveScroll: true, preserveState: true })" v-html="link.label" />
                </div>
            </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
