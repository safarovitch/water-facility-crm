<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable, show } from '@/routes/two-factor';
import { BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref, computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('Two-Factor Authentication'),
        href: show.url(),
    },
]);

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('Two-Factor Authentication')" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('Two-Factor Authentication')" :description="t('Manage your two-factor authentication settings')" />

                <div v-if="!twoFactorEnabled" class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="destructive">{{ t('Disabled') }}</Badge>

                    <p class="text-muted-foreground">
                        {{ t('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                    </p>

                    <div>
                        <Button v-if="hasSetupData" @click="showSetupModal = true"> <ShieldCheck />{{ t('Continue Setup') }} </Button>
                        <Form v-else v-bind="enable.form()" @success="showSetupModal = true" #default="{ processing }">
                            <Button type="submit" :disabled="processing"> <ShieldCheck />{{ t('Enable 2FA') }}</Button></Form
                        >
                    </div>
                </div>

                <div v-else class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="default">{{ t('Enabled') }}</Badge>

                    <p class="text-muted-foreground">
                        {{ t('With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                    </p>

                    <TwoFactorRecoveryCodes />

                    <div class="relative inline">
                        <Form v-bind="disable.form()" #default="{ processing }">
                            <Button variant="destructive" type="submit" :disabled="processing">
                                <ShieldBan />
                                {{ t('Disable 2FA') }}
                            </Button>
                        </Form>
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
