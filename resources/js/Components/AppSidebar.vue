<!-- resources/js/Components/AppSidebar.vue - Alternative Version -->
<script setup lang="ts">
import { computed } from 'vue';
import { Calendar, Home, Inbox, Search, Settings } from "lucide-vue-next";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from "@/components/ui/sidebar";

const { state } = useSidebar();
const isCollapsed = computed(() => state.value === 'collapsed');

const items = [
    {
        title: "Dashboard",
        url: route("dashboard"),
        icon: Home,
    },
    {
        title: "Inbox",
        url: "#",
        icon: Inbox,
    },
    {
        title: "Calendar",
        url: "#",
        icon: Calendar,
    },
    {
        title: "Search",
        url: "#",
        icon: Search,
    },
    {
        title: "Settings",
        url: "#",
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar variant="inset" collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton :size="isCollapsed ? 'default' : 'lg'" as-child
                        :tooltip="isCollapsed ? 'My App' : undefined">
                        <a href="/" :class="[
                            'flex items-center',
                            isCollapsed ? 'justify-center' : 'gap-2'
                        ]">
                            <div
                                class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                <Home class="size-4" />
                            </div>
                            <div v-if="!isCollapsed" class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">My App</span>
                                <span class="truncate text-xs">Dashboard</span>
                            </div>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup>
                <SidebarGroupLabel v-if="!isCollapsed">Menu</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in items" :key="item.title">
                            <SidebarMenuButton as-child :tooltip="isCollapsed ? item.title : undefined">
                                <a :href="item.url" :class="[
                                    'flex items-center',
                                    isCollapsed ? 'justify-center' : 'gap-2'
                                ]">
                                    <component :is="item.icon" class="size-4" />
                                    <span v-if="!isCollapsed">{{ item.title }}</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child :tooltip="isCollapsed ? 'User Profile' : undefined">
                        <a href="#" :class="[
                            'flex items-center',
                            isCollapsed ? 'justify-center' : 'gap-2'
                        ]">
                            <div
                                class="flex aspect-square size-6 items-center justify-center rounded-full bg-sidebar-accent text-sidebar-accent-foreground">
                                <span class="text-xs font-medium">U</span>
                            </div>
                            <span v-if="!isCollapsed" class="text-sm">User Profile</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
