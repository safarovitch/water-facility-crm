<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ShoppingCart, Wallet, Wrench, Menu, Phone } from 'lucide-vue-next';
import { computed } from 'vue';
import { useSidebar } from '@/components/ui/sidebar/utils';
import { cn } from '@/lib/utils';

const page = usePage();
const { toggleSidebar } = useSidebar();

// adminMode is shared via HandleInertiaRequests — true only when staff has toggled admin mode on
const adminMode = computed(() => (page.props as any).adminMode as boolean);

const user = computed(() => page.props.auth.user);

const navItems = computed(() => {
  if (adminMode.value) {
    return [
      { title: 'Home', href: '/admin', icon: LayoutGrid },
      { title: 'Orders', href: '/admin/orders/index', icon: ShoppingCart },
      { title: 'Finance', href: '/admin/financial-records', icon: Wallet },
      { title: 'Inventory', href: '/admin/inventory-items', icon: Wrench }
    ];
  }
  
  return [
    { title: 'Home', href: '/dashboard', icon: LayoutGrid },
    { title: 'Orders', href: '/orders/index', icon: ShoppingCart },
  ];
});

const openDialer = () => {
  // @ts-ignore
  if (window.toggleDialpad) {
    // @ts-ignore
    window.toggleDialpad();
  }
};

const isActive = (href: string) => {
  return page.url === href || (href !== '/dashboard' && href !== '/admin' && page.url.startsWith(href));
};
</script>

<template>
  <nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-background/95 backdrop-blur-md border-t border-border flex items-center justify-around min-h-16 pb-[env(safe-area-inset-bottom)] border-sidebar-border dark:bg-sidebar/95">
    <Link
      v-for="item in navItems"
      :key="item.href"
      :href="item.href"
      class="flex flex-col items-center justify-center flex-1 h-16 gap-1 transition-colors"
      :class="cn(
        'text-muted-foreground hover:text-primary',
        isActive(item.href) && 'text-primary font-semibold'
      )"
    >
      <component :is="item.icon" class="h-5 w-5" />
      <span class="text-[10px] leading-none">{{ item.title }}</span>
    </Link>

    <!-- Phone Dialer for Staff -->
    <button
      v-if="user?.sip_extension"
      @click="openDialer"
      class="flex flex-col items-center justify-center flex-1 h-16 gap-1 text-muted-foreground hover:text-primary transition-colors"
    >
       <Phone class="h-5 w-5" />
       <span class="text-[10px] leading-none">Phone</span>
    </button>

    <button
      @click="toggleSidebar"
      class="flex flex-col items-center justify-center flex-1 h-16 gap-1 text-muted-foreground hover:text-primary transition-colors"
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
