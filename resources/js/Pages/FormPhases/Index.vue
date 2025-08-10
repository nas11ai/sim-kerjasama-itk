<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Plus,
    Search,
    Eye,
    Edit,
    Trash2,
    MoreHorizontal,
    Users,
    FileText,
    Settings,
} from "lucide-vue-next";

interface PhaseType {
    id: number;
    name: string;
}

interface Role {
    id: number;
    name: string;
}

interface Faculty {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty: Faculty;
}

interface Form {
    id: number;
    title: string;
}

interface FormAccessControl {
    id: number;
    form: Form;
    role: Role;
    study_program: StudyProgram;
}

interface FormPhaseDetail {
    id: number;
    order: number;
    phase_type: PhaseType;
    form_access_control: FormAccessControl;
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    form_phase_details: FormPhaseDetail[];
}

interface PaginatedData {
    data: FormPhase[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    formPhases: PaginatedData;
}

const props = defineProps<Props>();

const searchQuery = ref("");

const filteredFormPhases = computed(() => {
    if (!searchQuery.value) return props.formPhases.data;

    return props.formPhases.data.filter(
        (phase) =>
            phase.title
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            phase.description
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase())
    );
});

const deleteFormPhase = (id: number) => {
    if (confirm("Are you sure you want to delete this form phase?")) {
        router.delete(route("form-phases.destroy", id));
    }
};

const toggleStatus = (formPhase: FormPhase) => {
    router.patch(
        route("form-phases.update-status", formPhase.id),
        {
            is_active: !formPhase.is_active,
        },
        {
            onSuccess: (page) => {
                // Optional: Handle success response
                console.log("Status updated successfully");
            },
            onError: (errors) => {
                // Optional: Handle error response
                console.error("Failed to update status:", errors);
                alert("Failed to update status");
            },
        }
    );
};
</script>

<template>
    <Head title="Form Phases Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Form Phases Management
                </h2>
                <Link :href="route('form-phases.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create Form Phase
                    </Button>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Search and Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Search class="h-5 w-5" />
                        Search & Filter
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <Input
                                v-model="searchQuery"
                                placeholder="Search form phases by title or description..."
                                class="max-w-md"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Phases Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Form Phases List
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Phase Details</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead class="text-right"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="formPhase in filteredFormPhases"
                                    :key="formPhase.id"
                                >
                                    <TableCell class="font-medium">
                                        <div class="flex items-center gap-2">
                                            <FileText
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                            {{ formPhase.title }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="max-w-xs truncate">
                                            {{ formPhase.description || "-" }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                formPhase.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                        >
                                            {{
                                                formPhase.is_active
                                                    ? "Active"
                                                    : "Inactive"
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Users
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                            <span class="text-sm">
                                                {{
                                                    formPhase.form_phase_details
                                                        .length
                                                }}
                                                phases
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                new Date(
                                                    formPhase.created_at
                                                ).toLocaleDateString()
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <MoreHorizontal
                                                        class="h-4 w-4"
                                                    />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <Link
                                                    :href="
                                                        route(
                                                            'form-phases.show',
                                                            formPhase.id
                                                        )
                                                    "
                                                >
                                                    <DropdownMenuItem>
                                                        <Eye
                                                            class="h-4 w-4 mr-2"
                                                        />
                                                        View Details
                                                    </DropdownMenuItem>
                                                </Link>
                                                <Link
                                                    :href="
                                                        route(
                                                            'form-phases.edit',
                                                            formPhase.id
                                                        )
                                                    "
                                                >
                                                    <DropdownMenuItem>
                                                        <Edit
                                                            class="h-4 w-4 mr-2"
                                                        />
                                                        Edit
                                                    </DropdownMenuItem>
                                                </Link>
                                                <DropdownMenuItem
                                                    @click="
                                                        toggleStatus(formPhase)
                                                    "
                                                    class="cursor-pointer"
                                                >
                                                    <Settings
                                                        class="h-4 w-4 mr-2"
                                                    />
                                                    {{
                                                        formPhase.is_active
                                                            ? "Deactivate"
                                                            : "Activate"
                                                    }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        deleteFormPhase(
                                                            formPhase.id
                                                        )
                                                    "
                                                    class="text-destructive cursor-pointer"
                                                >
                                                    <Trash2
                                                        class="h-4 w-4 mr-2"
                                                    />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="filteredFormPhases.length === 0"
                        class="text-center py-12"
                    >
                        <Settings
                            class="h-12 w-12 mx-auto text-muted-foreground mb-4"
                        />
                        <h3 class="text-lg font-medium mb-2">
                            No form phases found
                        </h3>
                        <p class="text-muted-foreground mb-4">
                            {{
                                searchQuery
                                    ? "Try adjusting your search criteria."
                                    : "Get started by creating your first form phase."
                            }}
                        </p>
                        <Link
                            :href="route('form-phases.create')"
                            v-if="!searchQuery"
                        >
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Create Form Phase
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div
                v-if="props.formPhases.last_page > 1"
                class="flex justify-center"
            >
                <div class="flex items-center gap-2">
                    <template
                        v-for="link in props.formPhases.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'px-3 py-2 text-sm rounded-md',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-background border hover:bg-muted',
                            ]"
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
    </AuthenticatedLayout>
</template>
