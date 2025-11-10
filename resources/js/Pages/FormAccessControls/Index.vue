<!-- resources\js\Pages\FormAccessControls\Index.vue -->
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
    X,
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    Check,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";
import { debounce } from "lodash";
import { cn } from "@/lib/utils";

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
    form?: Form;
    role: Role;
    study_program: StudyProgram;
    created_at: string;
    updated_at: string;
}

interface GroupAccessControl {
    form_id: number;
    jumlah_access_controls: number;
    form: Form;
    controls: FormAccessControl[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedData {
    data: GroupAccessControl[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

interface Filters {
    form_id?: string;
    role_id?: string;
    faculty_id?: string;
    study_program_id?: string;
    search?: string;
}

interface Props {
    groupAccessControls: PaginatedData;
    forms: Form[];
    roles: Role[];
    faculties: Faculty[];
    filters: Filters;
}

const props = defineProps<Props>();
const { toast } = useToast();

const searchQuery = ref(props.filters.search || "");
const selectedFormId = ref(props.filters.form_id || "all");
const selectedRoleId = ref(props.filters.role_id || "all");
const selectedFacultyId = ref(props.filters.faculty_id || "all");
const selectedStudyProgramId = ref(props.filters.study_program_id || "all");

const openForm = ref(false);
const openRole = ref(false);
const openFaculty = ref(false);
const openStudyProgram = ref(false);

const selectedItems = ref<number[]>([]);
const selectAll = ref(false);
const openGroups = ref<number[]>([]);

const selectedFormLabel = computed(() => {
    if (selectedFormId.value === "all") return "All Forms";
    const form = props.forms.find((f) => f.id.toString() === selectedFormId.value);
    return form?.title || "Select form...";
});

const selectedRoleLabel = computed(() => {
    if (selectedRoleId.value === "all") return "All Roles";
    const role = props.roles.find((r) => r.id.toString() === selectedRoleId.value);
    return role?.name || "Select role...";
});

const selectedFacultyLabel = computed(() => {
    if (selectedFacultyId.value === "all") return "All Faculties";
    const faculty = props.faculties.find(
        (f) => f.id.toString() === selectedFacultyId.value
    );
    return faculty?.name || "Select faculty...";
});

const selectedStudyProgramLabel = computed(() => {
    if (selectedStudyProgramId.value === "all") return "All Study Programs";
    const studyProgram = studyPrograms.value.find(
        (sp) => sp.id.toString() === selectedStudyProgramId.value
    );
    return studyProgram?.name || "Select study program...";
});

const studyPrograms = computed(() => {
    if (!selectedFacultyId.value || selectedFacultyId.value === "all") return [];
    const faculty = props.faculties.find(
        (f) => f.id.toString() === selectedFacultyId.value
    );
    return faculty?.study_programs || [];
});

// Watch for faculty change to reset study program selection
watch(selectedFacultyId, () => {
    selectedStudyProgramId.value = "all";
});

const hasActiveFilters = computed(() => {
    return (
        selectedFormId.value !== "all" ||
        selectedRoleId.value !== "all" ||
        selectedFacultyId.value !== "all" ||
        selectedStudyProgramId.value !== "all" ||
        searchQuery.value !== ""
    );
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (searchQuery.value) count++;
    if (selectedFormId.value !== "all") count++;
    if (selectedRoleId.value !== "all") count++;
    if (selectedFacultyId.value !== "all") count++;
    if (selectedStudyProgramId.value !== "all") count++;
    return count;
});

// Debounced search
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

// Watch for filter changes
watch(
    [selectedFormId, selectedRoleId, selectedFacultyId, selectedStudyProgramId],
    () => {
        applyFilters();
    }
);

const applyFilters = () => {
    const params: Filters = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedFormId.value !== "all") params.form_id = selectedFormId.value;
    if (selectedRoleId.value !== "all") params.role_id = selectedRoleId.value;
    if (selectedFacultyId.value !== "all") params.faculty_id = selectedFacultyId.value;
    if (selectedStudyProgramId.value !== "all")
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

const toggleGroup = (formId: number) => {
    const index = openGroups.value.indexOf(formId);
    if (index > -1) {
        openGroups.value.splice(index, 1);
    } else {
        openGroups.value.push(formId);
    }
};

const isGroupOpen = (formId: number) => {
    return openGroups.value.includes(formId);
};

const toggleAllGroups = () => {
    if (openGroups.value.length === props.groupAccessControls.data.length) {
        openGroups.value = [];
    } else {
        openGroups.value = props.groupAccessControls.data.map((group) => group.form_id);
    }
};

const toggleGroupSelection = (controls: FormAccessControl[]) => {
    const controlIds = controls.map((c) => c.id);
    const allSelected = controlIds.every((id) => selectedItems.value.includes(id));

    if (allSelected) {
        selectedItems.value = selectedItems.value.filter(
            (id) => !controlIds.includes(id)
        );
    } else {
        const newIds = controlIds.filter((id) => !selectedItems.value.includes(id));
        selectedItems.value.push(...newIds);
    }
};

const toggleItemSelection = (id: number) => {
    const index = selectedItems.value.indexOf(id);
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(id);
    }
};

const isGroupSelected = (controls: FormAccessControl[]) => {
    const controlIds = controls.map((c) => c.id);
    return controlIds.every((id) => selectedItems.value.includes(id));
};

const isGroupPartiallySelected = (controls: FormAccessControl[]) => {
    const controlIds = controls.map((c) => c.id);
    const selectedCount = controlIds.filter((id) =>
        selectedItems.value.includes(id)
    ).length;
    return selectedCount > 0 && selectedCount < controlIds.length;
};

const deleteFormAccessControl = (id: number) => {
    if (confirm("Are you sure you want to delete this form access control?")) {
        router.delete(route("admin.form-access-controls.destroy", id), {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form access control deleted successfully.",
                });
            },
            onError: () => {
                toast({
                    title: "Error",
                    description: "Failed to delete form access control.",
                    variant: "destructive",
                });
            },
        });
    }
};

