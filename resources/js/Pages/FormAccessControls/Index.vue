<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Checkbox } from "@/Components/ui/checkbox";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
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
    Filter,
    FileText,
    Users,
    Building,
    X,
    Download,
} from "lucide-vue-next";

interface Role {
    id: number;
    name: string;
}

interface Faculty {
    id: number;
    name: string;
    study_programs: StudyProgram[];
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
    created_at: string;
    updated_at: string;
}

interface PaginatedData {
    data: FormAccessControl[];
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

interface Filters {
    form_id?: string;
    role_id?: string;
    faculty_id?: string;
    study_program_id?: string;
    search?: string;
    [key: string]: string | undefined; // Add index signature for Inertia compatibility
}

interface Props {
    formAccessControls: PaginatedData;
    forms: Form[];
    roles: Role[];
    faculties: Faculty[];
    filters: Filters;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || "");
const selectedFormId = ref(props.filters.form_id || "all");
const selectedRoleId = ref(props.filters.role_id || "all");
const selectedFacultyId = ref(props.filters.faculty_id || "all");
const selectedStudyProgramId = ref(props.filters.study_program_id || "all");
const selectedItems = ref<number[]>([]);
const selectAll = ref(false);

const studyPrograms = computed(() => {
    if (!selectedFacultyId.value || selectedFacultyId.value === "all")
        return [];
    const faculty = props.faculties.find(
        (f) => f.id.toString() === selectedFacultyId.value
    );
    return faculty?.study_programs || [];
});

// Watch for faculty change to reset study program selection
watch(selectedFacultyId, () => {
    selectedStudyProgramId.value = "all";
});

// Watch for select all checkbox
watch(selectAll, (newValue) => {
    if (newValue) {
        selectedItems.value = props.formAccessControls.data.map(
            (item) => item.id
        );
    } else {
        selectedItems.value = [];
    }
});

const hasActiveFilters = computed(() => {
    return (
        (selectedFormId.value && selectedFormId.value !== "all") ||
        (selectedRoleId.value && selectedRoleId.value !== "all") ||
        (selectedFacultyId.value && selectedFacultyId.value !== "all") ||
        (selectedStudyProgramId.value &&
            selectedStudyProgramId.value !== "all") ||
        searchQuery.value
    );
});

const applyFilters = () => {
    const params: Filters = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedFormId.value && selectedFormId.value !== "all")
        params.form_id = selectedFormId.value;
    if (selectedRoleId.value && selectedRoleId.value !== "all")
        params.role_id = selectedRoleId.value;
    if (selectedFacultyId.value && selectedFacultyId.value !== "all")
        params.faculty_id = selectedFacultyId.value;
    if (selectedStudyProgramId.value && selectedStudyProgramId.value !== "all")
        params.study_program_id = selectedStudyProgramId.value;

    router.get(route("admin.form-access-controls.index"), params, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = "";
    selectedFormId.value = "all";
    selectedRoleId.value = "all";
    selectedFacultyId.value = "all";
    selectedStudyProgramId.value = "all";

    router.get(
        route("admin.form-access-controls.index"),
        {},
        {
            preserveState: true,
            replace: true,
        }
    );
};

const deleteFormAccessControl = (id: number) => {
    if (confirm("Are you sure you want to delete this form access control?")) {
        router.delete(route("admin.form-access-controls.destroy", id));
    }
};

const bulkDelete = () => {
    if (selectedItems.value.length === 0) return;

    if (
        confirm(
            `Are you sure you want to delete ${selectedItems.value.length} selected items?`
        )
    ) {
        router.post(route("admin.form-access-controls.bulk-delete"), {
            ids: selectedItems.value,
        });
    }
};

const toggleItemSelection = (id: number, checked?: boolean | 'indeterminate') => {
    const isSelected = selectedItems.value.includes(id)

    const shouldSelect =
        checked === 'indeterminate'
            ? true
            : typeof checked === 'boolean'
                ? checked
                : !isSelected

    if (shouldSelect) {
        if (!isSelected) selectedItems.value.push(id)
    } else {
        selectedItems.value = selectedItems.value.filter(itemId => itemId !== id)
    }
}
</script>

<template>

    <Head title="Form Access Controls" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Form Access Controls
                </h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.form-access-controls.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create Access Control
                    </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Search and Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Search & Filter
                        <Badge v-if="hasActiveFilters" variant="secondary" class="ml-2">
                            Filters Active
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Search Bar -->
                    <div class="flex items-center gap-2">
                        <div class="flex-1 relative">
                            <Search
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input v-model="searchQuery"
                                placeholder="Search by form title, role name, or study program..." class="pl-10"
                                @keyup.enter="applyFilters" />
                        </div>
                        <Button @click="applyFilters">
                            <Search class="h-4 w-4 mr-2" />
                            Search
                        </Button>
                    </div>

