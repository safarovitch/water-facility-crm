<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { adminDashboard } from '@/lib/admin-routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { UserX, UsersIcon, UserCheck2, Package, Users2, ClipboardList, Activity, Truck, Box, LayoutGrid, ShoppingCart, Wallet, Wrench, Phone, RotateCcw, CalendarClock } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Use route() helper where possible or update hardcoded paths
const routeUsersIndex = () => '/admin/users/index';
const routeRolesIndex = () => '/admin/roles/index';
const routePermissionsIndex = () => '/admin/permissions/index';
const routeProductsIndex = () => '/admin/products/index';
const routeClientsIndex = () => '/admin/clients/index';
const routeOrdersIndexAdmin = () => '/admin/orders/index';
const routeOrdersAssignments = () => '/admin/orders/assignments';
const routeForecastsIndex = () => '/admin/forecasts/index';
const routeCurriersActivities = () => '/admin/curriers/activities';
const routeFinancialIndex = () => '/admin/financial-records';
const routeInventoryIndex = () => '/admin/inventory-items';
const routeRawMaterialsIndex = () => '/admin/raw-materials';
import { computed, ref } from 'vue';
import { useOrderNotifications } from '@/composables/useOrderNotifications';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
const { pendingCount } = useOrderNotifications() || { pendingCount: ref(0) };

const page = usePage();
const can = computed(() => page.props.auth.can ?? {});
const isStaff = computed(() => !!can.value.accessAdmin);
const isOnlyClient = computed(() => !isStaff.value);

// adminMode is shared via HandleInertiaRequests — true only when staff has toggled admin mode on
const adminMode = computed(() => (page.props as any).adminMode as boolean);

const mainNavItems = computed((): NavItem[] => {
  const items: NavItem[] = [];

  // Show My Profile only in user mode (not in admin mode)
  if (!adminMode.value) {
    items.push({
      title: t('My Profile'),
      href: dashboard(),
      icon: LayoutGrid,
    });
  }

  // Admin/staff nav items — shown ONLY when admin mode is active. Every
  // entry is gated by an ability flag that mirrors a server-side route
  // restriction, so couriers only see their scoped subset (dashboard,
  // orders, clients, forecasts, expenses).
  if (isStaff.value && adminMode.value) {
    items.push({
      title: can.value.viewAdminStats ? t('Admin Dashboard') : t('My Dashboard'),
      href: adminDashboard(),
      icon: LayoutGrid,
    });

    const salesChildren: NavItem[] = [];
    if (can.value.manageProducts) {
      salesChildren.push({ title: t('Products'), href: routeProductsIndex(), icon: Package });
    }
    if (can.value.manageRawMaterials) {
      salesChildren.push({ title: t('Raw Materials'), href: routeRawMaterialsIndex(), icon: Box });
    }
    if (can.value.manageClients) {
      salesChildren.push({ title: t('Clients'), href: routeClientsIndex(), icon: Users2 });
    }
    salesChildren.push({
      title: t('Orders'),
      href: routeOrdersIndexAdmin(),
      icon: ShoppingCart,
      badge: pendingCount.value > 0 ? pendingCount.value : undefined,
    });
    if (can.value.viewForecasts) {
      salesChildren.push({ title: t('Forecasts'), href: routeForecastsIndex(), icon: CalendarClock });
    }
    items.push({
      title: t('Sales'),
      href: '#',
      icon: ClipboardList,
      children: salesChildren,
    });

    if (can.value.assignCurriers || can.value.viewCurrierActivities) {
      const deliveryChildren: NavItem[] = [];
      if (can.value.assignCurriers) {
        deliveryChildren.push({ title: t('Currier Assignments'), href: routeOrdersAssignments(), icon: ClipboardList });
      }
      if (can.value.viewCurrierActivities) {
        deliveryChildren.push({ title: t('Currier Activities'), href: routeCurriersActivities(), icon: Activity });
      }
      items.push({
        title: t('Delivery'),
        href: '#',
        icon: Truck,
        children: deliveryChildren,
      });
    }

    if (can.value.manageUsers) {
      items.push({
        title: t('User management'),
        href: '#',
        icon: UsersIcon,
        children: [
          { title: t('All users'), href: routeUsersIndex(), icon: UsersIcon },
          { title: t('Roles'), href: routeRolesIndex(), icon: UserX },
          { title: t('Permissions'), href: routePermissionsIndex(), icon: UserCheck2 },
        ],
      });
    }

    if (can.value.manageSubscriptions) {
      items.push({
        title: t('Subscriptions'),
        href: '/admin/subscriptions',
        icon: RotateCcw,
      });
    }

    if (can.value.accessAccounting) {
      items.push({
        title: can.value.manageAccounting ? t('Accounting') : t('My Expenses'),
        href: routeFinancialIndex(),
        icon: Wallet,
      });
    }

    if (can.value.manageInventory) {
      items.push({
        title: t('Inventory'),
        href: routeInventoryIndex(),
        icon: Wrench,
      });
    }
  } else if (isOnlyClient.value || (isStaff.value && !adminMode.value)) {
    // In user mode (including admins in user mode): show My Orders
    items.push({
      title: t('My Orders'),
      href: '/orders/index',
      icon: ShoppingCart,
      badge: pendingCount.value > 0 ? pendingCount.value : undefined,
    });
  }

  return items;
});

const footerNavItems = computed((): NavItem[] => {
  if (isOnlyClient.value && !isStaff.value) {
    return [
      {
        title: t('Call Facility'),
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
