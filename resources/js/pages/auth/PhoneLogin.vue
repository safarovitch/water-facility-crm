<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, MessageCircle } from 'lucide-vue-next';
import { ref } from 'vue';

type Phase = 'phone' | 'otp';

const phase = ref<Phase>('phone');
const deepLink = ref<string | null>(null);
const verifiedPhone = ref('');

const phoneForm = useForm({ phone: '' });
const otpForm = useForm({ phone: '', code: '' });

const requestOtp = () => {
  phoneForm.post('/login/otp/request', {
    preserveScroll: true,
    onSuccess: (page) => {
      const props = page.props as Record<string, any>;
      verifiedPhone.value = props.phone ?? phoneForm.phone;
      deepLink.value = props.deep_link ?? null;
      otpForm.phone = verifiedPhone.value;
      phase.value = 'otp';
    },
  });
};

const verifyOtp = () => {
  otpForm.post('/login/otp/verify', {
    preserveScroll: true,
  });
};

const backToPhone = () => {
  phase.value = 'phone';
  otpForm.reset('code');
  deepLink.value = null;
};
</script>

<template>
  <AuthBase
    title="Sign in with your phone"
    description="We'll send a 6-digit code to your Telegram. No password required."
  >
    <Head title="Sign in" />

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

      <div class="text-center text-sm text-muted-foreground">
        New here?
        <TextLink href="/register" class="ml-1">Create an account</TextLink>
      </div>
      <div class="text-center text-xs text-muted-foreground">
        <TextLink href="/login/email">Have an email login? Use it here →</TextLink>
      </div>
    </div>

    <div v-else class="flex flex-col gap-5">
      <div class="rounded-xl border border-sky-200 bg-sky-50 dark:bg-sky-900/20 dark:border-sky-900/40 px-4 py-3 text-sm">
        <p class="font-medium text-sky-900 dark:text-sky-100">Open Telegram to receive your code</p>
        <p class="mt-1 text-xs text-sky-800/80 dark:text-sky-200/80">
          We sent a deep link to the bot for <span class="font-mono">{{ verifiedPhone }}</span>.
          Open it and the bot will DM you a 6-digit code.
        </p>
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
          Telegram bot isn't configured yet. Check your APP logs for the code (dev mode).
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
          Verify & sign in
        </Button>

        <button
          type="button"
          @click="backToPhone"
          class="text-center text-xs text-muted-foreground hover:text-foreground"
        >
          ← Use a different phone
        </button>
      </form>
    </div>
  </AuthBase>
</template>
