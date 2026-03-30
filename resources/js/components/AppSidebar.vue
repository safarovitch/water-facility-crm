<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { adminDashboard } from '@/lib/admin-routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { UserX, UsersIcon, UserCheck2, Package, Users2, ClipboardList, Activity, Truck, Box, LayoutGrid, ShoppingCart, Wallet, Wrench, Phone } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Use route() helper where possible or update hardcoded paths
const routeUsersIndex = () => '/admin/users/index';
const routeRolesIndex = () => '/admin/roles/index';
const routePermissionsIndex = () => '/admin/permissions/index';
const routeProductsIndex = () => '/admin/products/index';
const routeClientsIndex = () => '/admin/clients/index';
const routeOrdersIndexAdmin = () => '/admin/orders/index';
const routeOrdersAssignments = () => '/admin/orders/assignments';
const routeCurriersActivities = () => '/admin/curriers/activities';
const routeFinancialIndex = () => '/admin/financial-records';
const routeInventoryIndex = () => '/admin/inventory-items';
const routeRawMaterialsIndex = () => '/admin/raw-materials';
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
            href: routeProductsIndex(),
            icon: Package,
          },
          {
            title: 'Raw Materials',
            href: routeRawMaterialsIndex(),
            icon: Box,
          },
          {
            title: 'Clients',
            href: routeClientsIndex(),
            icon: Users2,
          },
          {
            title: 'Orders',
            href: routeOrdersIndexAdmin(),
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
            href: routeOrdersAssignments(),
            icon: ClipboardList,
          },
          {
            title: 'Currier Activities',
            href: routeCurriersActivities(),
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
            href: routeUsersIndex(),
            icon: UsersIcon,
          },
          {
            title: 'Roles',
            href: routeRolesIndex(),
            icon: UserX,
          },
          {
            title: 'Permissions',
            href: routePermissionsIndex(),
            icon: UserCheck2,
          },
        ]
      },
      {
        title: 'Accounting',
        href: routeFinancialIndex(),
        icon: Wallet,
      },
      {
        title: 'Inventory',
        href: routeInventoryIndex(),
        icon: Wrench,
      }
    );
  } else if (isOnlyClient.value || (isStaff.value && !adminMode.value)) {
    // In user mode (including admins in user mode): show My Orders
    items.push({
      title: 'My Orders',
      href: '/orders/index',
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
