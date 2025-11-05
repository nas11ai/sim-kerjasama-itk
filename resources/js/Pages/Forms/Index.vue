<!-- filepath: e:\ITK\sim-kerjasama-itk\resources\js\Pages\Forms\Index.vue -->
<script setup lang="ts">
import { ref, watch, computed } from "vue";
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
    MoreHorizontal, 
    Plus, 
    Eye, 
    Edit, 
    Copy, 
    Trash2,
    Search,
    ChevronLeft,
    ChevronRight,
    FileText,
    Filter,
    X,
    ListFilter,
    CheckCircle,
    XCircle,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";
import { debounce } from 'lodash';

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

const search = ref(props.filters.search || '');
const perPage = ref(props.filters.per_page || 9);
const sortBy = ref(props.filters.sort_by || 'created_at');
const sortOrder = ref(props.filters.sort_order || 'desc');
const formTypeFilter = ref(props.filters.form_type || 'all');
const isActiveFilter = ref(props.filters.is_active || 'all');

const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value });
}, 300);

watch(search, (newValue) => {
    debouncedSearch(newValue);
});

watch(perPage, (newValue) => {
    updateFilters({ per_page: newValue });
});

watch(formTypeFilter, (newValue) => {
    updateFilters({ form_type: newValue });
});

watch(isActiveFilter, (newValue) => {
    updateFilters({ is_active: newValue });
});

const updateFilters = (newFilters: Record<string, any>) => {
    router.get(route('admin.forms.index'), {
        ...props.filters,
        ...newFilters,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearSearch = () => {
    search.value = '';
    updateFilters({ search: '' });
};

const clearAllFilters = () => {
    search.value = '';
    formTypeFilter.value = 'all';
    isActiveFilter.value = 'all';
    updateFilters({ 
        search: '', 
        form_type: 'all', 
        is_active: 'all' 
    });
};

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
        });
    }
};

const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (formTypeFilter.value !== 'all') count++;
    if (isActiveFilter.value !== 'all') count++;
    return count;
});

const hasActiveFilters = computed(() => activeFiltersCount.value > 0);

