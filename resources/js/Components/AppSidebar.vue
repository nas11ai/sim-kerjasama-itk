<script setup lang="ts">
import { Calendar, ChevronDown, Home, Inbox, Search, Settings } from "lucide-vue-next";
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
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ResponsiveNavLink from "./ResponsiveNavLink.vue";

const page = usePage();
const user = page.props.auth.user;

const isUserMenuOpen = ref(false);
const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
};

const getInitial = (name: string) => name ? name[0].toUpperCase() : 'U';

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
                    <SidebarMenuButton size="lg" tooltip="My App Dashboard" class="flex flex-row">
                        <div
                            class="flex aspect-square size-6 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <Home class="size-4" />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">SIM Kerjasama ITK</span>
                            <span class="truncate text-xs">Dashboard</span>
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup>
                <SidebarGroupLabel>Menu</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in items" :key="item.title">
                            <SidebarMenuButton :tooltip="item.title">
                                <component :is="item.icon" class="size-4" />
                                <span>{{ item.title }}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <div class="relative w-full">
                <button @click="toggleUserMenu" :disabled="isCollapsed"
                    class="w-full flex items-center space-x-2 py-3 rounded-lg transition-colors" :class="[
                        isCollapsed ? '' : 'hover:bg-gray-100'
                    ]">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-full bg-red text-black">
                        <span class="text-sm font-semibold">{{ getInitial(user.name) }}</span>
                    </div>

                    <!-- Hide user name/email if collapsed -->
                    <div class="flex-1 text-left truncate transition-opacity duration-200"
                        :class="{ 'opacity-0 w-0': isCollapsed }">
                        <div class="text-sm font-medium text-gray-900 truncate">
                            {{ user.name }}
                        </div>
                        <div class="text-xs text-gray-500 truncate">
                            {{ user.email }}
                        </div>
                    </div>

                    <!-- Chevron icon with rotation and collapsed logic -->
                    <ChevronDown class="w-4 h-4 text-gray-500 transform transition-transform duration-300" :class="{
                        'rotate-180': isUserMenuOpen,
                        'opacity-0 w-0': isCollapsed
                    }" />
                </button>

                <!-- Dropdown -->
                <div v-if="isUserMenuOpen && !isCollapsed"
                    class="absolute bottom-12 left-4 right-4 z-50 w-[calc(100%-2rem)] rounded-lg border border-gray-200 bg-white shadow-md">
                    <ResponsiveNavLink :href="route('profile.edit')">
                        Profile
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('logout')" method="post" as="button" class="text-red-500">
                        Log Out
                    </ResponsiveNavLink>
                </div>
            </div>
        </SidebarFooter>
    </Sidebar>
</template>
