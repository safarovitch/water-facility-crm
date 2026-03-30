<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { browserSupportsWebAuthn, startRegistration } from '@simplewebauthn/browser';

const STORAGE_KEY = 'passkey-auto-register-dismissed';

const page = usePage();

onMounted(async () => {
    const shouldRegister = (page.props as any).shouldRegisterPasskey;

    if (!shouldRegister || !browserSupportsWebAuthn()) return;

    // Don't prompt again if user already dismissed or registered
    if (localStorage.getItem(STORAGE_KEY)) return;

    // Small delay so the page renders first
    await new Promise(r => setTimeout(r, 800));

    try {
        // 1. Get registration options
        const optionsResponse = await fetch('/settings/passkeys/register-options', {
            headers: { 'Accept': 'application/json' },
        });

        if (!optionsResponse.ok) return;

        const { options } = await optionsResponse.json();

        // 2. Prompt device biometric
        const credential = await startRegistration({ optionsJSON: options });

        // 3. Store the passkey
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const storeResponse = await fetch('/settings/passkeys', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                name: detectDeviceName(),
                passkey: JSON.stringify(credential),
                passkey_options: JSON.stringify(options),
            }),
        });

        if (storeResponse.ok) {
            // Mark as done so we don't prompt again
            localStorage.setItem(STORAGE_KEY, 'registered');
            // Reload props so shouldRegisterPasskey becomes false
            router.reload({ only: ['shouldRegisterPasskey'] });
        }
    } catch (e) {
        // User cancelled or error — mark as dismissed so we don't keep nagging
        localStorage.setItem(STORAGE_KEY, 'dismissed');
    }
});

function detectDeviceName(): string {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Macintosh/.test(ua)) return 'Mac';
    if (/Android/.test(ua)) return 'Android Device';
    if (/Windows/.test(ua)) return 'Windows PC';
    return 'My Device';
}
</script>

<template>
    <!-- Invisible component — handles auto-registration in background -->
</template>
