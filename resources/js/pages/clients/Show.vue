<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Phone, Edit, MapPin, Building, User as UserIcon, Calendar, Clock } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { computed } from 'vue';
import { edit } from '@/routes/clients';

interface UserAddress {
  id: number;
  label: string;
  address_line: string;
  city: string | null;
  region: string | null;
  is_default: boolean;
}

interface ClientPhone {
  id: number;
  label: string;
  phone: string;
  is_default: boolean;
}

interface ActivityLog {
  id: number;
  description: string;
  created_at: string;
  properties: {
    duration?: number;
    direction?: 'inbound' | 'outbound';
    phone?: string;
    status?: string;
    recording_url?: string;
  };
}

interface Client {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string;
  user_profile: {
    type: string;
    company_name: string | null;
    region: string | null;
    notes: string | null;
    credit_limit: number;
  } | null;
  phones: ClientPhone[];
  addresses: UserAddress[];
}

const props = defineProps<{
  client: Client;
  calls: ActivityLog[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clients', href: '/clients' },
  { title: props.client.name, href: '#' },
];

const isCompany = computed(() => props.client.user_profile?.type === 'company');

const initiateCall = (phone: string | null) => {
  if (phone && typeof window !== 'undefined' && window.initiateAsteriskCall) {
    window.initiateAsteriskCall(phone);
  }
};

const formatDuration = (seconds?: number) => {
  if (seconds === undefined) return '';
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = (seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
};
</script>

<template>

  <Head :title="client.name" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6 max-w-7xl mx-auto space-y-8">

      <!-- Header Section -->
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex items-start gap-4">
          <div class="h-16 w-16 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-sm flex-shrink-0">
            <Building v-if="isCompany" class="w-8 h-8" />
            <UserIcon v-else class="w-8 h-8" />
          </div>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ client.name }}</h1>
              <span class="px-2.5 py-1 text-xs font-semibold rounded-md shadow-sm border bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 uppercase tracking-wider">
                {{ client.user_profile?.type || 'Individual' }}
              </span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2">
              <Calendar class="w-4 h-4 opacity-70" />
              Customer since {{ new Date(client.created_at).getFullYear() }}
            </p>
          </div>
        </div>

        <Link :href="edit(client.id).url">
          <Button variant="outline" class="flex items-center gap-2">
            <Edit class="w-4 h-4" />
            Edit Profile
          </Button>
        </Link>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

        <!-- Sidebar Details -->
        <div class="space-y-6">

          <!-- Contact Details -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b pb-2 dark:border-gray-700">Contact Details</h2>

            <div class="space-y-4">
              <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Email</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ client.email }}</p>
              </div>

              <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Phone Numbers</p>
                <div v-if="client.phones && client.phones.length > 0" class="space-y-2">
                  <div v-for="phone in client.phones" :key="phone.id" class="flex items-center justify-between group py-1.5 px-3 -mx-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-3">
                      <div class="bg-primary/10 p-1.5 rounded-full text-primary">
                        <Phone class="w-4 h-4" />
                      </div>
                      <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                          {{ phone.phone }}
                          <span v-if="phone.is_default" class="text-[10px] bg-green-100 text-green-700 px-1.5 rounded uppercase font-bold tracking-wider">Primary</span>
                        </p>
                        <p class="text-xs text-gray-500 truncate">{{ phone.label }}</p>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity rounded-full text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/30 dark:text-green-400" @click="initiateCall(phone.phone)" title="Call">
                      <Phone class="w-4 h-4 fill-current" />
                    </Button>
                  </div>
                </div>
                <p v-else class="text-sm text-gray-500 italic">No phone numbers assigned.</p>
              </div>
            </div>
          </div>

          <!-- Business Profile Details -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b pb-2 dark:border-gray-700">Profile Details</h2>
            <div class="space-y-4">
              <div v-if="isCompany && client.user_profile?.company_name">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Company Name</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ client.user_profile.company_name }}</p>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Credit Limit</p>
                  <p class="text-lg font-semibold" :class="client.user_profile?.credit_limit ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100'">
                    {{ client.user_profile?.credit_limit || 0 }} TJS
                  </p>
                </div>
              </div>
              <div v-if="client.user_profile?.notes">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Internal Notes</p>
                <p class="text-sm text-gray-700 dark:text-gray-300 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg border border-yellow-100 dark:border-yellow-900/40">
                  {{ client.user_profile.notes }}
                </p>
              </div>
            </div>
          </div>

          <!-- Addresses -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b pb-2 dark:border-gray-700">Addresses</h2>
            <div v-if="client.addresses && client.addresses.length > 0" class="space-y-4">
              <div v-for="address in client.addresses" :key="address.id" class="flex gap-3 items-start border-l-2 border-gray-100 dark:border-gray-700 pl-3 py-1">
                <MapPin class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <div>
                  <div class="flex items-center gap-2 mb-0.5">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ address.label }}</p>
                    <span v-if="address.is_default" class="text-[10px] bg-blue-100 text-blue-700 px-1.5 rounded uppercase font-bold tracking-wider">Default</span>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ address.address_line }}</p>
                  <p v-if="address.city || address.region" class="text-xs text-gray-500 mt-0.5">{{ address.city }}{{ address.city && address.region ? ', ' : '' }}{{ address.region }}</p>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No addresses assigned.</p>
          </div>
        </div>

        <!-- Main Content (History Tabs) -->
        <div class="xl:col-span-2 space-y-8">

          <!-- Orders History Placeholder -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Orders History</h2>
            </div>
            <div class="p-0 max-h-[400px] overflow-y-auto">
              <div class="p-8 text-center bg-gray-50/50 dark:bg-gray-800/50 rounded-b-2xl">
                <p class="text-sm text-gray-500">Orders Module is not active. Future integrations will appear here.</p>
              </div>
            </div>
          </div>

          <!-- Call History -->
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <Phone class="w-5 h-5 text-gray-400" />
                Call History
              </h2>
            </div>
            <div class="p-0 max-h-[400px] overflow-y-auto">
              <div v-if="calls.length === 0" class="p-8 text-center text-gray-500">
                No call history found for this client.
              </div>
              <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                <li v-for="call in calls" :key="call.id" class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group flex flex-col">
                  <div class="flex justify-between items-center w-full">
                    <div class="flex items-center space-x-4">
                      <div class="rounded-full w-10 h-10 flex items-center justify-center shrink-0 border" :class="{
                        'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-900/30 dark:border-blue-900': call.properties.direction === 'inbound' && call.properties.status === 'answered',
                        'bg-green-50 text-green-600 border-green-100 dark:bg-green-900/30 dark:border-green-900': call.properties.direction === 'outbound' && call.properties.status === 'answered',
                        'bg-red-50 text-red-600 border-red-100 dark:bg-red-900/30 dark:border-red-900': call.properties.status !== 'answered',
                        'bg-gray-50 text-gray-600 border-gray-200': !call.properties.status // Legacy calls
                      }">
                        <Phone class="w-4 h-4" />
                      </div>
                      <div>
                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 mb-0.5">
                          {{ call.description }}
                        </div>
                        <div class="text-xs text-gray-500 flex flex-wrap items-center gap-3">
                          <span class="flex items-center gap-1">
                            <Clock class="w-3.5 h-3.5" />
                            {{ formatDate(call.created_at) }}
                          </span>
                          <span class="flex items-center gap-1 font-mono bg-gray-100 dark:bg-gray-800 px-1.5 rounded" :class="call.properties.status === 'answered' ? 'text-gray-600 dark:text-gray-300' : 'text-red-600 dark:text-red-400'">
                            {{ call.properties.status === 'answered' ? formatDuration(call.properties.duration) : '00:03' }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity text-green-600 hover:text-green-700 rounded-full hover:bg-green-50" @click="initiateCall(call.properties.phone || null)">
                      <Phone class="w-4 h-4 fill-current" />
                    </Button>
                  </div>

                  <!-- Full Width Audio Recording Player -->
                  <div v-if="call.properties.recording_url" class="mt-3 w-full">
                    <audio controls preload="none" class="h-9 w-full" :src="call.properties.recording_url"></audio>
                  </div>
                </li>
              </ul>
            </div>
          </div>

        </div>
      </div>
    </div>
  </AppLayout>
</template>
