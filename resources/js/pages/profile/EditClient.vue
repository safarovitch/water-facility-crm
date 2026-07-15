<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Phone, CheckCircle2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import AddressMapPicker from '@/components/AddressMapPicker.vue';
import type { AddressData } from '@/components/AddressMapPicker.vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface UserAddress {
  id?: number;
  label: string;
  address_line: string;
  city: string | null;
  region: string | null;
  lat: number | null;
  lng: number | null;
  is_default: boolean;
}

interface Client {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  user_profile: {
    type: string;
    company_name: string | null;
    region: string | null;
  } | null;
  phones: { id: number; label: string; phone: string; is_default: boolean }[];
  addresses: UserAddress[];
}

const props = defineProps<{ client: Client }>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('My Profile'), href: '/profile' },
  { title: t('Edit'), href: '#' },
]);

const form = useForm({
  name: props.client.name,
  email: props.client.email,
  type: (props.client.user_profile?.type ?? 'individual') as 'individual' | 'company',
  company_name: props.client.user_profile?.company_name ?? '',
  region: props.client.user_profile?.region ?? '',
  phones: (props.client.phones?.length ? props.client.phones : [{ label: 'Mobile', phone: props.client.phone ?? '', is_default: true }]).map((p: any) => ({
    id: p.id,
    label: p.label || 'Mobile',
    phone: p.phone,
    is_default: !!p.is_default
  })) as { id?: number; label: string; phone: string; is_default: boolean }[],
  addresses: (props.client.addresses ?? []).map(a => ({
    id: a.id,
    label: a.label,
    address_line: a.address_line,
    city: a.city ?? '',
    region: a.region ?? '',
    lat: a.lat,
    lng: a.lng,
    is_default: a.is_default,
  })) as (AddressData & { id?: number; label: string; is_default: boolean })[],
});

const isCompany = computed(() => form.type === 'company');

// ── Address management ───────────────────────────────────────────────────────

const expandedAddress = ref<number | null>(null);

function addAddress() {
  form.addresses.push({
    label: `Address ${form.addresses.length + 1}`,
    address_line: '',
    city: '',
    region: '',
    lat: null,
    lng: null,
    is_default: false,
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
  form.post('/profile/edit', {
    preserveScroll: true
  });
}

const selectClass = 'mt-1 cursor-pointer border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none dark:bg-input/30 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';
</script>

<template>

  <Head :title="t('Edit Profile')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg max-w-4xl mx-auto py-8">
      <div class="pb-6 bg-white dark:bg-gray-900 px-4 py-5 sm:px-6 rounded-t-lg">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Edit My Profile') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Keep your contact and address details up to date.') }}</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">

        <!-- Account -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Account Information') }}</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="name">{{ t('Full Name') }} *</Label>
              <Input id="name" v-model="form.name" required />
              <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
              <Label for="email">{{ t('Email') }} *</Label>
              <Input id="email" type="email" v-model="form.email" required disabled class="opacity-50 cursor-not-allowed" />
              <p class="text-xs text-muted-foreground mt-1">{{ t('Visit account settings to change your email.') }}</p>
            </div>
          </div>
        </div>

        <!-- Profile -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Profile Details') }}</h2>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="type">{{ t('Client Type') }} *</Label>
              <select id="type" v-model="form.type" :class="selectClass">
                <option value="individual">{{ t('Individual') }}</option>
                <option value="company">{{ t('Company') }}</option>
              </select>
            </div>
            <div v-if="isCompany" class="grid gap-2">
              <Label for="company_name">{{ t('Company Name') }}</Label>
              <Input id="company_name" v-model="form.company_name" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <div class="flex items-center justify-between mb-2 mt-4">
                <Label>{{ t('Phone Numbers') }} *</Label>
                <Button type="button" variant="outline" size="sm" class="h-7 text-xs" @click="addPhone">{{ t('+ Add Phone') }}</Button>
              </div>
              <div class="space-y-3">
                <div v-for="(p, i) in form.phones" :key="p.id ?? i" class="flex gap-2 items-center">
                  <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="relative">
                      <select v-model="p.label" :class="selectClass" class="!mt-0">
                        <option value="Mobile">{{ t('Mobile') }}</option>
                        <option value="Work">{{ t('Work') }}</option>
                        <option value="Home">{{ t('Home') }}</option>
                        <option value="Other">{{ t('Other') }}</option>
                      </select>
                    </div>
                    <div class="relative flex gap-1">
                      <Input v-model="p.phone" placeholder="+998 XX XXX XX XX" class="flex-1" />
                    </div>
                  </div>
                  <div class="flex items-center gap-1 pt-0.5">
                    <Button type="button" variant="outline" size="icon" class="h-9 w-9" :class="p.is_default ? 'bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-900/20' : 'text-gray-400'" @click="setPrimaryPhone(i)" :title="t('Set as Primary')">
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
          </div>
        </div>

        <!-- Addresses -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
              {{ t('My Addresses') }}
              <span class="ml-2 text-xs font-normal text-gray-400">({{ form.addresses.length }})</span>
            </h2>
            <Button type="button" variant="outline" size="sm" @click="addAddress">{{ t('+ Add Address') }}</Button>
          </div>

          <p v-if="form.addresses.length === 0" class="text-sm text-gray-400 italic">{{ t('No addresses saved yet.') }}</p>

          <div v-for="(addr, i) in form.addresses" :key="addr.id ?? i" class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">

            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-700 cursor-pointer" @click="toggleAddress(i)">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                  {{ addr.label || `Address ${i + 1}` }}
                  <span v-if="addr.is_default" class="ml-1 text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-1.5 py-0.5 rounded">{{ t('Default') }}</span>
                </span>
                <span v-if="addr.address_line" class="text-xs text-gray-400">
                  — {{ addr.address_line.substring(0, 40) }}{{ addr.address_line.length > 40 ? '…' : '' }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-gray-400 text-xs">{{ expandedAddress === i ? '▲' : '▼' }}</span>
                <button type="button" @click.stop="removeAddress(i)" class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
              </div>
            </div>

            <!-- Body -->
            <div v-show="expandedAddress === i" class="p-4 space-y-4">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                  <Label>{{ t('Label') }}</Label>
                  <Input v-model="addr.label" :placeholder="t('e.g. Main, Office, Warehouse')" />
                </div>
                <div class="grid gap-2">
                  <Label>{{ t('City') }}</Label>
                  <Input v-model="addr.city" :placeholder="t('City')" />
                </div>
              </div>
              <div class="grid gap-2 sm:col-span-2">
                <AddressMapPicker :model-value="addr" @update:model-value="updateAddress(i, $event)" height="260px" />
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 pt-2 pb-6">
          <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
            <span v-show="form.recentlySuccessful" class="text-sm text-green-600 flex items-center"><CheckCircle2 class="w-4 h-4 mr-1" /> {{ t('Saved') }}</span>
          </Transition>
          <Button type="button" @click="$inertia.visit('/profile')" variant="outline">{{ t('Cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">
            <span v-if="form.processing">{{ t('Saving...') }}</span>
            <span v-else>{{ t('Save Profile') }}</span>
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
