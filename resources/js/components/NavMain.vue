<script setup lang="ts">
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { urlIsActive } from '@/lib/utils'
import { type NavItem } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'
import { ref, watchEffect } from 'vue'

// Props
const props = defineProps<{
  items: NavItem[]
}>()

// Assign props to a variable
const items = props.items

const page = usePage()

// Track which dropdown is open
const openDropdown = ref<string | null>(null)

function toggleDropdown(title: string) {
  openDropdown.value = openDropdown.value === title ? null : title
}

// Automatically open dropdown if any child is active
watchEffect(() => {
  items.forEach(item => {
    if (item.children && item.children.some(child => urlIsActive(child.href, page.url))) {
      openDropdown.value = item.title
    }
  })
})
</script>

<template>
  <SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>Platform</SidebarGroupLabel>
    <SidebarMenu>
      <SidebarMenuItem v-for="item in items" :key="item.title">
        <SidebarMenuButton as-child :is-active="urlIsActive(item.href, page.url) || (item.children && item.children.some(child => urlIsActive(child.href, page.url)))" :tooltip="item.title">
          <div class="flex items-center w-full">
            <!-- If no children, just a normal link or action button -->
            <button v-if="!item.children && item.action" @click.prevent="item.action" class="flex items-center flex-1">
              <component :is="item.icon" class="mr-2 w-5 h-5" />
              <span>{{ item.title }}</span>
            </button>
            <Link v-else-if="!item.children" :href="item.href" class="flex items-center flex-1">
              <component :is="item.icon" class="mr-2 w-5 h-5" />
              <span>{{ item.title }}</span>
            </Link>

            <!-- If has children, show text + chevron -->
            <div v-else class="flex items-center flex-1 justify-between cursor-pointer" @click="toggleDropdown(item.title)">
              <div class="flex items-center">
                <component :is="item.icon" class="mr-2 w-5 h-5" />
                <span>{{ item.title }}</span>
              </div>
              <ChevronDown class="transition-transform" :class="{ 'rotate-180': openDropdown === item.title }" width="16" height="16" />
            </div>
          </div>
        </SidebarMenuButton>

        <!-- Inline dropdown for children -->
        <SidebarMenu v-if="item.children && openDropdown === item.title" class="mt-1 space-y-1 pl-6" as="div">
          <SidebarMenuItem v-for="child in item.children" :key="child.title">
            <SidebarMenuButton as-child :is-active="urlIsActive(child.href, page.url)" :tooltip="child.title">
              <Link :href="child.href" class="flex items-center">
                <component :is="child.icon" class="mr-2 w-5 h-5" />
                <span>{{ child.title }}</span>
                <span v-if="child.badge" class="ml-auto inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                  {{ child.badge }}
                </span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>