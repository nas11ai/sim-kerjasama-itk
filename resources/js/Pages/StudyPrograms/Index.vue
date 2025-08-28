<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
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
    MoreVertical,
    Edit,
    Trash2,
    GraduationCap,
    Building2,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Filter
} from "lucide-vue-next";
import { debounce } from 'lodash';

interface Faculty {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
    faculty: Faculty;
    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedStudyPrograms {
    data: StudyProgram[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

interface Filters {
    search: string;
    faculty_id: string;
    per_page: number;
    sort_by: string;
    sort_order: string;
}

interface FlashMessages {
    success?: string;
    error?: string;
}

interface Props {
    studyPrograms: PaginatedStudyPrograms;
    faculties: Faculty[];
    filters: Filters;
}

// Extend the existing PageProps interface
interface ExtendedPageProps {
    flash?: FlashMessages;
    auth: any; // This should match your existing auth type
    [key: string]: any; // Allow additional properties
}

const props = defineProps<Props>();
const page = usePage<ExtendedPageProps>();

// Reactive filters
const search = ref(props.filters.search || '');
const facultyFilter = ref(props.filters.faculty_id || 'all');
const perPage = ref(props.filters.per_page || 10);
const sortBy = ref(props.filters.sort_by || 'name');
const sortOrder = ref(props.filters.sort_order || 'asc');

// Loading states
const isDeleting = ref<number | null>(null);

// Search debounced function
const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value });
}, 300);

// Watch for changes
watch(search, (newValue) => {
    debouncedSearch(newValue);
});

watch(facultyFilter, (newValue) => {
    const filterValue = newValue === 'all' ? '' : newValue;
    updateFilters({ faculty_id: filterValue });
});

// Update filters function
const updateFilters = (newFilters: Partial<Filters>) => {
    router.get(route('admin.faculties.study-programs'), {
        ...props.filters,
        ...newFilters,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Sort function
const sortTable = (column: string) => {
    const newSortOrder = sortBy.value === column && sortOrder.value === 'asc' ? 'desc' : 'asc';
    sortBy.value = column;
    sortOrder.value = newSortOrder;

    updateFilters({
        sort_by: column,
        sort_order: newSortOrder,
    });
};

// Delete function
const deleteStudyProgram = async (studyProgram: StudyProgram) => {
    if (!confirm(`Are you sure you want to delete "${studyProgram.name}"?`)) {
        return;
    }

    isDeleting.value = studyProgram.id;

    try {
        await router.delete(route('admin.faculties.study-programs.destroy', studyProgram.id), {
            preserveState: false,
        });
    } finally {
        isDeleting.value = null;
    }
};

// Pagination function
const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
        });
    }
};

// Per page change
const changePerPage = (newPerPage: any) => {
    if (newPerPage !== null && newPerPage !== undefined) {
        let perPageNumber: number;
        if (typeof newPerPage === 'string') {
            perPageNumber = parseInt(newPerPage);
        } else if (typeof newPerPage === 'bigint') {
            perPageNumber = Number(newPerPage);
        } else if (typeof newPerPage === 'number') {
            perPageNumber = newPerPage;
        } else {
            // Handle Record<string, any> or other types
            return;
        }
        perPage.value = perPageNumber;
        updateFilters({ per_page: perPageNumber });
    }
};

// Clear filters
const clearFilters = () => {
    search.value = '';
    facultyFilter.value = 'all'; // Changed from '' to 'all'
    updateFilters({ search: '', faculty_id: '' });
};

// Success/Error messages
const successMessage = computed(() => page.props.flash?.success);
const errorMessage = computed(() => page.props.flash?.error);

// Sort icon helper
const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return 'text-gray-400';
    return sortOrder.value === 'asc' ? 'text-blue-600 rotate-0' : 'text-blue-600 rotate-180';
};

// Active filters count
const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (facultyFilter.value && facultyFilter.value !== 'all') count++; // Updated condition
    return count;
});
</script>

