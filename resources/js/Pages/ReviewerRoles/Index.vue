<!-- resources/js/Pages/Admin/ReviewerRoles/Index.vue -->
<script setup lang="ts">
import { ref, computed } from "vue";
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
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
    Users
} from "lucide-vue-next";

interface ReviewerRole {
    id: number;
    name: string;
    is_active: boolean;
    reviewers_count: number;
    created_at: string;
}

interface Props {
    reviewerRoles: {
        data: ReviewerRole[];
        links: any[];
        meta: any;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || "");
const selectedStatus = ref(props.filters.status || "all");
const showDeleteDialog = ref(false);
const roleToDelete = ref<ReviewerRole | null>(null);

const applyFilters = () => {
    const params: Record<string, any> = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedStatus.value && selectedStatus.value !== "all") params.status = selectedStatus.value;

    router.get(route("admin.reviewer-roles.index"), params, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = "";
    selectedStatus.value = "all";

    router.get(route("admin.reviewer-roles.index"), {}, {
        preserveState: true,
        replace: true,
    });
};

const deleteRole = (role: ReviewerRole) => {
    roleToDelete.value = role;
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    if (roleToDelete.value) {
        router.delete(route("admin.reviewer-roles.destroy", roleToDelete.value.id), {
            onFinish: () => {
                showDeleteDialog.value = false;
                roleToDelete.value = null;
            }
        });
    }
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    roleToDelete.value = null;
};

const toggleRoleStatus = (role: ReviewerRole) => {
    router.patch(route("admin.reviewer-roles.toggle-status", role.id));
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const hasFilters = computed(() => {
    return searchQuery.value || (selectedStatus.value && selectedStatus.value !== "all");
});

const totalRoles = computed(() => {
    return props.reviewerRoles?.meta?.total || props.reviewerRoles?.data?.length || 0;
});

const canDeleteRole = computed(() => {
    return roleToDelete.value ? roleToDelete.value.reviewers_count === 0 : false;
});

const linkClass = (link: any) => [
    'px-3 py-2 text-sm font-medium rounded-md',
    link.active
        ? 'bg-primary text-primary-foreground'
        : 'text-muted-foreground hover:bg-muted',
    !link.url && 'opacity-50 cursor-not-allowed'
];
</script>

<template>

    <Head title="Reviewer Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Reviewer Roles ({{ totalRoles }})
                </h2>
                <Link :href="route('admin.reviewer-roles.create')">
                <Button>
                    <Plus class="h-4 w-4 mr-2" />
                    Add Role
                </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Search class="h-5 w-5" />
                        Search & Filter
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <Input v-model="searchQuery" placeholder="Search roles..." @keyup.enter="applyFilters" />
                        </div>
                        <div>
                            <Select v-model="selectedStatus">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex gap-2">
                            <Button @click="applyFilters" class="flex-1">
                                <Search class="h-4 w-4 mr-2" />
                                Search
                            </Button>
                            <Button v-if="hasFilters" @click="clearFilters" variant="outline">
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Roles Grid -->
            <div v-if="props.reviewerRoles.data.length === 0" class="text-center py-12">
                <Card>
                    <CardContent class="pt-6">
                        <Shield class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            No Reviewer Roles Found
                        </h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            {{ hasFilters ? 'No roles match your search criteria.'
                                : 'Get started by creating your first reviewer role.' }}
                        </p>
                        <Link v-if="!hasFilters" :href="route('admin.reviewer-roles.create')">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Create First Role
                        </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="role in props.reviewerRoles.data" :key="role.id" class="hover:shadow-lg transition-shadow">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg flex items-center gap-2">
                                    <Shield class="h-5 w-5" />
                                    {{ role.name }}
                                </CardTitle>
                                <div class="flex items-center gap-2 mt-2">
                                    <Badge :variant="role.is_active ? 'default' : 'destructive'">
                                        {{ role.is_active ? 'Active' : 'Inactive' }}
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
                                        <Link :href="route('admin.reviewer-roles.show', role.id)">
                                        <Eye class="h-4 w-4 mr-2" />
                                        View
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('admin.reviewer-roles.edit', role.id)">
                                        <Edit class="h-4 w-4 mr-2" />
                                        Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="toggleRoleStatus(role)">
                                        <ToggleRight v-if="role.is_active" class="h-4 w-4 mr-2" />
                                        <ToggleLeft v-else class="h-4 w-4 mr-2" />
                                        {{ role.is_active ? 'Deactivate' : 'Activate' }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="deleteRole(role)" class="text-destructive"
                                        :disabled="role.reviewers_count > 0">
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Assigned Reviewers</span>
                                <span class="font-medium">{{ role.reviewers_count }}</span>
                            </div>

                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Created</span>
                                <span class="font-medium">{{ formatDate(role.created_at) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="props.reviewerRoles.links && props.reviewerRoles.links.length > 3" class="flex justify-center">
                <nav class="flex items-center space-x-1">
                    <Link v-for="link in props.reviewerRoles.links" :key="link.label" :href="link.url"
                        :class="linkClass(link)" v-html="link.label" />
                </nav>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        Confirm Delete Role
                    </DialogTitle>
                    <DialogDescription>
                        {{ canDeleteRole
                            ? 'Are you sure you want to delete this reviewer role? This action cannot be undone.'
                            : 'This role cannot be deleted because it has assigned reviewers.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="roleToDelete" class="py-4">
                    <div class="p-4 bg-muted rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                                <Shield class="h-6 w-6 text-primary" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg">{{ roleToDelete.name }}</h4>
                                <div class="flex items-center gap-3 mt-2">
                                    <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Users class="h-4 w-4" />
                                        <span>{{ roleToDelete.reviewers_count }} reviewers</span>
                                    </div>
                                    <Badge :variant="roleToDelete.is_active ? 'default' : 'destructive'"
                                        class="text-xs">
                                        {{ roleToDelete.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <!-- Warning message for roles with assigned reviewers -->
                        <div v-if="!canDeleteRole"
                            class="mt-4 p-3 bg-destructive/10 border border-destructive/20 rounded-md">
                            <div class="flex items-start gap-2">
                                <AlertTriangle class="h-5 w-5 text-destructive flex-shrink-0 mt-0.5" />
                                <div class="text-sm">
                                    <p class="font-medium text-destructive mb-1">Cannot Delete Role</p>
                                    <p class="text-muted-foreground">
                                        This role has {{ roleToDelete.reviewers_count }} assigned reviewer(s).
                                        Please reassign or remove all reviewers before deleting this role.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete">
                        Cancel
                    </Button>
                    <Button v-if="canDeleteRole" variant="destructive" @click="confirmDelete">
                        Delete Role
                    </Button>
                    <Button v-else variant="outline" disabled class="opacity-50">
                        Cannot Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
