<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, KeyRound } from 'lucide-vue-next';

const form = useForm({ name: '', phone: '', pin: '', pin_confirmation: '' });

const submit = () => {
  form.post('/register', {
    preserveScroll: true,
    onError: () => form.reset('pin', 'pin_confirmation'),
  });
};
</script>

<template>
  <AuthBase
    title="Create your account"
    description="Your name, phone number, and a 4-6 digit PIN you'll use to sign in."
  >
    <Head title="Register" />

    <div class="flex flex-col gap-5">
      <form @submit.prevent="submit" class="flex flex-col gap-4">
        <div class="grid gap-2">
          <Label for="name">Full name</Label>
          <Input
            id="name"
            v-model="form.name"
            autocomplete="name"
            placeholder="Your name"
            required
            autofocus
            class="h-11"
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="phone">Phone number</Label>
          <Input
            id="phone"
            v-model="form.phone"
            type="tel"
            autocomplete="tel"
            placeholder="+992 …"
            required
            class="h-11"
          />
          <InputError :message="form.errors.phone" />
        </div>

        <div class="grid gap-2">
          <Label for="pin">Choose a PIN code (4-6 digits)</Label>
          <p class="text-xs text-muted-foreground -mt-1">You'll use this PIN to sign in.</p>
          <Input
            id="pin"
            v-model="form.pin"
            type="password"
            inputmode="numeric"
            autocomplete="new-password"
            maxlength="6"
            placeholder="••••"
            required
            class="h-11 text-center tracking-[0.3em] font-mono text-lg"
          />
          <InputError :message="form.errors.pin" />
        </div>

        <div class="grid gap-2">
          <Label for="pin_confirmation">Confirm PIN code</Label>
          <Input
            id="pin_confirmation"
            v-model="form.pin_confirmation"
            type="password"
            inputmode="numeric"
            autocomplete="new-password"
            maxlength="6"
            placeholder="••••"
            required
            class="h-11 text-center tracking-[0.3em] font-mono text-lg"
          />
          <InputError :message="form.errors.pin_confirmation" />
        </div>

        <Button type="submit" :disabled="form.processing" class="w-full h-11">
          <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
          <KeyRound v-else class="mr-2 h-4 w-4" />
          Create my account
        </Button>
      </form>

      <div class="text-center text-sm text-muted-foreground">
        Already have an account?
        <TextLink href="/login" class="ml-1">Sign in</TextLink>
      </div>
    </div>
  </AuthBase>
</template>
