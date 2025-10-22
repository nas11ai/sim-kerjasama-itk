<!-- resources/js/Pages/Admin/FormPhases/Show.vue -->
<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Separator } from "@/Components/ui/separator";
import {
    ArrowLeft,
    Edit,
    FileText,
    Users,
    Building,
    Calendar,
    CheckCircle,
    XCircle,
    ClipboardList,
    Plus,
    Eye,
    Settings,
    Star
} from "lucide-vue-next";

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

interface PhaseType {
    id: number;
    name: string;
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

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
    is_required: boolean;
    is_active: boolean;
    order: number;
    fields_count: number;
    required_fields_count: number;
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    form_phase_details: FormPhaseDetail[];
    review_evaluation_forms?: ReviewEvaluationForm[];
    review_evaluation_forms_count?: number;
    required_review_evaluation_forms_count?: number;
}

interface Props {
    formPhase: FormPhase;
}

const props = defineProps<Props>();

const sortedPhaseDetails = computed(() =>
    [...props.formPhase.form_phase_details].sort((a, b) => a.order - b.order)
);

const sortedEvaluationForms = computed(() =>
    [...(props.formPhase.review_evaluation_forms || [])].sort((a, b) => a.order - b.order)
);

const uniqueFormsCount = computed(() =>
    new Set(sortedPhaseDetails.value.map(d => d.form_access_control.form.id)).size
);

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};
</script>

