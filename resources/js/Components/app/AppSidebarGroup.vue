<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/Components/ui/sidebar'
import { isNavItemActive, type NavGroup } from '@/lib/navigation'

defineProps<{ group: NavGroup }>()

const isActive = (pattern: string) => route().current(pattern)
</script>

<template>
    <SidebarGroup>
        <SidebarGroupLabel v-if="group.label">{{ group.label }}</SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.label">
                    <SidebarMenuButton as-child :is-active="isNavItemActive(item, isActive)">
                        <Link :href="route(item.routeName ?? '')">
                            <component :is="item.icon" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
