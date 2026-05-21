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

type Phase = 'details' | 'otp';

const phase = ref<Phase>('details');
const deepLink = ref<string | null>(null);

const detailsForm = useForm({ name: '', phone: '' });
const otpForm = useForm({ name: '', phone: '', code: '' });

const requestOtp = () => {
  detailsForm.post('/register/otp/request', {
    preserveScroll: true,
    onSuccess: (page) => {
      const flow = (page.props as Record<string, any>).otpFlow ?? {};
      otpForm.name = flow.name ?? detailsForm.name;
      otpForm.phone = flow.phone ?? detailsForm.phone;
      deepLink.value = flow.deep_link ?? null;
      phase.value = 'otp';
    },
  });
};

const verifyOtp = () => {
  otpForm.post('/register/otp/verify', { preserveScroll: true });
};

const backToDetails = () => {
  phase.value = 'details';
  otpForm.reset('code');
  deepLink.value = null;
};
</script>

<template>
  <AuthBase
    title="Create your account"
    description="Just your name and phone — we'll send a Telegram code to verify."
  >
    <Head title="Register" />

    <div v-if="phase === 'details'" class="flex flex-col gap-5">
      <form @submit.prevent="requestOtp" class="flex flex-col gap-4">
        <div class="grid gap-2">
          <Label for="name">Full name</Label>
          <Input
            id="name"
            v-model="detailsForm.name"
            autocomplete="name"
            placeholder="Your name"
            required
            autofocus
          />
          <InputError :message="detailsForm.errors.name" />
        </div>
        <div class="grid gap-2">
          <Label for="phone">Phone number</Label>
          <Input
            id="phone"
            v-model="detailsForm.phone"
            type="tel"
            autocomplete="tel"
            placeholder="+992 …"
            required
          />
          <InputError :message="detailsForm.errors.phone" />
        </div>

        <Button type="submit" :disabled="detailsForm.processing" class="w-full">
          <LoaderCircle v-if="detailsForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          <MessageCircle v-else class="mr-2 h-4 w-4" />
          Continue with Telegram
        </Button>
      </form>

      <div class="text-center text-sm text-muted-foreground">
        Already have an account?
        <TextLink href="/login" class="ml-1">Sign in</TextLink>
      </div>
    </div>

    <div v-else class="flex flex-col gap-5">
      <div class="rounded-xl border border-sky-200 bg-sky-50 dark:bg-sky-900/20 dark:border-sky-900/40 px-4 py-3 text-sm">
        <p class="font-medium text-sky-900 dark:text-sky-100">Verify with Telegram</p>
        <p class="mt-1 text-xs text-sky-800/80 dark:text-sky-200/80">
          Open the bot and confirm your number. You'll get a 6-digit code to paste below.
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
            class="text-center tracking-[0.5em] font-mono text-lg"
            required
            autofocus
          />
          <InputError :message="otpForm.errors.code" />
        </div>

        <Button type="submit" :disabled="otpForm.processing" class="w-full">
          <LoaderCircle v-if="otpForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Create my account
        </Button>

        <button
          type="button"
          @click="backToDetails"
          class="text-center text-xs text-muted-foreground hover:text-foreground"
        >
          ← Edit my details
        </button>
      </form>
    </div>
  </AuthBase>
</template>
