<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

interface Props {
  mustVerifyEmail: boolean;
  status?: string;
}

defineProps<Props>();

const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
  {
    title: t('Profile settings'),
    href: edit().url,
  },
]);

const page = usePage();
const user = page.props.auth.user;
const isClient = (user as any).roles?.includes('Client') && !(user as any).roles?.some((r: string) => ['Admin','Manager','Operator','Currier'].includes(r));
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">

    <Head :title="t('Profile settings')" />

    <SettingsLayout>
      <div class="flex flex-col space-y-6">
        <!-- Header Card (matched with Profile.vue) -->
        <div class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-2xl font-bold text-primary">
            {{ user.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div>
            <h2 class="text-xl font-semibold text-foreground">{{ user.name }}</h2>
            <p class="text-sm text-muted-foreground">{{ user.email }}</p>
            <div class="mt-1 flex gap-2">
              <span v-for="role in (user as any).roles" :key="role" class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                {{ role }}
              </span>
            </div>
          </div>
        </div>

        <!-- Form Card -->
        <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
          <HeadingSmall :title="t('Profile information')" :description="t('Update your name and email address')" class="mb-6" />

          <Form v-bind="ProfileController.update.form()" class="space-y-6" v-slot="{ errors, processing, recentlySuccessful }">
            <div class="grid gap-2">
              <Label for="name">{{ t('Name') }}</Label>
              <Input id="name" class="mt-1 block w-full" name="name" :default-value="user.name" required autocomplete="name" :placeholder="t('Full name')" />
              <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
              <Label for="email">{{ t('Email address') }}</Label>
              <Input id="email" type="email" class="mt-1 block w-full" name="email" :default-value="user.email" required autocomplete="username" :placeholder="t('Email address')" />
              <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
              <Label for="phone">{{ t('Phone number') }}</Label>
              <Input id="phone" type="text" class="mt-1 block w-full" name="phone" :default-value="(user as any).phone" required autocomplete="tel" :placeholder="t('Phone number')" />
              <InputError class="mt-2" :message="errors.phone" />
            </div>

            <div v-if="!isClient" class="grid gap-2">
              <Label for="sip_extension">{{ t('SIP Extension') }}</Label>
              <Input id="sip_extension" class="mt-1 block w-full" name="sip_extension" :default-value="(user as any).sip_extension" placeholder="e.g. 101" />
              <InputError class="mt-2" :message="errors.sip_extension" />
            </div>

            <div v-if="!isClient" class="grid gap-2">
              <Label for="sip_password">{{ t('SIP Password') }}</Label>
              <Input id="sip_password" type="text" class="mt-1 block w-full" name="sip_password" :default-value="(user as any).sip_password" :placeholder="t('SIP Password')" />
              <InputError class="mt-2" :message="errors.sip_password" />
            </div>

            <div v-if="mustVerifyEmail && !user.email_verified_at">
              <p class="-mt-4 text-sm text-muted-foreground">
                {{ t('Your email address is unverified.') }}
                <Link :href="send()" as="button" class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500">
                  {{ t('Click here to resend the verification email.') }}
                </Link>
              </p>

              <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                {{ t('A new verification link has been sent to your email address.') }}
              </div>
            </div>

            <div class="flex items-center gap-4">
              <Button :disabled="processing" data-test="update-profile-button">{{ t('Save') }}</Button>

              <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                <p v-show="recentlySuccessful" class="text-sm text-neutral-600">{{ t('Saved.') }}</p>
              </Transition>
            </div>
          </Form>
        </div>
      </div>

      <DeleteUser />
    </SettingsLayout>
  </AppLayout>
</template>