<template>

    <Head title="Form Phase Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.form-phases.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Back to Form Phases
                    </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Form Phase Details
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.form-phases.evaluation-forms', formPhase.id)">
                    <Button variant="outline">
                        <ClipboardList class="h-4 w-4 mr-2" />
                        Manage Evaluation Forms
                        <Badge v-if="formPhase.review_evaluation_forms_count" variant="secondary" class="ml-2">
                            {{ formPhase.review_evaluation_forms_count }}
                        </Badge>
                    </Button>
                    </Link>
                    <Link :href="route('admin.form-phases.edit', formPhase.id)">
                    <Button>
                        <Edit class="h-4 w-4 mr-2" />
                        Edit Phase
                    </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Phase Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Phase Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Title
                            </h3>
                            <p class="text-lg font-medium">
                                {{ formPhase.title }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Status
                            </h3>
                            <Badge :variant="formPhase.is_active ? 'default' : 'secondary'"
                                class="flex items-center gap-1 w-fit">
                                <CheckCircle v-if="formPhase.is_active" class="h-3 w-3" />
                                <XCircle v-else class="h-3 w-3" />
                                {{ formPhase.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="formPhase.description">
                        <h3 class="font-medium text-sm text-muted-foreground mb-1">
                            Description
                        </h3>
                        <p class="text-gray-700">
                            {{ formPhase.description }}
                        </p>
                    </div>

                    <Separator />

                    <div class="grid gap-6 md:grid-cols-2 text-sm">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Created:</span>
                            <span>{{ formatDate(formPhase.created_at) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Updated:</span>
                            <span>{{ formatDate(formPhase.updated_at) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Review Evaluation Forms -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5" />
                            Review Evaluation Forms
                            <Badge variant="secondary">
                                {{ formPhase.review_evaluation_forms_count || 0 }} forms
                            </Badge>
                        </CardTitle>
                        <Link :href="route('admin.form-phases.evaluation-forms', formPhase.id)">
                        <Button size="sm">
                            <Settings class="h-4 w-4 mr-2" />
                            Manage Forms
                        </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div v-if="sortedEvaluationForms.length === 0" class="text-center py-8 text-muted-foreground">
                        <ClipboardList class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <h4 class="font-medium mb-2">No evaluation forms configured</h4>
                        <p class="text-sm mb-4">
                            Evaluation forms allow reviewers to systematically evaluate submissions in this phase.
                        </p>
                        <Link :href="route('admin.form-phases.evaluation-forms', formPhase.id)">
                        <Button size="sm">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Evaluation Forms
                        </Button>
                        </Link>
                    </div>

                    <!-- Evaluation Forms List -->
                    <div v-else class="space-y-3">
                        <div v-for="form in sortedEvaluationForms" :key="form.id"
                            class="border rounded-lg p-4 bg-card hover:bg-muted/50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h4 class="font-medium">{{ form.title }}</h4>
                                        <Badge :variant="form.is_required ? 'default' : 'outline'" class="text-xs">
                                            {{ form.is_required ? 'Required' : 'Optional' }}
                                        </Badge>
                                        <Badge variant="outline" class="text-xs">
                                            Order: {{ form.order }}
                                        </Badge>
                                    </div>

                                    <p v-if="form.description" class="text-sm text-muted-foreground mb-3">
                                        {{ form.description }}
                                    </p>

                                    <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1">
                                            <FileText class="h-3 w-3" />
                                            {{ form.fields_count }} fields
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <Star class="h-3 w-3" />
                                            {{ form.required_fields_count }} required
                                        </span>
                                        <Badge :variant="form.is_active ? 'default' : 'secondary'" class="text-xs">
                                            {{ form.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1">
                                    <Link :href="route('admin.review-evaluation-forms.preview', form.id)">
                                    <Button size="sm" variant="ghost">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                    </Link>
                                    <Link :href="route('admin.review-evaluation-forms.edit', form.id)">
                                    <Button size="sm" variant="ghost">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Forms Summary -->
                        <div class="pt-3 border-t">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">
                                    Total: {{ sortedEvaluationForms.length }} forms
                                    ({{ formPhase.required_review_evaluation_forms_count || 0 }} required)
                                </span>
                                <Link :href="route('admin.form-phases.evaluation-forms', formPhase.id)">
                                <Button variant="outline" size="sm">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add More Forms
                                </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Phase Details -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Phase Details ({{ sortedPhaseDetails.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div v-if="sortedPhaseDetails.length === 0" class="text-center py-8 text-muted-foreground">
                        <Users class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No phase details configured yet.</p>
                    </div>

                    <!-- Phase Details List -->
                    <div v-else class="space-y-4">
                        <div v-for="(detail, index) in sortedPhaseDetails" :key="detail.id"
                            class="border rounded-lg p-4 bg-card">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        Step {{ detail.order }}
                                    </Badge>
                                    <Badge v-if="detail.needs_review" class="text-xs">
                                        Needs Review
                                    </Badge>
                                    <Badge class="text-xs">
                                        {{ detail.phase_type.name }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <!-- Form Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <FileText class="h-4 w-4 text-muted-foreground" />
                                        Form
                                    </div>
                                    <p class="text-sm text-muted-foreground pl-6">
                                        {{ detail.form_access_control.form.title }}
                                    </p>
                                </div>

                                <!-- Role Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <Users class="h-4 w-4 text-muted-foreground" />
                                        Role
                                    </div>
                                    <p class="text-sm text-muted-foreground pl-6">
                                        {{ detail.form_access_control.role.name }}
                                    </p>
                                </div>

                                <!-- Study Program Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <Building class="h-4 w-4 text-muted-foreground" />
                                        Study Program
                                    </div>
                                    <div class="text-sm text-muted-foreground pl-6">
                                        <p>{{ detail.form_access_control.study_program.name }}</p>
                                        <p class="text-xs opacity-75">
                                            {{ detail.form_access_control.study_program.faculty.name }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress indicator -->
                            <div v-if="index < sortedPhaseDetails.length - 1" class="flex justify-center mt-4">
                                <div class="w-px h-6 bg-border"></div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Statistics -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Users class="h-8 w-8 text-blue-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ sortedPhaseDetails.length }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Total Steps
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <ClipboardList class="h-8 w-8 text-orange-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ formPhase.review_evaluation_forms_count || 0 }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Evaluation Forms
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Star class="h-8 w-8 text-red-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ formPhase.required_review_evaluation_forms_count || 0 }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Required Forms
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <FileText class="h-8 w-8 text-green-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ uniqueFormsCount }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Unique Forms
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
