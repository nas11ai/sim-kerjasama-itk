<!-- filepath: e:\ITK\sim-kerjasama-itk\resources\js\Pages\FormPhases\Index.vue -->
<script setup lang="ts">
import { computed, ref, watch } from "vue";
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
    Eye,
    Edit,
    Trash2,
    MoreHorizontal,
    Users,
    FileText,
    Settings,
    X,
    Filter,
    CheckCircle,
    XCircle,
    ChevronsUpDown,
    Check,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";
import { debounce } from "lodash";
import { cn } from "@/lib/utils";

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
    needs_review: boolean;
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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    formPhases: {
        data: FormPhase[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: PaginationLink[];
    };
    filters: {
        search?: string;
        is_active?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();
const { toast } = useToast();

const search = ref(props.filters.search || "");
const isActiveFilter = ref(props.filters.is_active || "all");
const perPage = ref(props.filters.per_page || 10);

const openStatus = ref(false);

const statusOptions = [
    { value: "all", label: "All Status" },
    { value: "active", label: "Active" },
    { value: "inactive", label: "Inactive" },
];

const selectedStatusLabel = computed(() => {
    const status = statusOptions.find((s) => s.value === isActiveFilter.value);
    return status?.label || "Select status...";
});

// Active filters count
const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (isActiveFilter.value !== "all") count++;
    return count;
});

// Debounced search
const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value });
}, 300);

// Watch for changes
watch(search, (newValue) => {
    debouncedSearch(newValue);
});

watch(isActiveFilter, (newValue) => {
    updateFilters({ is_active: newValue });
});

const updateFilters = (newFilters: Record<string, any>) => {
    router.get(
        route("admin.form-phases.index"),
        {
            ...props.filters,
            ...newFilters,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const clearAllFilters = () => {
    search.value = "";
    isActiveFilter.value = "all";
    updateFilters({
        search: "",
        is_active: "all",
    });
};

// Change per page
const changePerPage = (newPerPage: string) => {
    const perPageNumber = parseInt(newPerPage);
    perPage.value = perPageNumber;
    updateFilters({ per_page: perPageNumber });
};

// Pagination
const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: false,
        });
    }
};

const deleteFormPhase = (id: number) => {
    if (confirm("Are you sure you want to delete this form phase?")) {
        router.delete(route("admin.form-phases.destroy", id), {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form phase deleted successfully!",
                });
            },
            onError: () => {
                toast({
                    title: "Error",
                    description: "Failed to delete form phase.",
                    variant: "destructive",
                });
            },
        });
    }
};

const toggleStatus = (formPhase: FormPhase) => {
    router.patch(
        route("admin.form-phases.update-status", formPhase.id),
        {
            is_active: !formPhase.is_active,
        },
        {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Status updated successfully!",
                });
            },
            onError: () => {
                toast({
                    title: "Error",
                    description: "Failed to update status.",
                    variant: "destructive",
                });
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
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Form Phases Management
                    </h2>
                </div>
                <Link :href="route('admin.form-phases.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create Form Phase
                    </Button>
                </Link>
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
                            v-if="activeFiltersCount > 0"
                            variant="ghost"
                            size="sm"
                            @click="clearAllFilters"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Clear All
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
                                    v-model="search"
                                    placeholder="Search form phases by title or description..."
                                    class="pl-10"
                                />
                            </div>
                        </div>

                        <!-- Filter (Combobox) -->
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
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="status in statusOptions"
                                                    :key="status.value"
                                                    :value="status.value"
                                                    @select="
                                                        () => {
                                                            isActiveFilter = status.value;
                                                            openStatus = false;
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                isActiveFilter === status.value
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    <div class="flex items-center gap-2">
                                                        <CheckCircle
                                                            v-if="status.value === 'active'"
                                                            class="h-4 w-4 text-green-600"
                                                        />
                                                        <XCircle
                                                            v-else-if="status.value === 'inactive'"
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

            <!-- Form Phases Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Form Phases List
                        <Badge variant="secondary">{{ props.formPhases.total }} total</Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="rounded-md border ml-4 mr-4 mb-4 overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">#</TableHead>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead class="text-center">Status</TableHead>
                                    <TableHead class="text-center">Phase Details</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <!-- Empty State -->
                                <TableRow v-if="props.formPhases.data.length === 0">
                                    <TableCell colspan="7" class="text-center py-8 text-gray-500">
                                        <Settings class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                        <p class="font-medium">No form phases found</p>
                                        <p class="text-sm mt-1">
                                            {{
                                                activeFiltersCount > 0
                                                    ? "Try adjusting your filters"
                                                    : "Create your first form phase to get started"
                                            }}
                                        </p>
                                    </TableCell>
                                </TableRow>

                                <!-- Data Rows -->
                                <TableRow
                                    v-for="(formPhase, index) in props.formPhases.data"
                                    :key="formPhase.id"
                                    class="hover:bg-muted/50"
                                >
                                    <TableCell class="font-medium">
                                        {{ props.formPhases.from + index }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2 font-medium">
                                            <FileText class="h-4 w-4 text-muted-foreground" />
                                            {{ formPhase.title }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="max-w-md truncate text-sm text-gray-600">
                                            {{ formPhase.description || "-" }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            :variant="
                                                formPhase.is_active ? 'default' : 'destructive'
                                            "
                                            class="flex items-center gap-1 w-fit mx-auto"
                                        >
                                            <CheckCircle
                                                v-if="formPhase.is_active"
                                                class="h-3 w-3"
                                            />
                                            <XCircle v-else class="h-3 w-3" />
                                            {{ formPhase.is_active ? "Active" : "Inactive" }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <Users class="h-4 w-4 text-muted-foreground" />
                                            <Badge variant="secondary">
                                                {{ formPhase.form_phase_details.length }} phases
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm text-muted-foreground">
                                            {{
                                                new Date(
                                                    formPhase.created_at
                                                ).toLocaleDateString("en-US", {
                                                    year: "numeric",
                                                    month: "short",
                                                    day: "numeric",
                                                })
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
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
                                                                'admin.form-phases.show',
                                                                formPhase.id
                                                            )
                                                        "
                                                    >
                                                        <Eye class="h-4 w-4 mr-2" />
                                                        View Details
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link
                                                        :href="
                                                            route(
                                                                'admin.form-phases.edit',
                                                                formPhase.id
                                                            )
                                                        "
                                                    >
                                                        <Edit class="h-4 w-4 mr-2" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="toggleStatus(formPhase)"
                                                    class="cursor-pointer"
                                                >
                                                    <Settings class="h-4 w-4 mr-2" />
                                                    {{
                                                        formPhase.is_active
                                                            ? "Deactivate"
                                                            : "Activate"
                                                    }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="deleteFormPhase(formPhase.id)"
                                                    class="text-destructive cursor-pointer"
                                                >
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
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.formPhases.last_page > 1" class="flex justify-center">
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