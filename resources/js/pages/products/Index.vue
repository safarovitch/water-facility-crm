<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index, create, edit } from '@/routes/products';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../../components/PlaceholderPattern.vue';
import { Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Products',
    href: index().url
  },
];

interface Paginated<T> {
  data: T[];
  links: Record<string, any>;
  meta: Record<string, any>;
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
  statusHtmlClass: string;
  statusLabel: string;
  image_url: string | null;
}

const props = defineProps<{
  products: Paginated<Product>
}>();

</script>

<template>

  <Head title="Users" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="p-4 flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 pb-4 bg-white dark:bg-gray-900">
        <div>
          <Link :href="create().url" class="inline-flex items-center text-gray-500 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-3 py-1.5 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 me-2">
            Create product
          </Link>
        </div>
        <label for="table-search" class="sr-only">Search</label>
        <div class="relative">
          <div class="absolute inset-y-0 rtl:inset-r-0 start-0 flex items-center ps-3 pointer-events-none">
            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
            </svg>
          </div>
          <input type="text" id="table-search-users" class="block p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg w-80 bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Search for users">
        </div>
      </div>
      <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
          <tr>
            <!-- <th scope="col" class="p-4">
                            <div class="flex items-center">
                                <input id="checkbox-all-search" type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="checkbox-all-search" class="sr-only">checkbox</label>
                            </div>
                        </th> -->
            <th scope="col" class="px-6 py-3">
              Image
            </th>
            <th scope="col" class="px-6 py-3">
              Name & Slug
            </th>
            <th scope="col" class="px-6 py-3 text-right">
              Price
            </th>
            <th scope="col" class="px-6 py-3">
              Short Description
            </th>
            <th scope="col" class="px-6 py-3 text-center">
              Quantity
            </th>
            <th scope="col" class="px-6 py-3">
              Status
            </th>
            <th scope="col" class="px-6 py-3 text-right">
              Action
            </th>
          </tr>
        </thead>
        <tbody>
          <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 border-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600" v-for="product in props.products.data">
            <!-- <td class="w-4 p-4">
                            <div class="flex items-center">
                                <input id="checkbox-table-search-1" type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
                            </div>
                        </td> -->
            <td class="px-6 py-4">
              <div v-if="product.image_url" class="w-12 h-12 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <img :src="product.image_url" :alt="product.name?.en" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-200 border border-gray-200 dark:border-gray-700">
                {{ (product.name?.en ?? product.name?.uz ?? '?').charAt(0).toUpperCase() }}
              </div>
            </td>
            <td class="px-6 py-4">
              <div class="text-base font-semibold text-gray-900 dark:text-white">{{ product.name?.en ?? product.name?.uz ?? '—' }}</div>
              <div class="text-xs font-mono text-gray-500">{{ product.sku }} / {{ product.slug }}</div>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="font-medium text-gray-900 dark:text-white">
                {{ product.price }} {{ product.currency }}
              </div>
              <div v-if="product.sale_price && parseFloat(product.sale_price) > 0" class="text-xs text-gray-400 line-through">
                {{ product.sale_price }} {{ product.currency }}
              </div>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm text-gray-500 line-clamp-2 max-w-xs">
                {{ product.short_description?.en ?? product.short_description?.uz ?? '—' }}
              </div>
            </td>
            <td class="px-6 py-4 text-center font-medium">
              {{ product.quantity }}
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center">
                <div class="h-2 w-2 rounded-full me-2" :class="product.statusHtmlClass"></div>
                <span class="text-xs font-medium">{{ product.statusLabel }}</span>
              </div>
            </td>
            <td class="px-6 py-4 text-right">
              <Link :href="edit(product.id).url" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </AppLayout>
</template>