const deleteForm = (form: Form) => {
    if (confirm(`Are you sure you want to delete "${form.title}"?`)) {
        router.delete(route("admin.forms.destroy", form.id), {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form deleted successfully!",
                });
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
</script>

<template>
    <Head title="Forms" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
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
            <!-- Search and Filters -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <ListFilter class="h-5 w-5" />
                            Search & Filter
                            <Badge v-if="activeFiltersCount > 0" variant="secondary">
                                {{ activeFiltersCount }} active
                            </Badge>
                        </CardTitle>
                        <Button 
                            v-if="hasActiveFilters" 
                            variant="ghost" 
                            size="sm" 
                            @click="clearAllFilters"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Clear All
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Search Bar -->
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
                                <Button
                                    v-if="search"
                                    variant="ghost"
                                    size="sm"
                                    class="absolute right-1 top-1/2 transform -translate-y-1/2 h-7 w-7 p-0"
                                    @click="clearSearch"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <!-- Per Page Select -->
                        <div class="w-full lg:w-40">
                            <Select 
                                :model-value="perPage.toString()" 
                                @update:model-value="(value) => perPage = parseInt(value)"
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="6">6 per page</SelectItem>
                                    <SelectItem value="9">9 per page</SelectItem>
                                    <SelectItem value="12">12 per page</SelectItem>
                                    <SelectItem value="18">18 per page</SelectItem>
                                    <SelectItem value="24">24 per page</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Filter Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Form Type Filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Form Type
                            </label>
                            <Select 
                                :model-value="formTypeFilter" 
                                @update:model-value="(value) => formTypeFilter = value"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Form Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Form Types</SelectItem>
                                    <SelectItem 
                                        v-for="type in formTypes" 
                                        :key="type.id" 
                                        :value="type.id.toString()"
                                    >
                                        {{ type.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Status
                            </label>
                            <Select 
                                :model-value="isActiveFilter" 
                                @update:model-value="(value) => isActiveFilter = value"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="active">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle class="h-4 w-4 text-green-600" />
                                            Active
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        <div class="flex items-center gap-2">
                                            <XCircle class="h-4 w-4 text-red-600" />
                                            Inactive
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Results Info -->
            <div v-if="props.forms.total > 0" class="flex items-center justify-between text-sm text-muted-foreground">
                <div>
                    Showing {{ props.forms.from }} to {{ props.forms.to }} of {{ props.forms.total }} forms
                </div>
                <Badge variant="outline">
                    Page {{ props.forms.current_page }} of {{ props.forms.last_page }}
                </Badge>
            </div>

            <!-- Forms Grid -->
            <div v-if="props.forms.data.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card 
                    v-for="form in props.forms.data" 
                    :key="form.id" 
                    class="group hover:shadow-lg transition-shadow"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg line-clamp-2">
                                    {{ form.title }}
                                </CardTitle>
                                <CardDescription class="mt-1 line-clamp-2">
                                    {{ form.description || "No description" }}
                                </CardDescription>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('admin.forms.show', form.id)">
                                            <Eye class="h-4 w-4 mr-2" />
                                            View
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('admin.forms.edit', form.id)">
                                            <Edit class="h-4 w-4 mr-2" />
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="duplicateForm(form)">
                                        <Copy class="h-4 w-4 mr-2" />
                                        Duplicate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem 
                                        @click="deleteForm(form)" 
                                        class="text-destructive"
                                    >
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <Badge variant="outline">
                                    {{ form.form_type.name }}
                                </Badge>
                                <Badge 
                                    :variant="form.is_active ? 'default' : 'destructive'"
                                >
                                    {{ form.is_active ? "Active" : "Inactive" }}
                                </Badge>
                            </div>

                            <div class="text-sm text-muted-foreground">
                                {{ form.form_fields.length }} field{{ form.form_fields.length !== 1 ? "s" : "" }}
                            </div>

                            <div class="text-xs text-muted-foreground">
                                Created {{ formatDate(form.created_at) }}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="text-center py-12">
                    <div class="mx-auto max-w-md">
                        <FileText class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            {{ hasActiveFilters ? 'No forms found' : 'No forms yet' }}
                        </h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            {{ hasActiveFilters 
                                ? 'No data based on your filter.' 
                                : 'Get started by creating your first form.' 
                            }}
                        </p>
                        <div class="flex gap-2 justify-center">
                            <Link v-if="!hasActiveFilters" :href="route('admin.forms.create')">
                                <Button>
                                    <Plus class="h-4 w-4 mr-2" />
                                    Create Form
                                </Button>
                            </Link>
                            <Button v-else variant="outline" @click="clearAllFilters">
                                <X class="h-4 w-4 mr-2" />
                                Clear Filters
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.forms.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-sm text-gray-500">
                    Showing {{ props.forms.from }} to {{ props.forms.to }} of {{ props.forms.total }} results
                </div>
                
                <div class="flex items-center space-x-2">
                    <!-- Previous Button -->
                    <Button
                        variant="outline"
                        size="sm"
                        @click="goToPage(props.forms.links[0].url)"
                        :disabled="!props.forms.links[0].url"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        <span class="hidden sm:inline ml-2">Previous</span>
                    </Button>

                    <!-- Page Numbers -->
                    <template v-for="link in props.forms.links.slice(1, -1)" :key="link.label">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            :class="{
                                'bg-blue-600 text-white hover:bg-blue-700 hover:text-white': link.active,
                                'hover:bg-gray-100': !link.active
                            }"
                        >
                            {{ link.label }}
                        </Button>
                    </template>

                    <!-- Next Button -->
                    <Button
                        variant="outline"
                        size="sm"
                        @click="goToPage(props.forms.links[props.forms.links.length - 1].url)"
                        :disabled="!props.forms.links[props.forms.links.length - 1].url"
                    >
                        <span class="hidden sm:inline mr-2">Next</span>
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>