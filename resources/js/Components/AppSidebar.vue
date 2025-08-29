<!-- resources/js/Components/AppSidebar.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
    SidebarRail,
} from '@/Components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Home,
    FileText,
    Settings,
    Calendar,
    Users,
    Building2,
    GraduationCap,
    ChevronUp,
    User2,
    LogOut,
    Shield,
    BookOpen,
    ClipboardList,
    Filter,
    Send,
    Megaphone
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userRoles = computed(() => user.value?.roles || []);

const isAdmin = computed(() =>
    userRoles.value.some((role: any) => ['Super Admin', 'Admin'].includes(role))
);

const isUser = computed(() =>
    userRoles.value.some((role: any) => ['Mahasiswa', 'Tenaga Kependidikan'].includes(role))
);

// Navigation items for admin
const adminNavItems = [
    {
        title: "Dashboard",
        url: route('admin.dashboard'),
        icon: Home,
    },
    {
        title: "Form Management",
        icon: FileText,
        items: [
            {
                title: "Forms",
                url: route('admin.forms.index'),
                icon: FileText,
            },
            {
                title: "Form Phases",
                url: route('admin.form-phases.index'),
                icon: Settings,
            },
            {
                title: "Access Controls",
                url: route('admin.form-access-controls.index'),
                icon: Shield,
            }
        ]
    },
    {
        title: "Submission Management",
        icon: Calendar,
        items: [
            {
                title: "Submission Periods",
                url: route('admin.submission-periods.index'),
                icon: Calendar,
            },
            {
                title: "View Submissions",
                url: route('admin.submissions.index'),
                icon: Send,
            }
        ]
    },
    {
        title: "Reviewer Management",
        icon: Users, // pakai icon Users dari lucide-vue-next
        items: [
            {
                title: "Reviewers",
                url: route('admin.reviewers.index'),
                icon: Users,
            },
            {
                title: "Reviewer Roles",
                url: route('admin.reviewer-roles.index'),
                icon: Filter,
            }
        ]
    },
    {
        title: "Institution Management",
        icon: Building2,
        items: [
            {
                title: "Faculties",
                url: route('admin.faculties.index'),
                icon: Building2,
            },
            {
                title: "Study Programs",
                url: route('admin.faculties.study-programs'),
                icon: GraduationCap,
            }
        ]
    },
    {
        title: "Announcement Management",
        icon: Building2,
        items: [
            {
                title: "Announcements",
                url: route('admin.announcements.index'),
                icon: Megaphone,
            }
        ]
    }
];

// Navigation items for regular users
const userNavItems = [
    {
        title: "Dashboard",
        url: route('user.dashboard'),
        icon: Home,
    },
    {
        title: "My Forms",
        icon: ClipboardList,
        items: [
            {
                title: "Active Submissions",
                url: route('user.dashboard'),
                icon: BookOpen,
            }
        ]
    },
    {
        title: "My Submissions",
        icon: Send,
        items: [
            {
                title: "View Submissions",
                url: route('user.submissions.index'),
                icon: Send,
            }
        ]
    }
];

const navItems = computed(() => {
    if (isAdmin.value) return adminNavItems;
    return userNavItems;
});

const currentUrl = computed(() => page.url);

const isActive = (url: string) => {
    return currentUrl.value === url || currentUrl.value.startsWith(url);
};

const hasActiveChild = (items: any[]) => {
    return items.some(item => isActive(item.url));
};
</script>

<template>
    <Sidebar variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <a href="/">
                            <div
                                class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <FileText class="size-4" />
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">Form System</span>
                                <span class="truncate text-xs">{{ isAdmin ? 'Administration' : 'User Portal' }}</span>
                            </div>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-for="item in navItems" :key="item.title">
                <SidebarGroupLabel v-if="item.items">{{ item.title }}</SidebarGroupLabel>

                <SidebarGroupContent>
                    <SidebarMenu>
                        <!-- Single nav item -->
                        <SidebarMenuItem v-if="!item.items">
                            <SidebarMenuButton as-child :is-active="isActive(item.url)">
                                <a :href="item.url">
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <!-- Group with subitems -->
                        <template v-else>
                            <SidebarMenuItem v-for="subItem in item.items" :key="subItem.title">
                                <SidebarMenuButton as-child :is-active="isActive(subItem.url)">
                                    <a :href="subItem.url">
                                        <component :is="subItem.icon" />
                                        <span>{{ subItem.title }}</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </template>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                                <User2 class="size-4" />
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ user?.name }}</span>
                                    <span class="truncate text-xs capitalize">
                                        {{userRoles.map((role: any) => role).join(', ')}}
                                    </span>
                                </div>
                                <ChevronUp class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                            side="bottom" align="end">
                            <DropdownMenuItem as-child>
                                <a :href="route('profile.edit')" class="cursor-pointer">
                                    <User2 class="mr-2 size-4" />
                                    Profile
                                </a>
                            </DropdownMenuItem>

                            <!-- Switch between admin/user view if user has admin role -->
                            <DropdownMenuItem v-if="isAdmin && !currentUrl.startsWith('/admin')" as-child>
                                <a :href="route('admin.dashboard')" class="cursor-pointer">
                                    <Shield class="mr-2 size-4" />
                                    Admin Panel
                                </a>
                            </DropdownMenuItem>

                            <DropdownMenuItem v-if="isAdmin && currentUrl.startsWith('/admin')" as-child>
                                <a :href="route('user.dashboard')" class="cursor-pointer">
                                    <User2 class="mr-2 size-4" />
                                    User View
                                </a>
                            </DropdownMenuItem>

                            <DropdownMenuItem as-child>
                                <a :href="route('logout')" method="post" class="cursor-pointer text-destructive">
                                    <LogOut class="mr-2 size-4" />
                                    Logout
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
