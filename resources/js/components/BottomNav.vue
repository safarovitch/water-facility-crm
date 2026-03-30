<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ShoppingCart, Wallet, Wrench, Menu } from 'lucide-vue-next';
import { computed } from 'vue';
import { useSidebar } from '@/components/ui/sidebar/utils';
import { cn } from '@/lib/utils';

const page = usePage();
const { toggleSidebar } = useSidebar();

// adminMode is shared via HandleInertiaRequests — true only when staff has toggled admin mode on
const adminMode = computed(() => (page.props as any).adminMode as boolean);

const navItems = computed(() => {
  const items = [
    { title: 'Home', href: '/dashboard', icon: LayoutGrid },
    { title: 'Orders', href: '/orders', icon: ShoppingCart },
  ];

  if (adminMode.value) {
    items.push(
        { title: 'Accounting', href: '/financial-records', icon: Wallet },
        { title: 'Inventory', href: '/inventory-items', icon: Wrench }
    );
  }

  return items;
});

const isActive = (href: string) => {
  return page.url === href || page.url.startsWith(href);
};
</script>

<template>
  <nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-background/95 backdrop-blur-md border-t border-border flex items-center justify-around h-16 pb-safe border-sidebar-border dark:bg-sidebar/95">
    <Link
      v-for="item in navItems"
      :key="item.href"
      :href="item.href"
      class="flex flex-col items-center justify-center flex-1 h-full gap-1 transition-colors"
      :class="cn(
        'text-muted-foreground hover:text-primary',
        isActive(item.href) && 'text-primary font-semibold'
      )"
    >
      <component :is="item.icon" class="h-5 w-5" />
      <span class="text-[10px] leading-none">{{ item.title }}</span>
    </Link>

    <button
      @click="toggleSidebar"
      class="flex flex-col items-center justify-center flex-1 h-full gap-1 text-muted-foreground hover:text-primary transition-colors"
    >
       <Menu class="h-5 w-5" />
       <span class="text-[10px] leading-none">Menu</span>
    </button>
  </nav>
</template>

<style scoped>
.pb-safe {
  padding-bottom: env(safe-area-inset-bottom);
}
</style>
