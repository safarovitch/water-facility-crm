<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, useForm } from '@inertiajs/vue3';
import PlaceholderPattern from '../../components/PlaceholderPattern.vue';
import { Link, Upload, X } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { ref, computed, toRefs } from 'vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Label from '@/components/ui/label/Label.vue';
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/users';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Users',
    href: index().url,
  },
  {
    title: 'New User',
    href: '#',
  },
];

const props = defineProps<{
  statuses: [],
  roles: roleObject
}>();

interface roleObject {
  id: number;
  name: string;
}

// Form handling with Inertia
const form = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  status: '',
  avatar: null as File | null,
  roles: '',
  sip_extension: '',
  sip_password: ''
});

// Avatar preview
const avatarPreview = ref<string | null>(null);

const handleAvatarUpload = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (file) {
    form.avatar = file;
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      avatarPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  }
};

const removeAvatar = () => {
  form.avatar = null;
  avatarPreview.value = null;
  // Reset file input
  const fileInput = document.getElementById('avatar') as HTMLInputElement;
  if (fileInput) fileInput.value = '';
};

// Computed property for form validation
const isFormValid = computed(() => {
  return form.name.length > 0 &&
    form.email.length > 0 &&
    form.phone.length > 0 &&
    form.password.length >= 8 &&
    form.roles.length > 0 &&
    form.password === form.password_confirmation;
});

const submitForm = () => {
  form.post(store.url(), {
    forceFormData: true, // converts the form to multipart/form-data
    onFinish: () => {
      console.log('Form submitted!');
    }
  });
};
</script>

<template>

  <Head title="New user" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative overflow-x-auto sm:rounded-lg">
      <!-- Header -->
      <div class="p-4 flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 pb-6 bg-white dark:bg-gray-900">
        <div>
          <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create New User</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add a new user to organization</p>
        </div>
      </div>

      <!-- Form -->
      <Form v-bind="store.form()" class="space-y-6" v-slot="{ errors, processing, recentlySuccessful }" enctype="multipart/form-data">
        <!-- <Form v-bind="store" @submit.prevent="submitForm" class="space-y-6" v-slot="{ errors, processing, recentlySuccessful }"> -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <!-- Avatar Upload -->
            <div class="grid gap-2 mb-6">
              <Label for="name">Avatar</Label>
              <div class="flex items-center space-x-4">
                <div class="relative">
                  <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center overflow-hidden">
                    <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar preview" class="w-full h-full object-cover">
                    <Upload v-else class="w-8 h-8 text-gray-400" />
                  </div>
                  <button v-if="avatarPreview" type="button" @click="removeAvatar" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors">
                    <X class="w-3 h-3" />
                  </button>
                </div>
                <div>
                  <Input id="avatar" name="avatar" type="file" accept="image/*" @change="handleAvatarUpload" class="hidden" />
                  <label for="avatar" class="cursor-pointer inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                    Choose file
                  </label>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    JPG, GIF or PNG. Max size 2MB.
                  </p>
                </div>
              </div>
              <InputError class="mt-2" :message="errors.avatar" />
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
              <!-- Name -->
              <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" class="mt-1 block w-full" name="name" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                <InputError class="mt-2" :message="errors.name" />
              </div>

              <!-- Email -->
              <div class="grid gap-2">
                <Label for="email">Email Address *</Label>
                <Input id="email" class="mt-1 block w-full" name="email" v-model="form.email" required autocomplete="email" placeholder="Email Address" />
                <InputError class="mt-2" :message="errors.email" />
              </div>

              <!-- Phone -->
              <div class="grid gap-2">
                <Label for="phone">Phone Number *</Label>
                <Input id="phone" class="mt-1 block w-full" name="phone" v-model="form.phone" required autocomplete="tel" placeholder="Phone Number" />
                <InputError class="mt-2" :message="errors.phone" />
              </div>

              <!-- Status -->
              <div class="grid gap-2">
                <Label for="status">Status</Label>
                <select id="status" name="status" v-model="form.status" required :class="cn(
                  'mt-1 cursor-pointer file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                  'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                  'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                )">
                  <option value="">Select status</option>
                  <option v-for="(statusIndex, status) in statuses" :key="statusIndex" :value="statusIndex">{{ status }}</option>
                </select>
              </div>

              <!-- Roles -->
              <div class="grid gap-2">
                <Label for="roles">Assign roles</Label>
                <select id="roles" name="roles[]" v-model="form.roles" required multiple :class="cn(
                  'mt-1 cursor-pointer file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-auto w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                  'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                  'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                )">
                  <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                </select>
              </div>
            </div>

            <!-- Asterisk Credentials -->
            <div class="mt-8 mb-4">
              <h3 class="text-md font-medium text-gray-900 dark:text-white pb-2 border-b border-gray-200 dark:border-gray-700">Asterisk SIP Credentials</h3>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mt-5">
              <!-- SIP Extension -->
              <div class="grid gap-2">
                <Label for="sip_extension">SIP Extension</Label>
                <Input id="sip_extension" class="mt-1 block w-full" name="sip_extension" v-model="form.sip_extension" placeholder="e.g. 100" />
                <InputError class="mt-2" :message="errors.sip_extension" />
              </div>

              <!-- SIP Password -->
              <div class="grid gap-2">
                <Label for="sip_password">SIP Password</Label>
                <Input id="sip_password" type="text" class="mt-1 block w-full" name="sip_password" v-model="form.sip_password" placeholder="e.g. secret" />
                <InputError class="mt-2" :message="errors.sip_password" />
              </div>
            </div>

            <!-- Login Credentials -->
            <div class="mt-8 mb-4">
              <h3 class="text-md font-medium text-gray-900 dark:text-white pb-2 border-b border-gray-200 dark:border-gray-700">Login Credentials</h3>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mt-5">
              <!-- Password -->
              <div class="grid gap-2">
                <Label for="password">Password *</Label>
                <Input id="password" class="mt-1 block w-full" name="password" v-model="form.password" required autocomplete="new-password" placeholder="Enter password (min. 8 characters)" />
                <InputError class="mt-2" :message="errors.password" />
              </div>

              <!-- Confirm Password -->
              <div class="grid gap-2">
                <Label for="password_confirmation">Confirm Password *</Label>
                <Input id="password_confirmation" class="mt-1 block w-full" name="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" placeholder="" />
                <InputError class="mt-2" :message="errors.password_confirmation" />
              </div>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-3 pt-4">
          <Button type="button" @click="$inertia.visit(index().url)" class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
            Cancel
          </Button>
          <Button type="submit" :disabled="processing || !isFormValid" class="cursor-pointer inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" :class="{ 'opacity-50 cursor-not-allowed': processing || !isFormValid }">
            <span v-if="processing" class="mr-2">
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            {{ processing ? 'Creating...' : 'Create User' }}
          </Button>
        </div>
      </Form>
    </div>
  </AppLayout>
</template>