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
import users from '@/routes/users';
import { computed, ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { Info, AlertCircle } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clients', href: index().url },
  { title: 'New Client', href: '#' },
];

const form = useForm({
  name: '',
  email: '',
  phones: [{ label: 'Mobile', phone: '', is_default: true }] as { id?: number; label: string; phone: string; is_default: boolean }[],
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

// ── Phone management ─────────────────────────────────────────────────────────

function addPhone() {
  form.phones.push({
    label: 'Mobile',
    phone: '',
    is_default: false,
  });
}

function removePhone(i: number) {
  if (form.phones.length <= 1) return;
  const removedIsDefault = form.phones[i].is_default;
  form.phones.splice(i, 1);
  if (removedIsDefault && form.phones.length > 0) {
    form.phones[0].is_default = true;
  }
}

function setPrimaryPhone(i: number) {
  form.phones.forEach((p, idx) => p.is_default = idx === i);
}

// ── Submit ───────────────────────────────────────────────────────────────────

function submitForm() {
  form.post(store().url);
}

const selectClass = 'mt-1 cursor-pointer border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none dark:bg-input/30 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

// ── Existing user check ───────────────────────────────────────────────────────

const userCheckResult = ref<{ exists: boolean; is_client?: boolean; name?: string; phone?: string } | null>(null);
const isCheckingEmail = ref(false);

watchDebounced(
  () => form.email,
  async (email) => {
    if (!email || !email.includes('@')) {
      userCheckResult.value = null;
      return;
    }

    isCheckingEmail.value = true;
    try {
      const response = await fetch(users.checkEmail({ query: { email } }).url);
      if (response.ok) {
        userCheckResult.value = await response.json();
        // If user exists and is not a client, pre-fill name and phone if empty
        if (userCheckResult.value?.exists && !userCheckResult.value?.is_client) {
          if (!form.name) form.name = userCheckResult.value.name || '';
          if (form.phones.length > 0 && !form.phones[0].phone) {
            form.phones[0].phone = userCheckResult.value.phone || '';
          }
        }
      }
    } catch (e) {
      console.error('Failed to check email', e);
    } finally {
      isCheckingEmail.value = false;
    }
  },
  { debounce: 500 }
);
</script>

<template>

  <Head title="New Client" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg xl:w-2/3">
      <div class="p-4 pb-6 bg-white dark:bg-gray-900">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create New Client</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add a new client account</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">

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
              <div class="relative">
                <Input id="email" type="email" v-model="form.email" required :class="{ 'border-red-500': userCheckResult?.is_client }" />
                <div v-if="isCheckingEmail" class="absolute right-3 top-2.5">
                  <div class="animate-spin h-4 w-4 border-2 border-primary border-t-transparent rounded-full"></div>
                </div>
              </div>

              <!-- Feedback Alerts -->
              <div v-if="userCheckResult?.exists" class="mt-2 space-y-2">
                <div v-if="userCheckResult.is_client" class="flex items-center gap-2 p-3 text-sm rounded-md bg-destructive/15 text-destructive border border-destructive/20">
                  <AlertCircle class="h-4 w-4 shrink-0" />
                  <p>This email is already registered as a client.</p>
                </div>
                <div v-else class="flex items-center gap-2 p-3 text-sm rounded-md bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                  <Info class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                  <p>
                    Email belongs to existing user <strong>{{ userCheckResult.name }}</strong>. This client profile will be linked to them.
                  </p>
                </div>
              </div>

              <InputError :message="form.errors.email" />
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
              <div class="flex items-center justify-between mb-2">
                <Label>Phone Numbers *</Label>
                <Button type="button" variant="outline" size="sm" class="h-7 text-xs" @click="addPhone">+ Add Phone</Button>
              </div>
              <div class="space-y-3">
                <div v-for="(p, i) in form.phones" :key="i" class="flex gap-2 items-center">
                  <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="relative">
                      <select v-model="p.label" :class="selectClass" class="!mt-0">
                        <option value="Mobile">Mobile</option>
                        <option value="Work">Work</option>
                        <option value="Home">Home</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>
                    <div class="relative">
                      <Input v-model="p.phone" placeholder="+998 XX XXX XX XX" />
                    </div>
                  </div>
                  <div class="flex items-center gap-1 pt-0.5">
                    <Button type="button" variant="outline" size="icon" class="h-9 w-9" :class="p.is_default ? 'bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-900/20' : 'text-gray-400'" @click="setPrimaryPhone(i)" title="Set as Primary">
                      <span v-if="p.is_default" class="text-[10px] font-bold">PRI</span>
                      <span v-else class="text-[10px] font-bold opacity-30">PRI</span>
                    </Button>
                    <Button v-if="form.phones.length > 1" type="button" variant="outline" size="icon" class="h-9 w-9 text-red-500 border-red-100 hover:bg-red-50" @click="removePhone(i)">
                      ✕
                    </Button>
                  </div>
                </div>
              </div>
              <InputError :message="form.errors.phones" />
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
