<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head, router } from '@inertiajs/vue3';
import { LoaderCircle, Fingerprint } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { startAuthentication, browserSupportsWebAuthn } from '@simplewebauthn/browser';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const passkeySupported = ref(false);
const passkeyLoading = ref(false);
const passkeyError = ref('');

onMounted(() => {
    passkeySupported.value = browserSupportsWebAuthn();

    // Auto-trigger passkey prompt when login page loads
    if (passkeySupported.value) {
        loginWithPasskey();
    }
});

const loginWithPasskey = async () => {
    passkeyError.value = '';
    passkeyLoading.value = true;

    try {
        // 1. Get authentication options from Spatie's route
        const optionsResponse = await fetch('/passkeys/authentication-options', {
            headers: { 'Accept': 'application/json' },
        });

        if (!optionsResponse.ok) {
            throw new Error('Failed to get authentication options');
        }

        const optionsJSON = await optionsResponse.json();

        // 2. Prompt device biometric (Face ID / Touch ID / Fingerprint)
        const credential = await startAuthentication({ optionsJSON });

        // 3. Submit to Spatie's authenticate route
        router.post('/passkeys/authenticate', {
            start_authentication_response: JSON.stringify(credential),
            remember: true,
        }, {
            onError: () => {
                passkeyError.value = 'Authentication failed. The passkey may not be registered.';
                passkeyLoading.value = false;
            },
        });
    } catch (error: any) {
        if (error.name === 'NotAllowedError') {
            passkeyError.value = 'Passkey authentication was cancelled.';
        } else if (error.name === 'SecurityError') {
            passkeyError.value = 'Passkeys require a secure (HTTPS) connection.';
        } else {
            passkeyError.value = error.message || 'An error occurred. Please try password login.';
            console.error('Passkey auth error:', error);
        }
        passkeyLoading.value = false;
    }
};
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- Passkey Login Button -->
        <div v-if="passkeySupported" class="mb-6">
            <Button
                type="button"
                variant="outline"
                class="w-full gap-2"
                :disabled="passkeyLoading"
                @click="loginWithPasskey"
                data-test="passkey-login-button"
            >
                <LoaderCircle v-if="passkeyLoading" class="h-4 w-4 animate-spin" />
                <Fingerprint v-else class="h-4 w-4" />
                Sign in with Passkey
            </Button>
            <InputError v-if="passkeyError" :message="passkeyError" class="mt-2" />

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">Or continue with password</span>
                </div>
            </div>
        </div>

        <Form
            v-bind="AuthenticatedSessionController.store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address or phone</Label>
                    <Input
                        id="email"
                        type="text"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="username"
                        placeholder="email@example.com or +1234567890"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5"> Forgot password? </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="processing" data-test="login-button">
                    <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
                    Log in
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
