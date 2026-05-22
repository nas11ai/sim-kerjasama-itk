<!-- resources/js/Components/AppSidebar.vue -->
<script setup lang="ts">
import { computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
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
} from "@/Components/ui/sidebar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
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
    MessageSquare,
    CheckCircle,
    Clock,
    Wand2,
    Megaphone,
    User,
    Bolt,
    BookCheck,
    UserPenIcon,
    ChartPie,
    FileChartPie,
    FileUserIcon,
    FileChartPieIcon,
    ChartPieIcon,
    SquareKanbanIcon,
    UserCheck2Icon,
    FileBarChart2,
} from "lucide-vue-next";

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userRoles = computed(() => user.value?.roles || []);

const isAdmin = computed(() =>
    userRoles.value.some((role: any) => ["Super Admin", "Admin"].includes(role))
);

const isUser = computed(() =>
    userRoles.value.some((role: any) =>
        ["Mahasiswa", "Tenaga Kependidikan"].includes(role)
    )
);


const isReviewer = computed(() => {
    // Flag dikirim dari backend (middleware CheckReviewer)
    return user.value?.is_reviewer || false;
});

// Navigation items for admin
const adminNavItems = [
    {
        title: "Dashboard",
        url: route("admin.dashboard"),
        icon: Home,
    },
    {
        title: "Manajemen User & Role",
        icon: FileText,
        items: [
            {
                title: "User",
                url: route("admin.users.index"),
                icon: User,
            },
            {
                title: "Role",
                url: route("admin.roles.index"),
                icon: Bolt,
            },
            {
                title: "Hak Akses",
                url: route("admin.permissions.index"),
                icon: BookCheck,
            },
        ],
    },
    {
        title: "Manajemen Formulir",
        icon: FileText,
        items: [
            {
                title: "Form Builder",
                url: route("admin.form-builder.create"),
                icon: Wand2,
            },
            {
                title: "Formulir",
                url: route("admin.forms.index"),
                icon: FileText,
            },
            {
                title: "Tahap Formulir",
                url: route("admin.form-phases.index"),
                icon: Settings,
            },
            {
                title: "Kontrol Akses",
                url: route("admin.form-access-controls.index"),
                icon: Shield,
            },
        ],
    },
    {
        title: "Manajemen Pengajuan",
        icon: Calendar,
        items: [
            {
                title: "Periode Pengajuan",
                url: route("admin.submission-periods.index"),
                icon: Calendar,
            },
            {
                title: "Lihat Pengajuan",
                url: route("admin.submissions.index"),
                icon: Send,
            },
        ],
    },
    {
        title: "Manajemen Review",
        icon: MessageSquare,
        items: [
            {
                title: "Ikhtisar Review",
                url: route("admin.submissions.index") + "?tab=review",
                icon: MessageSquare,
            },
            {
                title: "Review Tertunda",
                url: route("admin.submissions.index") + "?status=under_review",
                icon: Clock,
            },
            {
                title: "Review Selesai",
                url: route("admin.submissions.index") + "?status=approved",
                icon: CheckCircle,
            },
        ],
    },
    {
        title: "Manajemen Reviewer",
        icon: Users,
        items: [
            {
                title: "Reviewer",
                url: route("admin.reviewers.index"),
                icon: Users,
            },
            {
                title: "Role Reviewer",
                url: route("admin.reviewer-roles.index"),
                icon: Filter,
            },
        ],
    },
    {
        title: "Manajemen Fakultas & Prodi",
        icon: Building2,
        items: [
            {
                title: "Fakultas",
                url: route("admin.faculties.index"),
                icon: Building2,
            },
            {
                title: "Program Studi",
                url: route("admin.faculties.study-programs"),
                icon: GraduationCap,
            },
        ],
    },
    {
        title: "Manajemen Statistik",
        icon: ChartPie,
        items: [
            {
                title: "Statistik Tahap Formulir",
                url: route("admin.stats.form-phase"),
                icon: FileBarChart2,
            },
            {
                title: "Statistik Pengajuan Formulir",
                url: route("admin.stats.form-submission"),
                icon: FileChartPieIcon,
            },
            {
                title: "Statistik Reviewer",
                url: route("admin.stats.reviewer"),
                icon: UserCheck2Icon,
            },
            {
                title: "Statistik User",
                url: route("admin.stats.user"),
                icon: FileUserIcon,
            },
        ],
    },
    {
        title: "Manajemen Pengumuman",
        icon: Building2,
        items: [
            {
                title: "Pengumuman",
                url: route("admin.announcements.index"),
                icon: Megaphone,
            },
        ],
    },
];