const bulkDelete = () => {
    if (selectedItems.value.length === 0) return;

    if (
        confirm(
            `Are you sure you want to delete ${selectedItems.value.length} selected items?`
        )
    ) {
        router.post(
            route("admin.form-access-controls.bulk-delete"),
            { ids: selectedItems.value },
            {
                onSuccess: () => {
                    selectedItems.value = [];
                    toast({
                        title: "Success",
                        description: "Selected form access controls deleted successfully.",
                    });
                },
                onError: () => {
                    toast({
                        title: "Error",
                        description: "Failed to delete selected form access controls.",
                        variant: "destructive",
                    });
                },
            }
        );
    }
};

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
        });
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};
</script>

<template>
    <Head title="Form Access Controls" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Form Access Controls
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="selectedItems.length > 0"
                        variant="destructive"
                        size="sm"
                        @click="bulkDelete"
                    >
                        <Trash2 class="h-4 w-4 mr-2" />
                        Delete Selected ({{ selectedItems.length }})
                    </Button>
                    <Link :href="route('admin.form-access-controls.create')">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Create Access Control
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Search and Filters -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Search & Filter
                            <Badge v-if="activeFiltersCount > 0" variant="secondary">
                                {{ activeFiltersCount }} active
                            </Badge>
                        </CardTitle>
                        <Button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            variant="ghost"
                            size="sm"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Clear All
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Search Bar -->
                    <div class="flex items-center gap-2">
                        <div class="flex-1 relative">
                            <Search
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by form title, role name, or study program..."
                                class="pl-10"
                            />
                        </div>
                    </div>

                    <!-- filter -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <!-- form filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Form
                            </label>
                            <Popover v-model:open="openForm">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openForm"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedFormLabel }}</span>
                                        <ChevronsUpDown
                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                        />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[300px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Search form..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-none"
                                        />
                                        <CommandList>
                                            <CommandEmpty>No form found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="() => {
                                                            selectedFormId = 'all';
                                                            openForm = false;
                                                        }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedFormId === 'all'
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    All Forms
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="form in props.forms"
                                                    :key="form.id"
                                                    :value="form.id.toString()"
                                                    @select="() => {
                                                            selectedFormId = form.id.toString();
                                                            openForm = false;
                                                        }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedFormId === form.id.toString()
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    {{ form.title }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- role filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Role
                            </label>
                            <Popover v-model:open="openRole">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openRole"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedRoleLabel }}</span>
                                        <ChevronsUpDown
                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                        />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[200px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Search role..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-none"
                                        />
                                        <CommandList>
                                            <CommandEmpty>No role found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="() => {
                                                            selectedRoleId = 'all';
                                                            openRole = false;
                                                        }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedRoleId === 'all'
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    All Roles
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="role in props.roles"
                                                    :key="role.id"
                                                    :value="role.id.toString()"
                                                    @select="() => {
                                                            selectedRoleId = role.id.toString();
                                                            openRole = false;
                                                        }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedRoleId === role.id.toString()
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    {{ role.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- fak filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Faculty
                            </label>
                            <Popover v-model:open="openFaculty">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openFaculty"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedFacultyLabel }}</span>
                                        <ChevronsUpDown
                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                        />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[250px] p-0">
                                    <Command>
                                        <CommandInput
                                        placeholder="Search faculty..." 
                                        class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-none"
                                        />
                                        <CommandList>
                                            <CommandEmpty>No faculty found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="() => {
                                                            selectedFacultyId = 'all';
                                                            openFaculty = false;
                                                        }">
                                                    <Check
                                                        :class=" cn('mr-2 h-4 w-4',
                                                            selectedFacultyId === 'all'
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    All Faculties
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="faculty in props.faculties"
                                                    :key="faculty.id"
                                                    :value="faculty.id.toString()"
                                                    @select="() => {
                                                            selectedFacultyId =
                                                                faculty.id.toString();
                                                            openFaculty = false;
                                                        }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedFacultyId ===
                                                                faculty.id.toString()
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    {{ faculty.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- prodi filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Study Program
                            </label>
                            <Popover
                                v-model:open="openStudyProgram"
                                :disabled="
                                    !selectedFacultyId || selectedFacultyId === 'all'
                                "
                            >
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openStudyProgram"
                                        :disabled="
                                            !selectedFacultyId || selectedFacultyId === 'all'
                                        "
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{
                                            selectedStudyProgramLabel
                                        }}</span>
                                        <ChevronsUpDown
                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                        />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[250px] p-0">
                                    <Command>
                                        <CommandInput
                                        placeholder="Search study program..."
                                        class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-none"
                                        />
                                        <CommandList>
                                            <CommandEmpty>No study program found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="() => {
                                                            selectedStudyProgramId = 'all';
                                                            openStudyProgram = false;
                                                        }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedStudyProgramId === 'all'
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    All Study Programs
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="studyProgram in studyPrograms"
                                                    :key="studyProgram.id"
                                                    :value="studyProgram.id.toString()"
                                                    @select="() => {
                                                        selectedStudyProgramId =
                                                            studyProgram.id.toString();
                                                        openStudyProgram = false;
                                                    }">
                                                    <Check
                                                        :class="cn('mr-2 h-4 w-4',
                                                            selectedStudyProgramId ===
                                                                studyProgram.id.toString()
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
                                                        )"
                                                    />
                                                    {{ studyProgram.name }}
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

            <!-- Access Controls Table -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Forms with Access Controls
                        <Badge variant="secondary">
                            {{ props.groupAccessControls.total }} forms
                        </Badge>
                    </CardTitle>

                    <div
                        v-if="props.groupAccessControls.total > 0"
                        class="text-sm text-muted-foreground"
                    >
                        <Button variant="outline" size="sm" @click="toggleAllGroups">
                            {{
                                openGroups.length === props.groupAccessControls.data.length
                                    ? "Collapse All"
                                    : "Expand All"
                            }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Empty State -->
                    <div
                        v-if="props.groupAccessControls.data.length === 0"
                        class="text-center py-12"
                    >
                        <Users class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">No access controls found</h3>
                        <p class="text-muted-foreground mb-4">
                            {{
                                hasActiveFilters
                                    ? "Try adjusting your search criteria."
                                    : "Get started by creating your first access control."
                            }}
                        </p>
                        <Link
                            :href="route('admin.form-access-controls.create')"
                            v-if="!hasActiveFilters"
                        >
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Create Access Control
                            </Button>
                        </Link>
                        <Button v-else variant="outline" @click="clearFilters">
                            <X class="h-4 w-4 mr-2" />
                            Clear Filters
                        </Button>
                    </div>

                    <!-- Table with Groups -->
                    <div v-else class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12"></TableHead>
                                    <TableHead>Form</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Study Program</TableHead>
                                    <TableHead>Faculty</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template
                                    v-for="group in props.groupAccessControls.data"
                                    :key="group.form_id"
                                >
                                    <!-- Group Header Row -->
                                    <TableRow
                                        class="bg-muted/50 cursor-pointer hover:bg-muted"
                                        @click="toggleGroup(group.form_id)"
                                    >
                                        <TableCell>
                                            <Checkbox
                                                :checked="isGroupSelected(group.controls)"
                                                :indeterminate="
                                                    isGroupPartiallySelected(group.controls)
                                                "
                                                @click.stop
                                                @update:checked="
                                                    toggleGroupSelection(group.controls)
                                                "
                                            />
                                        </TableCell>
                                        <TableCell colspan="5" class="font-medium">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <FileText
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                    {{ group.form.title }}
                                                    <Badge variant="secondary">
                                                        {{ group.jumlah_access_controls }}
                                                        access control{{
                                                            group.jumlah_access_controls !== 1
                                                                ? "s"
                                                                : ""
                                                        }}
                                                    </Badge>
                                                </div>
                                                <component
                                                    :is="
                                                        isGroupOpen(group.form_id)
                                                            ? ChevronUp
                                                            : ChevronDown
                                                    "
                                                    class="h-4 w-4 text-muted-foreground transition-transform duration-200"
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell></TableCell>
                                    </TableRow>

                                    <!-- Group Detail Rows -->
                                    <template v-if="isGroupOpen(group.form_id)">
                                        <TableRow
                                            v-for="control in group.controls"
                                            :key="control.id"
                                            class="border-t"
                                        >
                                            <TableCell>
                                                <Checkbox
                                                    :checked="selectedItems.includes(control.id)"
                                                    @update:checked="
                                                        toggleItemSelection(control.id)
                                                    "
                                                />
                                            </TableCell>
                                            <TableCell class="pl-12">
                                                <span class="text-sm text-muted-foreground">
                                                    ###
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {{ control.role.name }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {{ control.study_program.name }}
                                            </TableCell>
                                            <TableCell>
                                                {{ control.study_program.faculty.name }}
                                            </TableCell>
                                            <TableCell>
                                                {{ formatDate(control.created_at) }}
                                            </TableCell>
                                            <TableCell class="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreHorizontal class="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <Link
                                                            :href="
                                                                route(
                                                                    'admin.form-access-controls.show',
                                                                    control.id
                                                                )
                                                            "
                                                        >
                                                            <DropdownMenuItem>
                                                                <Eye class="h-4 w-4 mr-2" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                        </Link>
                                                        <Link
                                                            :href="
                                                                route(
                                                                    'admin.form-access-controls.edit',
                                                                    control.id
                                                                )
                                                            "
                                                        >
                                                            <DropdownMenuItem>
                                                                <Edit class="h-4 w-4 mr-2" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                        </Link>
                                                        <DropdownMenuItem
                                                            @click="
                                                                deleteFormAccessControl(control.id)
                                                            "
                                                            class="text-destructive cursor-pointer"
                                                        >
                                                            <Trash2 class="h-4 w-4 mr-2" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                </template>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.groupAccessControls.last_page > 1" class="flex justify-center mt-4">
                <div class="flex items-center gap-2">
                    <template v-for="link in props.groupAccessControls.links" :key="link.label">
                        <Button
                            v-if="link.url"
                            variant="outline"
                            size="sm"
                            @click="goToPage(link.url)"
                            :class="{
                                'bg-primary text-primary-foreground': link.active,
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
    </AuthenticatedLayout>
</template>