<template>

    <Head title="Study Programs Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <GraduationCap class="h-6 w-6 text-green-600" />
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Study Programs Management
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Button @click="router.visit(route('faculties.index'))" variant="outline">
                        <Building2 class="h-4 w-4 mr-2" />
                        Faculties
                    </Button>
                    <Button @click="router.visit(route('admin.faculties.study-programs.create'))">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Study Program
                    </Button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Success/Error Messages -->
            <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ errorMessage }}
            </div>

            <!-- Filters Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Search class="h-5 w-5" />
                            Search & Filter
                            <Badge v-if="activeFiltersCount > 0" variant="secondary">
                                {{ activeFiltersCount }} active
                            </Badge>
                        </CardTitle>
                        <Button v-if="activeFiltersCount > 0" variant="ghost" size="sm" @click="clearFilters">
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
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                <Input v-model="search" placeholder="Search study programs or faculties..."
                                    class="pl-10" />
                            </div>
                        </div>

                        <!-- Faculty Filter -->
                        <div class="w-full lg:w-64">
                            <Select v-model="facultyFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="Filter by Faculty" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Faculties</SelectItem>
                                    <SelectItem v-for="faculty in faculties" :key="faculty.id"
                                        :value="faculty.id.toString()">
                                        {{ faculty.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Per Page Select -->
                        <div class="w-full lg:w-32">
                            <Select :model-value="perPage.toString()" @update:model-value="changePerPage">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="5">5 per page</SelectItem>
                                    <SelectItem value="10">10 per page</SelectItem>
                                    <SelectItem value="25">25 per page</SelectItem>
                                    <SelectItem value="50">50 per page</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Data Table Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5" />
                            Study Programs
                            <Badge variant="secondary">{{ studyPrograms.total }} total</Badge>
                        </CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">#</TableHead>
                                    <TableHead>
                                        <button @click="sortTable('name')"
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold">
                                            Program Name
                                            <ArrowUpDown class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('name')" />
                                        </button>
                                    </TableHead>
                                    <TableHead>
                                        <button @click="sortTable('faculty_name')"
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold">
                                            Faculty
                                            <ArrowUpDown class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('faculty_name')" />
                                        </button>
                                    </TableHead>
                                    <TableHead>
                                        <button @click="sortTable('created_at')"
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold">
                                            Created At
                                            <ArrowUpDown class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('created_at')" />
                                        </button>
                                    </TableHead>
                                    <TableHead class="text-center w-20">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="studyPrograms.data.length === 0">
                                    <TableCell colspan="5" class="text-center py-8 text-gray-500">
                                        <GraduationCap class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                        <p>No study programs found</p>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="(program, index) in studyPrograms.data" :key="program.id"
                                    class="hover:bg-muted/50">
                                    <TableCell class="font-medium">
                                        {{ studyPrograms.from + index }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-medium">{{ program.name }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                            {{ program.faculty.name }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {{ new Date(program.created_at).toLocaleDateString() }}
                                    </TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="sm" :disabled="isDeleting === program.id">
                                                    <MoreVertical class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    @click="router.visit(route('admin.faculties.study-programs.edit', program.id))">
                                                    <Edit class="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="deleteStudyProgram(program)"
                                                    class="text-red-600">
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

                    <!-- Pagination -->
                    <div v-if="studyPrograms.last_page > 1"
                        class="flex items-center justify-between px-6 py-4 border-t">
                        <div class="text-sm text-gray-500">
                            Showing {{ studyPrograms.from }} to {{ studyPrograms.to }} of {{ studyPrograms.total }}
                            results
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Previous Button -->
                            <Button variant="outline" size="sm" @click="goToPage(studyPrograms.links[0].url)"
                                :disabled="!studyPrograms.links[0].url">
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>

                            <!-- Page Numbers -->
                            <template v-for="link in studyPrograms.links.slice(1, -1)" :key="link.label">
                                <Button variant="outline" size="sm" @click="goToPage(link.url)" :disabled="!link.url"
                                    :class="{
                                        'bg-blue-600 text-white hover:bg-blue-700': link.active,
                                        'hover:bg-gray-100': !link.active
                                    }">
                                    {{ link.label }}
                                </Button>
                            </template>

                            <!-- Next Button -->
                            <Button variant="outline" size="sm"
                                @click="goToPage(studyPrograms.links[studyPrograms.links.length - 1].url)"
                                :disabled="!studyPrograms.links[studyPrograms.links.length - 1].url">
                                Next
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
