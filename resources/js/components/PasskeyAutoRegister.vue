<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { browserSupportsWebAuthn, startRegistration } from '@simplewebauthn/browser';

const page = usePage();
const prompted = ref(false);

onMounted(async () => {
    const shouldRegister = (page.props as any).shouldRegisterPasskey;

    if (!shouldRegister || !browserSupportsWebAuthn() || prompted.value) return;

    // Small delay so the page renders first
    await new Promise(r => setTimeout(r, 800));

    prompted.value = true;

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

        await fetch('/settings/passkeys', {
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
    } catch (e) {
        // User cancelled or error — silently ignore, they can register later
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
