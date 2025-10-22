<!-- resources/js/Pages/Admin/ReviewEvaluationForms/Index.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    ClipboardList,
    Plus,
    Search,
    Filter,
    MoreHorizontal,
    Edit,
    Eye,
    Copy,
    Trash2,
    Users,
    FileText
} from 'lucide-vue-next';

interface FormPhase {
    id: number;
    title: string;
}

interface ReviewFormField {
    id: number;
    label: string;
    field_type: {
        name: string;
    };
    is_required: boolean;
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
    is_required: boolean;
    is_active: boolean;
    order: number;
    form_phase: FormPhase;
    review_form_fields: ReviewFormField[];
}

interface Props {
    evaluationForms: {
        data: ReviewEvaluationForm[];
        links: any[];
        meta: any;
    };
    formPhases: FormPhase[];
    filters: {
        form_phase_id?: string;
        search?: string;
    };
}

const props = defineProps<Props>();

const searchTerm = ref(props.filters.search || '');
const formPhaseFilter = ref(props.filters.form_phase_id || '');

const applyFilters = () => {
    const params: any = {};

    if (searchTerm.value) {
        params.search = searchTerm.value;
    }

    if (formPhaseFilter.value) {
        params.form_phase_id = formPhaseFilter.value;
    }

    router.get(route('admin.review-evaluation-forms.index'), params, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    searchTerm.value = '';
    formPhaseFilter.value = '';
    router.get(route('admin.review-evaluation-forms.index'));
};

const deleteForm = (form: ReviewEvaluationForm) => {
    if (confirm(`Are you sure you want to delete "${form.title}"?`)) {
        router.delete(route('admin.review-evaluation-forms.destroy', form.id));
    }
};

const duplicateForm = (form: ReviewEvaluationForm) => {
    router.post(route('admin.review-evaluation-forms.duplicate', form.id));
};

const getFieldTypesPreview = (fields: ReviewFormField[]): string => {
    const types = [...new Set(fields.map(f => f.field_type.name))];
    return types.slice(0, 3).join(', ') + (types.length > 3 ? '...' : '');
};
</script>

<template>

    <Head title="Review Evaluation Forms" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Review Evaluation Forms
                    </h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        Manage evaluation forms for reviewers to assess submissions
                    </p>
                </div>
                <Link :href="route('admin.review-evaluation-forms.create')">
                <Button>
                    <Plus class="h-4 w-4 mr-2" />
                    Create Evaluation Form
                </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Filter Forms
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label class="text-sm font-medium mb-2 block">Search</label>
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input v-model="searchTerm" placeholder="Search evaluation forms..." class="pl-10"
                                    @keyup.enter="applyFilters" />
                            </div>
                        </div>

                        <div class="w-48">
                            <label class="text-sm font-medium mb-2 block">Form Phase</label>
                            <Select v-model="formPhaseFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All phases" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All phases</SelectItem>
                                    <SelectItem v-for="phase in formPhases" :key="phase.id"
                                        :value="phase.id.toString()">
                                        {{ phase.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex gap-2">
                            <Button @click="applyFilters">
                                <Search class="h-4 w-4 mr-2" />
                                Filter
                            </Button>
                            <Button variant="outline" @click="clearFilters">
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Evaluation Forms Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Evaluation Forms</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="evaluationForms.data.length === 0" class="text-center py-12">
                        <ClipboardList class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">No Evaluation Forms Found</h3>
                        <p class="text-muted-foreground mb-4">
                            No evaluation forms match your current filters or none have been created yet.
                        </p>
                        <Link :href="route('admin.review-evaluation-forms.create')">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Create First Evaluation Form
                        </Button>
                        </Link>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Form Title</TableHead>
                                    <TableHead>Form Phase</TableHead>
                                    <TableHead>Fields</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Usage</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="form in evaluationForms.data" :key="form.id">
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ form.title }}</div>
                                            <div v-if="form.description" class="text-sm text-muted-foreground">
                                                {{ form.description }}
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <Badge :variant="form.is_required ? 'destructive' : 'secondary'"
                                                    class="text-xs">
                                                    {{ form.is_required ? 'Required' : 'Optional' }}
                                                </Badge>
                                                <span class="text-xs text-muted-foreground">Order: {{ form.order
                                                    }}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-medium">{{ form.form_phase.title }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ form.review_form_fields.length }} fields</div>
                                            <div v-if="form.review_form_fields.length > 0"
                                                class="text-sm text-muted-foreground">
                                                {{ getFieldTypesPreview(form.review_form_fields) }}
                                            </div>
                                            <div class="text-xs text-muted-foreground mt-1">
                                                Required: {{form.review_form_fields.filter(f => f.is_required).length
                                                }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="form.is_active ? 'default' : 'secondary'">
                                            {{ form.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                            <Users class="h-3 w-3" />
                                            0 assignments
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
                                                <DropdownMenuItem asChild>
                                                    <Link :href="route('admin.review-evaluation-forms.show', form.id)"
                                                        class="cursor-pointer">
                                                    <Eye class="h-4 w-4 mr-2" />
                                                    View
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link :href="route('admin.review-evaluation-forms.edit', form.id)"
                                                        class="cursor-pointer">
                                                    <Edit class="h-4 w-4 mr-2" />
                                                    Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="duplicateForm(form)" class="cursor-pointer">
                                                    <Copy class="h-4 w-4 mr-2" />
                                                    Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        :href="route('admin.review-evaluation-forms.preview', form.id)"
                                                        class="cursor-pointer">
                                                    <FileText class="h-4 w-4 mr-2" />
                                                    Preview
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="deleteForm(form)"
                                                    class="cursor-pointer text-destructive focus:text-destructive">
                                                    <Trash2 class="h-4 w-4 mr-2" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-muted-foreground">
                                Showing {{ evaluationForms.meta.from || 0 }} to {{ evaluationForms.meta.to || 0 }}
                                of {{ evaluationForms.meta.total }} results
                            </div>

                            <div class="flex gap-2">
                                <Link v-for="link in evaluationForms.links" :key="link.label" :href="link.url"
                                    v-html="link.label" :class="[
                                        'px-3 py-2 text-sm border rounded-md',
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-background hover:bg-muted border-border',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