                    <!-- Filter Controls -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <!-- Form Filter -->
                        <div>
                            <Select v-model="selectedFormId">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Forms" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Forms</SelectItem>
                                    <SelectItem v-for="form in props.forms" :key="form.id" :value="form.id.toString()">
                                        {{ form.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Role Filter -->
                        <div>
                            <Select v-model="selectedRoleId">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Roles" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Roles</SelectItem>
                                    <SelectItem v-for="role in props.roles" :key="role.id" :value="role.id.toString()">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Faculty Filter -->
                        <div>
                            <Select v-model="selectedFacultyId">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Faculties" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Faculties</SelectItem>
                                    <SelectItem v-for="faculty in props.faculties" :key="faculty.id"
                                        :value="faculty.id.toString()">
                                        {{ faculty.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Study Program Filter -->
                        <div>
                            <Select v-model="selectedStudyProgramId" :disabled="!selectedFacultyId ||
                                selectedFacultyId === 'all'
                                ">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Study Programs" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Study Programs</SelectItem>
                                    <SelectItem v-for="studyProgram in studyPrograms" :key="studyProgram.id"
                                        :value="studyProgram.id.toString()">
                                        {{ studyProgram.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex items-center gap-2">
                        <Button @click="applyFilters" size="sm">
                            Apply Filters
                        </Button>
                        <Button v-if="hasActiveFilters" @click="clearFilters" variant="outline" size="sm">
                            <X class="h-4 w-4 mr-2" />
                            Clear Filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Access Controls Table -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Access Controls ({{ props.formAccessControls.total }})
                    </CardTitle>
                    <Button v-if="selectedItems.length > 0" variant="destructive" size="sm" @click="bulkDelete">
                        <Trash2 class="h-4 w-4 mr-2" />
                        Delete Selected ({{ selectedItems.length }})
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">
                                        <Checkbox v-model="selectAll" :indeterminate="selectedItems.length > 0 &&
                                            selectedItems.length <
                                            props.formAccessControls
                                                .data.length
                                            " />
                                    </TableHead>
                                    <TableHead>Form</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Study Program</TableHead>
                                    <TableHead>Faculty</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="control in props.formAccessControls.data" :key="control.id">
                                    <TableCell>
                                        <Checkbox
                                            :model-value="selectedItems.includes(control.id)"
                                            @update:modelValue="(val) => toggleItemSelection(control.id, val)"
                                        />
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        <div class="flex items-center gap-2">
                                            <FileText class="h-4 w-4 text-muted-foreground" />
                                            {{ control.form.title }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {{ control.role.name }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Building class="h-4 w-4 text-muted-foreground" />
                                            {{ control.study_program.name }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm text-muted-foreground">
                                            {{
                                                control.study_program.faculty
                                                    .name
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm text-muted-foreground">
                                            {{
                                                new Date(
                                                    control.created_at
                                                ).toLocaleDateString()
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <Link :href="route(
                                                    'admin.form-access-controls.show',
                                                    control.id
                                                )
                                                    ">
                                                <DropdownMenuItem>
                                                    <Eye class="h-4 w-4 mr-2" />
                                                    View Details
                                                </DropdownMenuItem>
                                                </Link>
                                                <Link :href="route(
                                                    'admin.form-access-controls.edit',
                                                    control.id
                                                )
                                                    ">
                                                <DropdownMenuItem>
                                                    <Edit class="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                                </Link>
                                                <DropdownMenuItem @click="
                                                    deleteFormAccessControl(
                                                        control.id
                                                    )
                                                    " class="text-destructive cursor-pointer">
                                                    <Trash2 class="h-4 w-4 mr-2" />
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
                    <div v-if="props.formAccessControls.data.length === 0" class="text-center py-12">
                        <Users class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">
                            No access controls found
                        </h3>
                        <p class="text-muted-foreground mb-4">
                            {{
                                hasActiveFilters
                                    ? "Try adjusting your search criteria."
                                    : "Get started by creating your first access control."
                            }}
                        </p>
                        <Link :href="route('admin.form-access-controls.create')" v-if="!hasActiveFilters">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Create Access Control
                        </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.formAccessControls.last_page > 1" class="flex justify-center">
                <div class="flex items-center gap-2">
                    <template v-for="link in props.formAccessControls.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" :class="[
                            'px-3 py-2 text-sm rounded-md',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background border hover:bg-muted',
                        ]" v-html="link.label" />
                        <span v-else :class="[
                            'px-3 py-2 text-sm rounded-md text-muted-foreground',
                            'bg-muted cursor-not-allowed',
                        ]" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
