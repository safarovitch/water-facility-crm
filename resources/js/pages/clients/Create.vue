<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import AddressMapPicker from '@/components/AddressMapPicker.vue';
import type { AddressData } from '@/components/AddressMapPicker.vue';
import { index, store } from '@/routes/clients';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clients', href: index().url },
  { title: 'New Client', href: '#' },
];

const form = useForm({
  name: '',
  email: '',
  phone: '',
  // Profile
  type: 'individual' as 'individual' | 'company',
  company_name: '',
  region: '',
  notes: '',
  credit_limit: 0,
  // Addresses array
  addresses: [] as (AddressData & { label: string })[],
});

const isCompany = computed(() => form.type === 'company');

// ── Address management ───────────────────────────────────────────────────────

const expandedAddress = ref<number | null>(null);

function addAddress() {
  form.addresses.push({
    label: form.addresses.length === 0 ? 'Main' : `Address ${form.addresses.length + 1}`,
    address_line: '',
    city: '',
    region: '',
    lat: null,
    lng: null,
  });
  expandedAddress.value = form.addresses.length - 1;
}

function removeAddress(i: number) {
  form.addresses.splice(i, 1);
  if (expandedAddress.value === i) expandedAddress.value = null;
}

function toggleAddress(i: number) {
  expandedAddress.value = expandedAddress.value === i ? null : i;
}

function updateAddress(i: number, val: AddressData) {
  form.addresses[i] = { ...form.addresses[i], ...val };
}

// ── Submit ───────────────────────────────────────────────────────────────────

function submitForm() {
  form.post(store().url);
}

const selectClass = 'mt-1 cursor-pointer border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none dark:bg-input/30 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';
</script>

<template>

  <Head title="New Client" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <div class="p-4 pb-6 bg-white dark:bg-gray-900">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create New Client</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add a new client account</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">

        <!-- Account -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Account</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="name">Full Name *</Label>
              <Input id="name" v-model="form.name" required />
              <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
              <Label for="email">Email *</Label>
              <Input id="email" type="email" v-model="form.email" required />
              <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
              <Label for="phone">Phone</Label>
              <Input id="phone" v-model="form.phone" placeholder="+998 XX XXX XX XX" />
            </div>
          </div>
        </div>

        <!-- Profile -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Profile</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="type">Client Type *</Label>
              <select id="type" v-model="form.type" :class="selectClass">
                <option value="individual">Individual</option>
                <option value="company">Company</option>
              </select>
            </div>
            <div v-if="isCompany" class="grid gap-2">
              <Label for="company_name">Company Name</Label>
              <Input id="company_name" v-model="form.company_name" />
              <InputError :message="form.errors.company_name" />
            </div>
            <div class="grid gap-2">
              <Label for="credit_limit">Credit Limit</Label>
              <Input id="credit_limit" type="number" min="0" v-model.number="form.credit_limit" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <Label for="notes">Internal Notes</Label>
              <textarea id="notes" v-model="form.notes" rows="2" class="block w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Private notes visible only to staff..."></textarea>
            </div>
          </div>
        </div>

        <!-- Addresses -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Addresses</h2>
            <Button type="button" variant="outline" size="sm" @click="addAddress">+ Add Address</Button>
          </div>

          <p v-if="form.addresses.length === 0" class="text-sm text-gray-400 italic">No addresses yet. Click "+ Add Address" to add one.</p>

          <div v-for="(addr, i) in form.addresses" :key="i" class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">

            <!-- Address card header -->
            <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-700 cursor-pointer" @click="toggleAddress(i)">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                  {{ addr.label || `Address ${i + 1}` }}
                  <span v-if="i === 0" class="ml-1 text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-1.5 py-0.5 rounded">Default</span>
                </span>
                <span v-if="addr.address_line" class="text-xs text-gray-400">— {{ addr.address_line.substring(0, 40) }}{{ addr.address_line.length > 40 ? '…' : '' }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-gray-400 text-xs">{{ expandedAddress === i ? '▲' : '▼' }}</span>
                <button type="button" @click.stop="removeAddress(i)" class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
              </div>
            </div>

            <!-- Address card body -->
            <div v-show="expandedAddress === i" class="p-4 space-y-4">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                  <Label>Label</Label>
                  <Input v-model="addr.label" placeholder="e.g. Main, Office, Warehouse" />
                </div>
                <div class="grid gap-2">
                  <Label>City</Label>
                  <Input v-model="addr.city" placeholder="City" />
                </div>
              </div>

              <!-- Map picker (includes street address input) -->
              <div class="grid gap-2 sm:col-span-2">
                <AddressMapPicker :model-value="addr" @update:model-value="updateAddress(i, $event)" height="260px" />
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 pt-2 pb-6">
          <Button type="button" @click="$inertia.visit(index().url)" variant="outline">Cancel</Button>
          <Button type="submit" :disabled="form.processing">
            <span v-if="form.processing">Creating...</span>
            <span v-else>Create Client</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
