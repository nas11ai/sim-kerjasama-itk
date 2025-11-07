<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    Ellipsis,
    Plus,
    Eye,
    Edit,
    Copy,
    Trash2,
    Search,
    Filter,
    FileText,
    X,
    ArrowUpDown,
    CheckCircle,
    XCircle,
    ChevronsUpDown,
    Check,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";
import { debounce } from "lodash";
import { cn } from "@/lib/utils";

interface FormType {
    id: number;
    name: string;
}

interface FormField {
    id: number;
    label: string;
    is_required: boolean;
}

interface Form {
    id: number;
    title: string;
    description: string;
    is_active: boolean;
    form_type: FormType;
    form_fields: FormField[];
    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    forms: {
        data: Form[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    formTypes: FormType[];
    filters: {
        search?: string;
        per_page?: number;
        sort_by?: string;
        sort_order?: string;
        form_type?: string;
        is_active?: string;
    };
}

const props = defineProps<Props>();
const { toast } = useToast();

const search = ref(props.filters.search || "");
const perPage = ref(props.filters.per_page || 10);
const sortBy = ref(props.filters.sort_by || "created_at");
const sortOrder = ref(props.filters.sort_order || "desc");
const formTypeFilter = ref(props.filters.form_type || "all");
const isActiveFilter = ref(props.filters.is_active || "all");

const openFormType = ref(false);
const openStatus = ref(false);

const isDeleting = ref<number | null>(null);

const statusOptions = [
    { value: "all", label: "All Status" },
    { value: "active", label: "Active" },
    { value: "inactive", label: "Inactive" },
];

const selectedFormTypeLabel = computed(() => {
    if (formTypeFilter.value === "all") return "All Form Types";
    const type = props.formTypes.find(
        (t) => t.id.toString() === formTypeFilter.value
    );
    return type?.name || "Select type...";
});

const selectedStatusLabel = computed(() => {
    const status = statusOptions.find((s) => s.value === isActiveFilter.value);
    return status?.label || "Select status...";
});

const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value });
}, 300);

watch(search, (newValue) => {
    debouncedSearch(newValue);
});

watch(formTypeFilter, (newValue) => {
    updateFilters({ form_type: newValue });
});

watch(isActiveFilter, (newValue) => {
    updateFilters({ is_active: newValue });
});

const updateFilters = (newFilters: Record<string, any>) => {
    router.get(
        route("admin.forms.index"),
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

const sortTable = (column: string) => {
    const newSortOrder =
        sortBy.value === column && sortOrder.value === "asc" ? "desc" : "asc";
    sortBy.value = column;
    sortOrder.value = newSortOrder;

    updateFilters({
        sort_by: column,
        sort_order: newSortOrder,
    });
};

const clearAllFilters = () => {
    search.value = "";
    formTypeFilter.value = "all";
    isActiveFilter.value = "all";
    updateFilters({
        search: "",
        form_type: "all",
        is_active: "all",
    });
};

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: false,
        });
    }
};

const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (formTypeFilter.value !== "all") count++;
    if (isActiveFilter.value !== "all") count++;
    return count;
});

const deleteForm = (form: Form) => {
    if (confirm(`Are you sure you want to delete "${form.title}"?`)) {
        isDeleting.value = form.id;
        router.delete(route("admin.forms.destroy", form.id), {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form deleted successfully!",
                });
                isDeleting.value = null;
            },
            onError: () => {
                isDeleting.value = null;
            },
        });
    }
};

const duplicateForm = (form: Form) => {
    router.post(
        route("admin.forms.duplicate", form.id),
        {},
        {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form duplicated successfully!",
                });
            },
        }
    );
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return "text-gray-400";
    return sortOrder.value === "asc"
        ? "text-blue-600 rotate-0"
        : "text-blue-600 rotate-180";
};
</script>

