<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { LoaderCircle, MessageCircle, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
  userName?: string | null;
  existingPhone?: string | null;
}>();

type Phase = 'phone' | 'otp';

const phase = ref<Phase>('phone');
const deepLink = ref<string | null>(null);

const phoneForm = useForm({ phone: props.existingPhone ?? '' });
const otpForm = useForm({ phone: '', code: '' });

const requestOtp = () => {
  phoneForm.post('/account/setup-phone/request', {
    preserveScroll: true,
    onSuccess: (page) => {
      const p = page.props as Record<string, any>;
      otpForm.phone = p.phone ?? phoneForm.phone;
      deepLink.value = p.deep_link ?? null;
      phase.value = 'otp';
    },
  });
};

const verifyOtp = () => {
  otpForm.post('/account/setup-phone/verify', { preserveScroll: true });
};

const logout = () => router.post('/logout');
</script>

<template>
  <AuthBase
    title="One more step"
    description="Link your phone to Telegram to keep your account secure."
  >
    <Head title="Set up phone login" />

    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-900/40 px-4 py-3 text-sm">
      <p class="font-medium text-amber-900 dark:text-amber-100">
        <ShieldCheck class="inline h-4 w-4 mr-1" />
        Phone verification is required
      </p>
      <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-200/80">
        We've switched to Telegram-based sign-in. {{ userName ? `Hi ${userName} —` : '' }} please verify your phone once and you're done.
      </p>
    </div>

    <div v-if="phase === 'phone'" class="flex flex-col gap-5">
      <form @submit.prevent="requestOtp" class="flex flex-col gap-4">
        <div class="grid gap-2">
          <Label for="phone">Phone number</Label>
          <Input
            id="phone"
            v-model="phoneForm.phone"
            type="tel"
            autocomplete="tel"
            placeholder="+992 …"
            required
            autofocus
          />
          <InputError :message="phoneForm.errors.phone" />
        </div>

        <Button type="submit" :disabled="phoneForm.processing" class="w-full">
          <LoaderCircle v-if="phoneForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          <MessageCircle v-else class="mr-2 h-4 w-4" />
          Send code via Telegram
        </Button>
      </form>

      <button
        type="button"
        @click="logout"
        class="text-center text-xs text-muted-foreground hover:text-foreground"
      >
        Sign out instead
      </button>
    </div>

    <div v-else class="flex flex-col gap-5">
      <div class="rounded-xl border border-sky-200 bg-sky-50 dark:bg-sky-900/20 dark:border-sky-900/40 px-4 py-3 text-sm">
        <p class="font-medium text-sky-900 dark:text-sky-100">Open Telegram to receive your code</p>
        <a
          v-if="deepLink"
          :href="deepLink"
          target="_blank"
          rel="noopener"
          class="mt-3 inline-flex items-center gap-2 rounded-full bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
        >
          <MessageCircle class="h-4 w-4" />
          Open Telegram bot
        </a>
        <p v-else class="mt-3 text-xs text-red-700">
          Telegram bot isn't configured yet. Check storage/logs/laravel.log for the code (dev mode).
        </p>
      </div>

      <form @submit.prevent="verifyOtp" class="flex flex-col gap-4">
        <div class="grid gap-2">
          <Label for="code">6-digit code</Label>
          <Input
            id="code"
            v-model="otpForm.code"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            placeholder="123456"
            class="text-center tracking-[0.5em] font-mono text-lg"
            required
            autofocus
          />
          <InputError :message="otpForm.errors.code" />
        </div>

        <Button type="submit" :disabled="otpForm.processing" class="w-full">
          <LoaderCircle v-if="otpForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Verify & finish
        </Button>

        <button
          type="button"
          @click="phase = 'phone'"
          class="text-center text-xs text-muted-foreground hover:text-foreground"
        >
          ← Use a different phone
        </button>
      </form>
    </div>
  </AuthBase>
</template>
