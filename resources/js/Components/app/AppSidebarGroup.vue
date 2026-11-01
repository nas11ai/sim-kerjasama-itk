<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/Components/ui/sidebar'
import { isNavItemActive, navItemCount, type VisibleNavGroup } from '@/lib/navigation'
import type { NavigationFlags } from '@/types'

defineProps<{ group: VisibleNavGroup; flags: NavigationFlags }>()

const isActive = (pattern: string) => route().current(pattern)
</script>

<template>
    <SidebarGroup>
        <SidebarGroupLabel v-if="group.label">{{ group.label }}</SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.label">
                    <SidebarMenuButton as-child :is-active="isNavItemActive(item, isActive)">
                        <Link :href="route(item.routeName)">
                            <component :is="item.icon" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuBadge v-if="navItemCount(item, flags) !== null">
                        {{ navItemCount(item, flags) }}
                    </SidebarMenuBadge>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
