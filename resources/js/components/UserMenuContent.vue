<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { adminSwitchMode } from '@/lib/admin-routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings, LayoutGrid, UserIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface Props {
    user: User;
}

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage();

const adminMode = computed(() => (page.props as any).adminMode as boolean);

const isStaff = computed(() =>
    props.user.roles?.some((r: string) => r.toLowerCase() !== 'client')
);

const handleLogout = () => router.flushAll();

const switchMode = () => {
    router.post(adminSwitchMode().url, {}, { preserveScroll: false });
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <!-- Admin mode toggle — only visible to staff/admin users -->
        <DropdownMenuItem
            v-if="isStaff"
            class="cursor-pointer"
            :class="adminMode
                ? 'text-muted-foreground'
                : 'font-medium text-primary focus:text-primary'"
            @click="switchMode"
        >
            <LayoutGrid v-if="!adminMode" class="mr-2 h-4 w-4 text-primary" />
            <UserIcon v-else class="mr-2 h-4 w-4" />
            {{ adminMode ? t('Switch to User View') : t('Switch to Admin View') }}
        </DropdownMenuItem>

        <!-- Settings -->
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                {{ t('Settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" :href="logout()" @click="handleLogout" as="button" data-test="logout-button">
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('Log out') }}
        </Link>
    </DropdownMenuItem>
</template>
