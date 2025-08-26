<!-- resources/js/Pages/Admin/Reviewers/Index.vue -->
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
    UserCheck,
    UserX,
    Filter,
    AlertTriangle
} from "lucide-vue-next";

interface ReviewerRole {
    id: number;
    name: string;
}

interface Reviewer {
    id: number;
    start_date: string;
    end_date: string | null;
    is_active: boolean;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
    reviewer_role: {
        id: number;
        name: string;
    };
}

interface Props {
    reviewers: {
        data: Reviewer[];
        links: any[];
        meta: any;
    };
    reviewerRoles: ReviewerRole[];
    filters: {
        search?: string;
        role?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || "");
const selectedRole = ref(props.filters.role || "");
const selectedStatus = ref(props.filters.status || "");
const showDeleteDialog = ref(false);
const reviewerToDelete = ref<Reviewer | null>(null);

const applyFilters = () => {
    const params: Record<string, any> = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedRole.value) params.role = selectedRole.value;
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get(route("admin.reviewers.index"), params, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = "";
    selectedRole.value = "";
    selectedStatus.value = "";

    router.get(route("admin.reviewers.index"), {}, {
        preserveState: true,
        replace: true,
    });
};

const deleteReviewer = (reviewer: Reviewer) => {
    reviewerToDelete.value = reviewer;
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    if (reviewerToDelete.value) {
        router.delete(route("admin.reviewers.destroy", reviewerToDelete.value.id), {
            onFinish: () => {
                showDeleteDialog.value = false;
                reviewerToDelete.value = null;
            }
        });
    }
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    reviewerToDelete.value = null;
};

const toggleReviewerStatus = (reviewer: Reviewer) => {
    const action = reviewer.is_active ? 'deactivate' : 'activate';
    const routeName = `admin.reviewers.${action}`;

    router.patch(route(routeName, reviewer.id));
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const hasFilters = computed(() => {
    return searchQuery.value || selectedRole.value || selectedStatus.value;
});
</script>

<template>

    <Head title="Manage Reviewers" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Reviewer Management
                </h2>
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.reviewer-roles.index')">
                    <Button variant="outline">
                        <Filter class="h-4 w-4 mr-2" />
                        Reviewer Roles
                    </Button>
                    </Link>
                    <Link :href="route('admin.reviewers.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Add Reviewer
                    </Button>
                    </Link>
                </div>
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
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <Input v-model="searchQuery" placeholder="Search reviewers..."
                                @keyup.enter="applyFilters" />
                        </div>
                        <div>
                            <Select v-model="selectedRole">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Roles" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All Roles</SelectItem>
                                    <SelectItem v-for="role in reviewerRoles" :key="role.id"
                                        :value="role.id.toString()">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Select v-model="selectedStatus">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All Status</SelectItem>
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

            <!-- Reviewers Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Reviewers ({{ props.reviewers.meta.total }})</CardTitle>
                    <CardDescription>
                        Manage reviewer assignments and permissions
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.reviewers.data.length === 0" class="text-center py-8">
                        <div class="mx-auto max-w-md">
                            <UserCheck class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                No Reviewers Found
                            </h3>
                            <p class="text-sm text-muted-foreground mb-4">
                                {{ hasFilters ? 'No reviewers match your search criteria.'
                                    : 'Get started by adding your first reviewer.' }}
                            </p>
                            <Link v-if="!hasFilters" :href="route('admin.reviewers.create')">
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Add First Reviewer
                            </Button>
                            </Link>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="reviewer in props.reviewers.data" :key="reviewer.id"
                            class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                        <UserCheck class="h-5 w-5 text-primary" />
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">
                                        {{ reviewer.user.name }}
                                    </h4>
                                    <p class="text-sm text-muted-foreground">
                                        {{ reviewer.user.email }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Badge variant="secondary">
                                            {{ reviewer.reviewer_role.name }}
                                        </Badge>
                                        <Badge :variant="reviewer.is_active ? 'default' : 'destructive'">
                                            {{ reviewer.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <div class="text-right text-sm text-muted-foreground">
                                    <div>Start: {{ formatDate(reviewer.start_date) }}</div>
                                    <div v-if="reviewer.end_date">
                                        End: {{ formatDate(reviewer.end_date) }}
                                    </div>
                                    <div v-else class="text-green-600 font-medium">
                                        No End Date
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
                                            <Link :href="route('admin.reviewers.show', reviewer.id)">
                                            <Eye class="h-4 w-4 mr-2" />
                                            View Details
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('admin.reviewers.edit', reviewer.id)">
                                            <Edit class="h-4 w-4 mr-2" />
                                            Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="toggleReviewerStatus(reviewer)">
                                            <UserX v-if="reviewer.is_active" class="h-4 w-4 mr-2" />
                                            <UserCheck v-else class="h-4 w-4 mr-2" />
                                            {{ reviewer.is_active ? 'Deactivate' : 'Activate' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="deleteReviewer(reviewer)" class="text-destructive">
                                            <Trash2 class="h-4 w-4 mr-2" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.reviewers.links.length > 3" class="flex justify-center">
                <nav class="flex items-center space-x-1">
                    <Link v-for="link in props.reviewers.links" :key="link.label" :href="link.url" :class="[
                        'px-3 py-2 text-sm font-medium rounded-md',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted',
                        !link.url && 'opacity-50 cursor-not-allowed',
                    ]" v-html="link.label" />
                </nav>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        Confirm Delete
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this reviewer? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="reviewerToDelete" class="py-4">
                    <div class="p-4 bg-muted rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                <UserCheck class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h4 class="font-medium">{{ reviewerToDelete.user.name }}</h4>
                                <p class="text-sm text-muted-foreground">{{ reviewerToDelete.user.email }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <Badge variant="secondary" class="text-xs">
                                        {{ reviewerToDelete.reviewer_role.name }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="confirmDelete">
                        Delete Reviewer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
