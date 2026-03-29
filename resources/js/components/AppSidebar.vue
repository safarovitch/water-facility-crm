<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { adminDashboard } from '@/lib/admin-routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { UserX, LayoutGrid, UsersIcon, UserCheck2, Package, Users2, ShoppingCart, ClipboardList, Phone, Activity, Wallet, Wrench, Truck, Box } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { index as usersIndex } from '@/routes/users';
import { index as rolesIndex } from '@/routes/roles';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as productsIndex } from '@/routes/products';
import { index as clientsIndex } from '@/routes/clients';
import { index as ordersIndex, assignments as assignmentsIndex } from '@/routes/orders';
import curriersIndex from '@/routes/curriers';
import { index as financialIndex } from '@/routes/financial';
import { index as inventoryIndex } from '@/routes/inventory';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const userRoles = computed(() => user.value?.roles?.map(r => r.toLowerCase()) || []);
const isStaff = computed(() => userRoles.value.some(role => role !== 'client'));
const isOnlyClient = computed(() => userRoles.value.length === 1 && userRoles.value.includes('client'));

// adminMode is shared via HandleInertiaRequests — true only when staff has toggled admin mode on
const adminMode = computed(() => (page.props as any).adminMode as boolean);

const mainNavItems = computed((): NavItem[] => {
  const items: NavItem[] = [];

  // Show My Profile only in user mode (not in admin mode)
  if (!adminMode.value) {
    items.push({
      title: 'My Profile',
      href: dashboard(),
      icon: LayoutGrid,
    });
  }

  // Admin/staff nav items — shown ONLY when admin mode is active
  if (isStaff.value && adminMode.value) {
    items.push(
      {
        title: 'Admin Dashboard',
        href: adminDashboard(),
        icon: LayoutGrid,
      },
      {
        title: 'Sales',
        href: '#',
        icon: ClipboardList,
        children: [
          {
            title: 'Products',
            href: productsIndex(),
            icon: Package,
          },
          {
            title: 'Raw Materials',
            href: '/raw-materials',
            icon: Box,
          },
          {
            title: 'Clients',
            href: clientsIndex(),
            icon: Users2,
          },
          {
            title: 'Orders',
            href: ordersIndex(),
            icon: ShoppingCart,
          },
        ],
      },
      {
        title: 'Delivery',
        href: '#',
        icon: Truck,
        children: [
          {
            title: 'Currier Assignments',
            href: assignmentsIndex(),
            icon: ClipboardList,
          },
          {
            title: 'Currier Activities',
            href: curriersIndex.activities.url(),
            icon: Activity,
          },
        ],
      },
      {
        title: 'User management',
        href: '#',
        icon: UsersIcon,
        children: [
          {
            title: 'All users',
            href: usersIndex(),
            icon: UsersIcon,
          },
          {
            title: 'Roles',
            href: rolesIndex(),
            icon: UserX,
          },
          {
            title: 'Permissions',
            href: permissionsIndex(),
            icon: UserCheck2,
          },
        ]
      },
      {
        title: 'Accounting',
        href: financialIndex().url,
        icon: Wallet,
      },
      {
        title: 'Inventory',
        href: inventoryIndex().url,
        icon: Wrench,
      }
    );
  } else if (isOnlyClient.value || (isStaff.value && !adminMode.value)) {
    // In user mode (including admins in user mode): show My Orders
    items.push({
      title: 'My Orders',
      href: ordersIndex(),
      icon: ShoppingCart,
    });
  }

  return items;
});

const footerNavItems = computed((): NavItem[] => {
  if (isOnlyClient.value && !isStaff.value) {
    return [
      {
        title: 'Call Facility',
        href: 'tel:+992884238383',
        icon: Phone,
      },
    ];
  }
  return [];
});
</script>

<template>
  <Sidebar variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="dashboard()">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
