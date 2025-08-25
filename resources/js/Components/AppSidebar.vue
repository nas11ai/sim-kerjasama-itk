<script setup lang="ts">
import {
    Calendar,
    ChevronDown,
    Home,
    FileText,
    Settings,
    Shield,
    Clock,
    Users,
    Building2,
    GraduationCap
} from "lucide-vue-next";
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
} from "@/Components/ui/sidebar";
import { usePage, Link } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ResponsiveNavLink from "./ResponsiveNavLink.vue";

const page = usePage();
const user = page.props.auth.user;

// console.log('User object:', user);

const hasAdminAccess = computed(() => {
    if (!user || !user.roles) {
        return false;
    }
    return user.roles.includes('admin') || user.roles.includes('Super Admin');
});

// console.log('Has Admin Access:', hasAdminAccess.value);

const isUserMenuOpen = ref(false);
const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
};

const getInitial = (name: string) => name ? name[0].toUpperCase() : 'U';

const { state } = useSidebar();
const isCollapsed = computed(() => state.value === 'collapsed');

// Current route helper
const isCurrentRoute = (routeName: string) => {
    return page.url.startsWith(route(routeName));
};

// Menu items based on available routes
const menuItems = [
    {
        title: "Dashboard",
        url: route("user.dashboard"),
        icon: Home,
        routeName: "user.dashboard"
    }
];

// Form Management Group
const formManagementItems = [
    {
        title: "Forms",
        url: route("admin.forms.index"),
        icon: FileText,
        routeName: "admin.forms.index"
    },
    {
        title: "Form Phases",
        url: route("admin.form-phases.index"),
        icon: Settings,
        routeName: "admin.form-phases.index"
    },
    {
        title: "Form Access Controls",
        url: route("admin.form-access-controls.index"),
        icon: Shield,
        routeName: "admin.form-access-controls.index"
    }
];

// Academic Management Group
const academicManagementItems = [
    {
        title: "Faculties",
        url: route("admin.faculties.index"),
        icon: Building2,
        routeName: "admin.faculties.index"
    },
    {
        title: "Study Programs",
        url: route("admin.faculties.study-programs"),
        icon: GraduationCap,
        routeName: "admin.faculties.study-programs"
    }
];

// Submission Management Group
const submissionManagementItems = [
    {
        title: "Submission Periods",
        url: route("admin.submission-periods.index"),
        icon: Clock,
        routeName: "admin.submission-periods.index"
    }
];

// User Management Group (if needed in the future)
const userManagementItems = [
    {
        title: "Profile Settings",
        url: route("profile.edit"),
        icon: Users,
        routeName: "profile.edit"
    }
];
</script>

<template>
    <Sidebar variant="inset" collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" tooltip="My App Dashboard" class="flex flex-row" as-child>
                        <Link :href="route('user.dashboard')">
                        <div
                            class="flex aspect-square size-6 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <Home class="size-4" />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">SIM Kerjasama ITK</span>
                            <span class="truncate text-xs">Dashboard</span>
                        </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Main Menu -->
            <SidebarGroup v-if="hasAdminAccess">
                <SidebarGroupLabel>Main Menu</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in menuItems" :key="item.title">
                            <SidebarMenuButton :tooltip="item.title" as-child
                                :isActive="isCurrentRoute(item.routeName)">
                                <Link :href="item.url">
                                <component :is="item.icon" class="size-4" />
                                <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Form Management -->
            <SidebarGroup v-if="hasAdminAccess">
                <SidebarGroupLabel>Form Management</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in formManagementItems" :key="item.title">
                            <SidebarMenuButton :tooltip="item.title" as-child
                                :isActive="isCurrentRoute(item.routeName)">
                                <Link :href="item.url">
                                <component :is="item.icon" class="size-4" />
                                <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Academic Management -->
            <SidebarGroup v-if="hasAdminAccess">
                <SidebarGroupLabel>Academic Management</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in academicManagementItems" :key="item.title">
                            <SidebarMenuButton :tooltip="item.title" as-child
                                :isActive="isCurrentRoute(item.routeName)">
                                <Link :href="item.url">
                                <component :is="item.icon" class="size-4" />
                                <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Submission Management -->
            <SidebarGroup v-if="hasAdminAccess">
                <SidebarGroupLabel>Submission Management</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in submissionManagementItems" :key="item.title">
                            <SidebarMenuButton :tooltip="item.title" as-child
                                :isActive="isCurrentRoute(item.routeName)">
                                <Link :href="item.url">
                                <component :is="item.icon" class="size-4" />
                                <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- User Management -->
            <SidebarGroup>
                <SidebarGroupLabel>User Settings</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in userManagementItems" :key="item.title">
                            <SidebarMenuButton :tooltip="item.title" as-child
                                :isActive="isCurrentRoute(item.routeName)">
                                <Link :href="item.url">
                                <component :is="item.icon" class="size-4" />
                                <span>{{ item.title }}</span>
                                </Link>
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
