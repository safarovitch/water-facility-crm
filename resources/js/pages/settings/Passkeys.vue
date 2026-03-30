<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { startRegistration } from '@simplewebauthn/browser';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import InputError from '@/components/InputError.vue';
import { Fingerprint, Trash2, ShieldCheck, Smartphone } from 'lucide-vue-next';

defineProps<{
    passkeys: Array<{
        id: number;
        name: string;
        created_at: string;
        last_used_at: string | null;
    }>;
}>();

const page = usePage();
const flash = page.props.flash as Record<string, string> | undefined;

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Passkeys',
        href: '/settings/passkeys',
    },
];

const name = ref('');
const errorMsg = ref('');
const registering = ref(false);

const submitRegistration = async () => {
    if (!name.value.trim()) {
        errorMsg.value = 'Please provide a name for this passkey.';
        return;
    }

    errorMsg.value = '';
    registering.value = true;

    try {
        // 1. Request registration options from our controller
        const optionsResponse = await fetch('/settings/passkeys/register-options', {
            headers: { 'Accept': 'application/json' },
        });

        if (!optionsResponse.ok) {
            throw new Error('Failed to get registration options');
        }

        const { options } = await optionsResponse.json();

        // 2. Prompt device biometric (Face ID / Touch ID / Fingerprint)
        const credential = await startRegistration({ optionsJSON: options });

        // 3. Send back to Laravel to store
        const form = useForm({
            name: name.value,
            passkey: JSON.stringify(credential),
            passkey_options: JSON.stringify(options),
        });

        form.post('/settings/passkeys', {
            preserveScroll: true,
            onSuccess: () => {
                name.value = '';
                registering.value = false;
            },
            onError: (errors) => {
                errorMsg.value = Object.values(errors).flat().join(' ') || 'Failed to save passkey.';
                registering.value = false;
            },
        });
    } catch (error: any) {
        if (error.name === 'NotAllowedError') {
            errorMsg.value = 'Passkey registration was cancelled or denied by the device.';
        } else if (error.name === 'SecurityError') {
            errorMsg.value = 'Passkeys require a secure (HTTPS) connection.';
        } else {
            errorMsg.value = error.message || 'An error occurred during passkey registration.';
            console.error('Passkey registration error:', error);
        }
        registering.value = false;
    }
};

const deleteForm = useForm({});
const deletePasskey = (id: number) => {
    if (confirm('Are you sure you want to remove this passkey? You will no longer be able to sign in with it.')) {
        deleteForm.delete(`/settings/passkeys/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Clear the auto-register dismissal so the prompt can appear again
                localStorage.removeItem('passkey-auto-register-dismissed');
            },
        });
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Passkeys" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Info Banner -->
                <div class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                    <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-300">
                        <p class="font-medium">What are Passkeys?</p>
                        <p class="mt-1 text-blue-700 dark:text-blue-400">
                            Passkeys let you sign in securely using your device's Face ID, Touch ID, fingerprint sensor, or hardware security key — no password needed.
                        </p>
                    </div>
                </div>

                <!-- Register Section -->
                <div class="space-y-4">
                    <HeadingSmall
                        title="Register a New Passkey"
                        description="Add Face ID, Touch ID, or a security key to sign in without a password."
                    />

                    <div class="space-y-4 max-w-md">
                        <div class="grid gap-2">
                            <Label for="passkey_name">Device Name</Label>
                            <Input
                                id="passkey_name"
                                v-model="name"
                                type="text"
                                placeholder="e.g. My MacBook, Work iPhone"
                                :disabled="registering"
                                @keyup.enter="submitRegistration"
                            />
                            <InputError v-if="errorMsg" :message="errorMsg" />
                        </div>

                        <Button
                            @click="submitRegistration"
                            :disabled="registering || !name.trim()"
                            class="gap-2"
                        >
                            <Fingerprint class="h-4 w-4" />
                            {{ registering ? 'Waiting for device...' : 'Register Passkey' }}
                        </Button>
                    </div>
                </div>

                <!-- Existing Passkeys -->
                <div class="space-y-4">
                    <HeadingSmall
                        title="Your Passkeys"
                        description="Devices that can sign in to your account without a password."
                    />

                    <div v-if="passkeys.length > 0" class="divide-y rounded-lg border">
                        <div
                            v-for="passkey in passkeys"
                            :key="passkey.id"
                            class="flex items-center justify-between gap-4 p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <Smartphone class="h-5 w-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <p class="font-medium">{{ passkey.name }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        Added {{ formatDate(passkey.created_at) }}
                                        <span v-if="passkey.last_used_at">
                                            · Last used {{ formatDate(passkey.last_used_at) }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="deletePasskey(passkey.id)"
                                :disabled="deleteForm.processing"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div v-else class="rounded-lg border border-dashed p-8 text-center">
                        <Fingerprint class="mx-auto h-10 w-10 text-muted-foreground/50" />
                        <p class="mt-3 text-sm text-muted-foreground">No passkeys registered yet.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">
                            Register one above to enable passwordless sign in.
                        </p>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
