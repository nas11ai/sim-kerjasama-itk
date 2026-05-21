<!-- resources\js\Pages\ReviewerRoles\Index.vue -->
<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/Components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    Shield,
    ToggleLeft,
    ToggleRight,
    AlertTriangle,
    Users,
    ChevronsUpDown,
    Check,
    Filter,
    X,
    CheckCircle,
    XCircle,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";
import { debounce } from "lodash";
import { cn } from "@/lib/utils";

interface ReviewerRole {
    id: number;
    name: string;
    is_active: boolean;
    reviewers_count: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    reviewerRoles: {
        data: ReviewerRole[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();
const { toast } = useToast();

const searchQuery = ref(props.filters.search || "");
const selectedStatus = ref(props.filters.status || "all");
const showDeleteDialog = ref(false);
const roleToDelete = ref<ReviewerRole | null>(null);
const openStatus = ref(false);

const statusOptions = [
    { value: "all", label: "Semua Status" },
    { value: "active", label: "Aktif" },
    { value: "inactive", label: "Tidak Aktif" },
];

const selectedStatusLabel = computed(() => {
    const status = statusOptions.find((s) => s.value === selectedStatus.value);
    return status?.label || "Pilih status...";
});

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

watch(selectedStatus, () => {
    applyFilters();
});

const applyFilters = () => {
    const params: Record<string, any> = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedStatus.value && selectedStatus.value !== "all")
        params.status = selectedStatus.value;

    router.get(route("admin.reviewer-roles.index"), params, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = "";
    selectedStatus.value = "all";

    router.get(
        route("admin.reviewer-roles.index"),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteRole = (role: ReviewerRole) => {
    roleToDelete.value = role;
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    if (roleToDelete.value) {
        router.delete(
            route("admin.reviewer-roles.destroy", roleToDelete.value.id),
            {
                onSuccess: () => {
                    showDeleteDialog.value = false;
                    roleToDelete.value = null;
                    toast({
                        title: "Sukses",
                        description: "Role reviewer berhasil dihapus!",
                    });
                },
                onError: (errors) => {
                    toast({
                        title: "Error",
                        description:
                            errors.error || "Gagal menghapus role reviewer.",
                        variant: "destructive",
                    });
                },
                onFinish: () => {
                    showDeleteDialog.value = false;
                    roleToDelete.value = null;
                },
            },
        );
    }
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    roleToDelete.value = null;
};

const toggleRoleStatus = (role: ReviewerRole) => {
    router.patch(
        route("admin.reviewer-roles.toggle-status", role.id),
        {},
        {
            onSuccess: () => {
                toast({
                    title: "Sukses",
                    description: `Role reviewer berhasil ${
                        role.is_active ? "dinonaktifkan" : "diaktifkan"
                    }!`,
                });
            },
            onError: () => {
                toast({
                    title: "Error",
                    description: "Gagal memperbarui status.",
                    variant: "destructive",
                });
            },
        },
    );
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const hasFilters = computed(() => {
    return (
        searchQuery.value ||
        (selectedStatus.value && selectedStatus.value !== "all")
    );
});

const totalRoles = computed(() => {
    return props.reviewerRoles?.total ?? props.reviewerRoles.data.length;
});

const canDeleteRole = computed(() => {
    return roleToDelete.value
        ? roleToDelete.value.reviewers_count === 0
        : false;
});

const activeFiltersCount = computed(() => {
    let count = 0;

    if (searchQuery.value) count++;
    if (selectedStatus.value && selectedStatus.value !== "all") count++;

    return count;
});

const goToPage = (url: string | null) => {
    if (url) router.visit(url);
};
</script>

<template>
    <Head title="Reviewer Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Role Reviewer ({{ totalRoles }})
                </h2>
                <Link :href="route('admin.reviewer-roles.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Tambah Role
                    </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Search class="h-5 w-5" />
                            Cari & Filter
                            <Badge
                                v-if="activeFiltersCount > 0"
                                variant="secondary"
                            >
                                {{ activeFiltersCount }} aktif
                            </Badge>
                        </CardTitle>
                        <Button
                            v-if="hasFilters"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Bersihkan Semua Filter
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search Input -->
                        <div class="flex-1">
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"
                                />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Cari reviewer roles berdasarkan nama..."
                                    class="pl-10"
                                />
                            </div>
                        </div>

                        <!-- Status Filter-->
                        <div class="w-full lg:w-48">
                            <Popover v-model:open="openStatus">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openStatus"
                                        class="w-full justify-between"
                                    >
                                        {{ selectedStatusLabel }}
                                        <ChevronsUpDown
                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                        />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[200px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Cari status..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                                        />
                                        <CommandList>
                                            <CommandEmpty
                                                >Tidak ada status
                                                ditemukan.</CommandEmpty
                                            >
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="status in statusOptions"
                                                    :key="status.value"
                                                    :value="status.value"
                                                    @select="
                                                        () => {
                                                            selectedStatus =
                                                                status.value;
                                                            openStatus = false;
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedStatus ===
                                                                    status.value
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0',
                                                            )
                                                        "
                                                    />
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <CheckCircle
                                                            v-if="
                                                                status.value ===
                                                                'active'
                                                            "
                                                            class="h-4 w-4 text-green-600"
                                                        />
                                                        <XCircle
                                                            v-else-if="
                                                                status.value ===
                                                                'inactive'
                                                            "
                                                            class="h-4 w-4 text-red-600"
                                                        />
                                                        {{ status.label }}
                                                    </div>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <div
                v-if="props.reviewerRoles.data.length === 0"
                class="text-center py-12"
            >
                <Card>
                    <CardContent class="pt-6">
                        <Shield
                            class="h-12 w-12 mx-auto text-muted-foreground mb-4"
                        />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            Tidak Ada Role Reviewer Ditemukan
                        </h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            {{
                                hasFilters
                                    ? "Tidak ada role yang cocok dengan kriteria pencarian Anda."
                                    : "Mulai dengan membuat role reviewer pertama Anda."
                            }}
                        </p>
                        <Link
                            v-if="!hasFilters"
                            :href="route('admin.reviewer-roles.create')"
                        >
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Buat Role Pertama
                            </Button>
                        </Link>
                        <Button v-else variant="outline" @click="clearFilters">
                            <X class="h-4 w-4 mr-2" />
                            Bersihkan Semua Filter
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Roles Grid -->
            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="role in props.reviewerRoles.data"
                    :key="role.id"
                    class="group hover:shadow-lg transition-shadow"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle
                                    class="text-lg flex items-center gap-2"
                                >
                                    <Shield class="h-5 w-5" />
                                    {{ role.name }}
                                </CardTitle>
                                <div class="flex items-center gap-2 mt-2">
                                    <Badge
                                        :variant="
                                            role.is_active
                                                ? 'default'
                                                : 'destructive'
                                        "
                                        class="flex items-center gap-1"
                                    >
                                        {{
                                            role.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </Badge>
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link
                                            :href="
                                                route(
                                                    'admin.reviewer-roles.show',
                                                    role.id,
                                                )
                                            "
                                        >
                                            <Eye class="h-4 w-4 mr-2" />
                                            Lihat
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link
                                            :href="
                                                route(
                                                    'admin.reviewer-roles.edit',
                                                    role.id,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4 mr-2" />
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="toggleRoleStatus(role)"
                                        class="cursor-pointer"
                                    >
                                        <ToggleRight
                                            v-if="role.is_active"
                                            class="h-4 w-4 mr-2"
                                        />
                                        <ToggleLeft
                                            v-else
                                            class="h-4 w-4 mr-2"
                                        />
                                        {{
                                            role.is_active
                                                ? "Nonaktifkan"
                                                : "Aktifkan"
                                        }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="deleteRole(role)"
                                        class="text-destructive cursor-pointer"
                                        :disabled="role.reviewers_count > 0"
                                    >
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Hapus
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <div
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <span>Reviewer Ditugaskan</span>
                                </div>
                                <Badge variant="secondary">{{
                                    role.reviewers_count
                                }}</Badge>
                            </div>

                            <div
                                class="flex items-center justify-between text-sm pt-2 border-t"
                            >
                                <span class="text-muted-foreground"
                                    >Dibuat</span
                                >
                                <span class="font-medium text-sm">{{
                                    formatDate(role.created_at)
                                }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div
                v-if="props.reviewerRoles.last_page > 1"
                class="flex justify-center mt-4"
            >
                <div class="flex items-center gap-2">
                    <template
                        v-for="link in props.reviewerRoles.links"
                        :key="link.label"
                    >
                        <Button
                            v-if="link.url"
                            variant="outline"
                            size="sm"
                            @click="goToPage(link.url)"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    link.active,
                                'hover:bg-muted': !link.active,
                            }"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            :class="[
                                'px-3 py-2 text-sm rounded-md text-muted-foreground',
                                'bg-muted cursor-not-allowed',
                            ]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        Konfirmasi Hapus Role
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            canDeleteRole
                                ? "Apakah Anda yakin ingin menghapus role reviewer ini? Tindakan ini tidak dapat dibatalkan."
                                : "Role ini tidak dapat dihapus karena memiliki reviewer yang ditugaskan."
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="roleToDelete" class="py-4">
                    <div class="p-4 bg-muted rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center"
                            >
                                <Shield class="h-6 w-6 text-primary" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg">
                                    {{ roleToDelete.name }}
                                </h4>
                                <div class="flex items-center gap-3 mt-2">
                                    <div
                                        class="flex items-center gap-1 text-sm text-muted-foreground"
                                    >
                                        <Users class="h-4 w-4" />
                                        <span
                                            >{{
                                                roleToDelete.reviewers_count
                                            }}
                                            reviewer</span
                                        >
                                    </div>
                                    <Badge
                                        :variant="
                                            roleToDelete.is_active
                                                ? 'default'
                                                : 'destructive'
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            roleToDelete.is_active
                                                ? "Aktif"
                                                : "Tidak Aktif"
                                        }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <!-- Warning message for roles with assigned reviewers -->
                        <div
                            v-if="!canDeleteRole"
                            class="mt-4 p-3 bg-destructive/10 border border-destructive/20 rounded-md"
                        >
                            <div class="flex items-start gap-2">
                                <AlertTriangle
                                    class="h-5 w-5 text-destructive shrink-0 mt-0.5"
                                />
                                <div class="text-sm">
                                    <p
                                        class="font-medium text-destructive mb-1"
                                    >
                                        Tidak Dapat Menghapus Role
                                    </p>
                                    <p class="text-muted-foreground">
                                        Role ini memiliki
                                        {{ roleToDelete.reviewers_count }}
                                        reviewer yang ditugaskan. Silakan
                                        tetapkan ulang atau hapus semua reviewer
                                        sebelum menghapus role ini.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete">
                        Batal
                    </Button>
                    <Button
                        v-if="canDeleteRole"
                        variant="destructive"
                        @click="confirmDelete"
                    >
                        Hapus Role
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        disabled
                        class="opacity-50"
                    >
                        Tidak Dapat Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