// Navigation items for regular users (termasuk reviewer)
const userNavItems = computed(() => {
    const baseItems = [
        {
            title: "Dashboard",
            url: route('user.dashboard'),
            icon: Home,
        },
        {
            title: "Form Biodata",
            icon: ClipboardList,
            items: [
                {
                    title: "Biodata",
                    url: route("user.biodata.index"),
                    icon: UserPenIcon,
                },
            ],
        },
        {
            title: "Formulir Saya",
            icon: ClipboardList,
            items: [
                {
                    title: "Pengajuan Aktif",
                    url: route('user.dashboard'),
                    icon: BookOpen,
                }
            ]
        },
        {
            title: "Pengajuan Formulir",
            icon: Send,
            items: [
                {
                    title: "Lihat Pengajuan",
                    url: route('user.submissions.index'),
                    icon: Send,
                },
                {
                    title: "Dalam Review",
                    url:
                        route("user.submissions.index") +
                        "?status=under_review",
                    icon: Clock,
                },
                {
                    title: "Disetujui",
                    url: route("user.submissions.index") + "?status=approved",
                    icon: CheckCircle,
                }
            ]
        }
    ];

    // Add Review Tasks menu if user is reviewer
    if (isReviewer.value) {
        baseItems.push({
            title: "Tugas Review",
            icon: MessageSquare,
            items: [
                {
                    title: "Tugas Review Ditugaskan",
                    url: route("reviewer.submissions.index"),
                    icon: MessageSquare,
                },
                {
                    title: "Review Pending",
                    url: route("reviewer.submissions.index") + "?status=open",
                    icon: Clock,
                },
                {
                    title: "Review Selesai",
                    url:
                        route("reviewer.submissions.index") +
                        "?status=resolved",
                    icon: CheckCircle,
                },
            ],
        });
    }

    return baseItems;
});

const navItems = computed(() => {
    if (isAdmin.value) return adminNavItems;
    return userNavItems.value;
});

const currentUrl = computed(() => page.url);

const isActive = (url: string) => {
    // Remove query parameters for comparison
    const cleanUrl = url.split("?")[0];
    const cleanCurrentUrl = currentUrl.value.split("?")[0];
    return cleanCurrentUrl === cleanUrl || cleanCurrentUrl.startsWith(cleanUrl);
};

const hasActiveChild = (items: any[]) => {
    return items.some((item) => isActive(item.url));
};

const logout = (e: Event) => {
    e.preventDefault();
    router.post(route("logout"));
};

// Helper to determine current context
const getCurrentContext = computed(() => {
    const url = currentUrl.value;
    if (url.startsWith("/admin")) return "admin";
    return "user";
});

const getContextLabel = computed(() => {
    switch (getCurrentContext.value) {
        case "admin":
            return "Administration";
        default:
            return isReviewer.value ? "User & Reviewer Portal" : "User Portal";
    }
});
</script>

<template>
  <Sidebar variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton
            size="lg"
            as-child
          >
            <a href="/">
              <div
                class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
              >
                <FileText class="size-4" />
              </div>
              <div class="grid flex-1 text-left text-sm leading-tight">
                <span class="truncate font-semibold">Form System</span>
                <span class="truncate text-xs">{{
                  getContextLabel
                }}</span>
              </div>
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <SidebarGroup
        v-for="item in navItems"
        :key="item.title"
      >
        <SidebarGroupLabel v-if="item.items">
          {{
            item.title
          }}
        </SidebarGroupLabel>

        <SidebarGroupContent>
          <SidebarMenu>
            <!-- Single nav item -->
            <SidebarMenuItem v-if="!item.items">
              <SidebarMenuButton
                as-child
                :is-active="isActive(item.url)"
              >
                <a :href="item.url">
                  <component :is="item.icon" />
                  <span>{{ item.title }}</span>
                </a>
              </SidebarMenuButton>
            </SidebarMenuItem>

            <!-- Group with subitems -->
            <template v-else>
              <SidebarMenuItem
                v-for="subItem in item.items"
                :key="subItem.title"
              >
                <SidebarMenuButton
                  as-child
                  :is-active="isActive(subItem.url)"
                >
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
              <SidebarMenuButton
                size="lg"
                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
              >
                <User2 class="size-4" />
                <div class="grid flex-1 text-left text-sm leading-tight">
                  <span class="truncate font-semibold">{{
                    user?.name
                  }}</span>
                  <span class="truncate text-xs capitalize">
                    {{
                      userRoles
                        .map((role: any) => role)
                        .join(", ")
                    }}
                    <span
                      v-if="isReviewer"
                      class="text-blue-600"
                    >• Reviewer</span>
                  </span>
                </div>
                <ChevronUp class="ml-auto size-4" />
              </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
              class="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
              side="bottom"
              align="end"
            >
              <DropdownMenuItem as-child>
                <a
                  :href="route('profile.edit')"
                  class="cursor-pointer"
                >
                  <User2 class="mr-2 size-4" />
                  Profile
                </a>
              </DropdownMenuItem>

              <!-- Switch between admin/user view if user has admin role -->
              <DropdownMenuItem
                v-if="
                  isAdmin && !currentUrl.startsWith('/admin')
                "
                as-child
              >
                <a
                  :href="route('admin.dashboard')"
                  class="cursor-pointer"
                >
                  <Shield class="mr-2 size-4" />
                  Admin Panel
                </a>
              </DropdownMenuItem>

              <DropdownMenuItem
                v-if="
                  isAdmin && currentUrl.startsWith('/admin')
                "
                as-child
              >
                <a
                  :href="route('user.dashboard')"
                  class="cursor-pointer"
                >
                  <User2 class="mr-2 size-4" />
                  User View
                </a>
              </DropdownMenuItem>

              <DropdownMenuItem as-child>
                <a
                  href="#"
                  class="cursor-pointer text-destructive flex items-center"
                  @click="logout"
                >
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
