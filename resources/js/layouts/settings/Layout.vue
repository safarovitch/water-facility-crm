<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { appearance } from '@/routes';
import { edit as editPassword } from '@/routes/password';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();
const user = page.props.auth.user as any;
const isClient = user?.roles?.includes('Client') && !user?.roles?.some((r: string) => ['Admin','Manager','Operator','Currier'].includes(r));

const sidebarNavItems = computed((): NavItem[] => [
    { title: t('Profile'),     href: edit() },
    { title: t('Password'),    href: editPassword() },
    { title: t('Passkeys'),    href: '/settings/passkeys' },
    ...(!isClient ? [{ title: t('Two-Factor Auth'), href: show() }] : []),
    { title: t('Appearance'),  href: appearance() },
]);

const currentPath = typeof window !== undefined ? window.location.pathname : '';
</script>

<template>
    <div>
        <Heading :title="t('Settings')" :description="t('Manage your profile and account settings')" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': urlIsActive(item.href, currentPath) }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
