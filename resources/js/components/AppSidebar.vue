<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { UserX, LayoutGrid, UsersIcon, UserCheck2, Package, Users2, ShoppingCart, ClipboardList, Phone } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { index as usersIndex } from '@/routes/users';
import { index as rolesIndex } from '@/routes/roles';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as productsIndex } from '@/routes/products';
import { index as clientsIndex } from '@/routes/clients';
import { index as ordersIndex } from '@/routes/orders';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
  },
  {
    title: 'Sales',
    href: '#',
    icon: ClipboardList,
    children: [
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
    title: 'Products',
    href: productsIndex(),
    icon: Package,
  },
];

const footerNavItems: NavItem[] = [
  // {
  //     title: 'Github Repo',
  //     href: 'https://github.com/laravel/vue-starter-kit',
  //     icon: Folder,
  // },
  // {
  //     title: 'Documentation',
  //     href: 'https://laravel.com/docs/starter-kits#vue',
  //     icon: BookOpen,
  // },
];
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
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
