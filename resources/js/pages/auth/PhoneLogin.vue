<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, MessageCircle, KeyRound } from 'lucide-vue-next';
import { ref } from 'vue';

type Phase = 'pin' | 'forgot' | 'otp';

const phase = ref<Phase>('pin');
const deepLink = ref<string | null>(null);
const verifiedPhone = ref('');

const pinForm = useForm({ phone: '', pin: '' });
const phoneForm = useForm({ phone: '' });
const otpForm = useForm({ phone: '', code: '' });

const loginWithPin = () => {
  pinForm.post('/login/pin', { preserveScroll: true });
};

const showForgotPin = () => {
  phoneForm.phone = pinForm.phone;
  phase.value = 'forgot';
};

const requestOtp = () => {
  phoneForm.post('/login/otp/request', {
    preserveScroll: true,
    onSuccess: (page) => {
      const flow = (page.props as Record<string, any>).otpFlow ?? {};
      verifiedPhone.value = flow.phone ?? phoneForm.phone;
      deepLink.value = flow.deep_link ?? null;
      otpForm.phone = verifiedPhone.value;
      phase.value = 'otp';
    },
  });
};

const verifyOtp = () => {
  otpForm.post('/login/otp/verify', { preserveScroll: true });
};

const backToPin = () => {
  phase.value = 'pin';
  otpForm.reset('code');
  deepLink.value = null;
};
</script>

<template>
  <AuthBase
    title="Sign in"
    :description="phase === 'pin' ? 'Enter your phone number and PIN.' : 'Verify via Telegram to reset your PIN.'"
  >
    <Head title="Sign in" />

    <!-- Phase 1: Phone + PIN (primary) -->
    <div v-if="phase === 'pin'" class="flex flex-col gap-5">
      <form @submit.prevent="loginWithPin" class="flex flex-col gap-4">
        <div class="grid gap-2">
          <Label for="phone">Phone number</Label>
          <Input
            id="phone"
            v-model="pinForm.phone"
            type="tel"
            autocomplete="tel"
            placeholder="+992 …"
            required
            autofocus
            class="h-11"
          />
          <InputError :message="pinForm.errors.phone" />
        </div>

        <div class="grid gap-2">
          <div class="flex items-center justify-between">
            <Label for="pin">PIN</Label>
            <button type="button" @click="showForgotPin" class="text-xs text-muted-foreground hover:text-primary transition-colors">
              Forgot PIN?
            </button>
          </div>
          <Input
            id="pin"
            v-model="pinForm.pin"
            type="password"
            inputmode="numeric"
            autocomplete="current-password"
            maxlength="6"
            placeholder="••••"
            required
            class="h-11 text-center tracking-[0.3em] font-mono text-lg"
          />
          <InputError :message="pinForm.errors.pin" />
        </div>

        <Button type="submit" :disabled="pinForm.processing" class="w-full h-11">
          <LoaderCircle v-if="pinForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          <KeyRound v-else class="mr-2 h-4 w-4" />
          Sign in
        </Button>
      </form>

      <div class="text-center text-sm text-muted-foreground">
        New here?
        <TextLink href="/register" class="ml-1">Create an account</TextLink>
      </div>
      <div class="text-center text-xs text-muted-foreground">
        <TextLink href="/login/email">Sign in with email instead →</TextLink>
      </div>
    </div>

    <!-- Phase 2: Forgot PIN — request Telegram OTP -->
    <div v-else-if="phase === 'forgot'" class="flex flex-col gap-5">
      <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-900/40 px-4 py-3 text-sm">
        <p class="font-medium text-amber-900 dark:text-amber-100">Reset your PIN via Telegram</p>
        <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-200/80">
          We'll send a verification code to your Telegram. After verifying, you can sign in directly.
        </p>
      </div>

      <form @submit.prevent="requestOtp" class="flex flex-col gap-4">
        <div class="grid gap-2">
          <Label for="forgot-phone">Phone number</Label>
          <Input
            id="forgot-phone"
            v-model="phoneForm.phone"
            type="tel"
            autocomplete="tel"
            placeholder="+992 …"
            required
            autofocus
            class="h-11"
          />
          <InputError :message="phoneForm.errors.phone" />
        </div>

        <Button type="submit" :disabled="phoneForm.processing" class="w-full h-11">
          <LoaderCircle v-if="phoneForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          <MessageCircle v-else class="mr-2 h-4 w-4" />
          Send code via Telegram
        </Button>

        <button type="button" @click="backToPin" class="text-center text-xs text-muted-foreground hover:text-foreground">
          ← Back to PIN login
        </button>
      </form>
    </div>

    <!-- Phase 3: Enter OTP code -->
    <div v-else class="flex flex-col gap-5">
      <div class="rounded-xl border border-sky-200 bg-sky-50 dark:bg-sky-900/20 dark:border-sky-900/40 px-4 py-3 text-sm">
        <p class="font-medium text-sky-900 dark:text-sky-100">Open Telegram to receive your code</p>
        <p class="mt-1 text-xs text-sky-800/80 dark:text-sky-200/80">
          Tap the button below, then the bot will DM you a 6-digit code for <span class="font-mono">{{ verifiedPhone }}</span>.
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
            class="h-11 text-center tracking-[0.5em] font-mono text-lg"
            required
            autofocus
          />
          <InputError :message="otpForm.errors.code" />
        </div>

        <Button type="submit" :disabled="otpForm.processing" class="w-full h-11">
          <LoaderCircle v-if="otpForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Verify & sign in
        </Button>

        <button type="button" @click="backToPin" class="text-center text-xs text-muted-foreground hover:text-foreground">
          ← Back to PIN login
        </button>
      </form>
    </div>
  </AuthBase>
</template>
