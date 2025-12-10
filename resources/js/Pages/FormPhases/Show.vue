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

interface FormPhaseDetail {
    id: number;
    order: number;
    needs_review: boolean;
    phase_type: PhaseType;
    form_access_control: FormAccessControl;
    review_evaluation_forms?: ReviewEvaluationForm[];
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    form_phase_details: FormPhaseDetail[];
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

const getEvaluationFormsForDetail = (detail: FormPhaseDetail) => {
    if (!detail.review_evaluation_forms) return [];
    return [...detail.review_evaluation_forms].sort((a, b) => a.order - b.order);
};

const getTotalEvaluationFormsCount = computed(() => {
    return sortedPhaseDetails.value.reduce((total, detail) => {
        return total + (detail.review_evaluation_forms?.length || 0);
    }, 0);
});

const getRequiredEvaluationFormsCount = computed(() => {
    return sortedPhaseDetails.value.reduce((total, detail) => {
        return total + (detail.review_evaluation_forms?.filter(f => f.is_required && f.is_active).length || 0);
    }, 0);
});
</script>

<template>

    <Head title="Detail Tahap Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.form-phases.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Kembali
                    </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Detail Tahap Formulir
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.form-phases.edit', formPhase.id)">
                    <Button>
                        <Edit class="h-4 w-4 mr-2" />
                        Edit Tahap
                    </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Phase Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Informasi Tahap
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Judul
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
                                {{ formPhase.is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="formPhase.description">
                        <h3 class="font-medium text-sm text-muted-foreground mb-1">
                            Deskripsi
                        </h3>
                        <p class="text-gray-700">
                            {{ formPhase.description }}
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Dibuat Pada
                            </h3>
                            <p class="text-sm">
                                {{ formatDate(formPhase.created_at) }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Terakhir Diperbarui
                            </h3>
                            <p class="text-sm">
                                {{ formatDate(formPhase.updated_at) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Phase Details -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Detail Tahap ({{ sortedPhaseDetails.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div v-if="sortedPhaseDetails.length === 0" class="text-center py-8 text-muted-foreground">
                        <Users class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>Belum ada detail tahap yang dikonfigurasi.</p>
                    </div>

                    <!-- Phase Details List -->
                    <div v-else class="space-y-6">
                        <div v-for="(detail, index) in sortedPhaseDetails" :key="detail.id"
                            class="border rounded-lg p-4 bg-card">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        Urutan {{ detail.order }}
                                    </Badge>
                                    <Badge v-if="detail.needs_review" class="text-xs">
                                        Perlu Review
                                    </Badge>
                                    <Badge class="text-xs">
                                        {{ detail.phase_type.name }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3 mb-4">
                                <!-- Form Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <FileText class="h-4 w-4 text-muted-foreground" />
                                        Formulir
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
                                        Program Studi
                                    </div>
                                    <div class="text-sm text-muted-foreground pl-6">
                                        <p>{{ detail.form_access_control.study_program.name }}</p>
                                        <p class="text-xs opacity-75">
                                            {{ detail.form_access_control.study_program.faculty.name }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Evaluation Forms for this Detail -->
                            <div v-if="getEvaluationFormsForDetail(detail).length > 0"
                                class="mt-4 pt-4 border-t bg-muted/30 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <ClipboardList class="h-4 w-4" />
                                        Formulir Evaluasi ({{ getEvaluationFormsForDetail(detail).length }})
                                    </div>
                                    <Link
                                        :href="route('admin.form-phases.evaluation-forms', { formPhase: formPhase.id, detail_id: detail.id })">
                                    <Button size="sm" variant="outline">
                                        <Settings class="h-4 w-4 mr-2" />
                                        Kelola Formulir Evaluasi
                                    </Button>
                                    </Link>
                                </div>

                                <div class="space-y-2">
                                    <div v-for="form in getEvaluationFormsForDetail(detail)" :key="form.id"
                                        class="flex items-center justify-between p-2 bg-background rounded border">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium">{{ form.title }}</span>
                                                <Badge :variant="form.is_required ? 'destructive' : 'secondary'"
                                                    class="text-xs">
                                                    {{ form.is_required ? 'Wajib' : 'Opsional' }}
                                                </Badge>
                                            </div>
                                            <p v-if="form.description" class="text-xs text-muted-foreground mt-1">
                                                {{ form.description }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-muted-foreground">
                                                    {{ form.fields_count }} isian
                                                </span>
                                                <span v-if="form.required_fields_count > 0"
                                                    class="text-xs text-muted-foreground flex items-center gap-1">
                                                    <Star class="h-3 w-3" />
                                                    {{ form.required_fields_count }} wajib
                                                </span>
                                                <Badge :variant="form.is_active ? 'default' : 'secondary'"
                                                    class="text-xs">
                                                    {{ form.is_active ? 'Aktif' : 'Tidak Aktif' }}
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
                            </div>

                            <!-- No Evaluation Forms -->
                            <div v-else class="mt-4 pt-4 border-t">
                                <div class="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                                    <span class="text-sm text-muted-foreground">Belum ada formulir evaluasi yang dikonfigurasi</span>
                                    <Link
                                        :href="route('admin.form-phases.evaluation-forms', { formPhase: formPhase.id, detail_id: detail.id })">
                                    <Button size="sm" variant="outline">
                                        <Plus class="h-4 w-4 mr-2" />
                                        Tambah Formulir Evaluasi
                                    </Button>
                                    </Link>
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
                                    Total Urutan
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
                                    {{ getTotalEvaluationFormsCount }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Formulir Evaluasi
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
                                    {{ getRequiredEvaluationFormsCount }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Formulir Wajib
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
                                    Formulir Unik
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