<template>
    <Head title="Forms" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Form Management
                    </h2>
                </div>
                <Link :href="route('admin.forms.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create Form
                    </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Search & Filter
                            <Badge
                                v-if="activeFiltersCount > 0"
                                variant="secondary"
                            >
                                {{ activeFiltersCount }} active
                            </Badge>
                        </CardTitle>
                        <Button
                            v-if="activeFiltersCount > 0"
                            variant="ghost"
                            size="sm"
                            @click="clearAllFilters"
                            class="flex items-center"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Clear All
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"
                                />
                                <Input
                                    v-model="search"
                                    placeholder="Search forms by title, description, or type..."
                                    class="pl-10"
                                />
                            </div>
                        </div>

                        <div class="w-full lg:w-64">
                            <Popover v-model:open="openFormType">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openFormType"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{
                                            selectedFormTypeLabel
                                        }}</span>
                                        <ChevronsUpDown
                                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                        />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[250px] p-0">
                                    <Command>
                                        <div
                                            class="flex items-center border-b px-3"
                                            cmdk-input-wrapper
                                        >
                                            <Search
                                                class="mr-2 h-4 w-4 shrink-0 opacity-50"
                                            />
                                            <CommandInput
                                                placeholder="Search form type..."
                                                class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-none"
                                            />
                                        </div>
                                        <CommandList>
                                            <CommandEmpty
                                                >No form type
                                                found.</CommandEmpty
                                            >
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="
                                                        () => {
                                                            formTypeFilter =
                                                                'all';
                                                            openFormType = false;
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                formTypeFilter ===
                                                                    'all'
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    All Form Types
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="type in formTypes"
                                                    :key="type.id"
                                                    :value="
                                                        type.id.toString()
                                                    "
                                                    @select="
                                                        () => {
                                                            formTypeFilter =
                                                                type.id.toString();
                                                            openFormType = false;
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                formTypeFilter ===
                                                                    type.id.toString()
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    {{ type.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="w-full lg:w-48">
                            <Popover v-model:open="openStatus">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openStatus"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{
                                            selectedStatusLabel
                                        }}</span>
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
                                                            isActiveFilter =
                                                                status.value;
                                                            openStatus = false;
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                isActiveFilter ===
                                                                    status.value
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
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

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Forms
                            <Badge variant="secondary"
                                >{{ props.forms.total }} total</Badge
                            >
                        </CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        class="rounded-md border ml-4 mr-4 mb-4 overflow-x-auto"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">#</TableHead>
                                    <TableHead>
                                        <button
                                            @click="sortTable('title')"
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                                        >
                                            Form Title
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('title')"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>
                                        <button
                                            @click="sortTable('form_type')"
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                                        >
                                            Type
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="
                                                    getSortIcon('form_type')
                                                "
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="text-center"
                                        >Status</TableHead
                                    >
                                    <TableHead>
                                        <button
                                            @click="sortTable('created_at')"
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                                        >
                                            Created At
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="
                                                    getSortIcon('created_at')
                                                "
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="text-center w-20"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-if="props.forms.data.length === 0"
                                >
                                    <TableCell
                                        colspan="7"
                                        class="text-center py-8 text-gray-500"
                                    >
                                        <FileText
                                            class="h-12 w-12 mx-auto mb-4 opacity-50"
                                        />
                                        <p class="font-medium">
                                            No forms found
                                        </p>
                                        <p class="text-sm mt-1">
                                            {{
                                                activeFiltersCount > 0
                                                    ? "Try adjusting your filters"
                                                    : "Create your first form to get started"
                                            }}
                                        </p>
                                    </TableCell>
                                </TableRow>

                                <TableRow
                                    v-for="(form, index) in props.forms.data"
                                    :key="form.id"
                                    class="hover:bg-muted/50"
                                >
                                    <TableCell class="font-medium">
                                        {{ props.forms.from + index }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ form.title }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div
                                            class="max-w-md truncate text-sm text-gray-600"
                                        >
                                            {{ form.description || "-" }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            class="bg-blue-50 text-blue-700 border-blue-200"
                                        >
                                            {{ form.form_type.name }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            :variant="
                                                form.is_active
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                            class="flex items-center gap-1 w-fit mx-auto"
                                        >
                                            <CheckCircle
                                                v-if="form.is_active"
                                                class="h-3 w-3"
                                            />
                                            <XCircle
                                                v-else
                                                class="h-3 w-3"
                                            />
                                            {{
                                                form.is_active
                                                    ? "Active"
                                                    : "Inactive"
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{
                                        formatDate(form.created_at)
                                    }}</TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    :disabled="
                                                        isDeleting === form.id
                                                    "
                                                >
                                                    <Ellipsis
                                                        class="h-4 w-4"
                                                    />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem as-child>
                                                    <Link
                                                        :href="
                                                            route(
                                                                'admin.forms.show',
                                                                form.id
                                                            )
                                                        "
                                                    >
                                                        <Eye
                                                            class="h-4 w-4 mr-2"
                                                        />
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link
                                                        :href="
                                                            route(
                                                                'admin.forms.edit',
                                                                form.id
                                                            )
                                                        "
                                                    >
                                                        <Edit
                                                            class="h-4 w-4 mr-2"
                                                        />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        duplicateForm(form)
                                                    "
                                                >
                                                    <Copy
                                                        class="h-4 w-4 mr-2"
                                                    />
                                                    Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="deleteForm(form)"
                                                    class="text-red-600"
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
                </CardContent>
            </Card>

            <div v-if="props.forms.last_page > 1" class="flex justify-center">
                <div class="flex items-center gap-2">
                    <template
                        v-for="link in props.forms.links"
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