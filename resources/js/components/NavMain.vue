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
import { useI18n } from '@/composables/useI18n'

// Props
const props = defineProps<{
  items: NavItem[]
}>()

// Assign props to a variable
const items = props.items

const page = usePage()
const { t } = useI18n()
</script>

<template>
  <SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>{{ t('Platform') }}</SidebarGroupLabel>
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
              <span v-if="item.badge" class="ml-auto inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-bold text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                {{ item.badge }}
              </span>
            </Link>

            <!-- If has children, show text -->
            <div v-else class="flex items-center flex-1 justify-between">
              <div class="flex items-center">
                <component :is="item.icon" class="mr-2 w-5 h-5" />
                <span>{{ item.title }}</span>
              </div>
            </div>
          </div>
        </SidebarMenuButton>

        <!-- Always show children if they exist -->
        <SidebarMenu v-if="item.children" class="mt-1 space-y-1 pl-6" as="div">
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