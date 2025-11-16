<!-- resources/js/Pages/Reviewer/EvaluationForm/Submitted.vue -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    ArrowLeft,
    CheckCircle,
    Calendar,
    User,
    FileText,
    Download,
    Clock
} from "lucide-vue-next";

interface FieldType {
    id: number;
    name: string;
}

interface FieldOption {
    id: number;
    label: string;
    value?: string;
}

interface ReviewFormField {
    id: number;
    label: string;
    description?: string;
    is_required: boolean;
    field_type: FieldType;
    review_form_field_options: FieldOption[];
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
    review_form_fields: ReviewFormField[];
}

interface FormSubmission {
    id: number;
    form: {
        id: number;
        title: string;
    };
    submitted_by: {
        id: number;
        name: string;
    };
}

interface ReviewFormResponse {
    id: number;
    status: string;
    submitted_at?: string;
    final_notes?: string;
}

interface ReviewerFormAssignment {
    id: number;
    is_required: boolean;
    due_date?: string;
    review_evaluation_form: ReviewEvaluationForm;
    submission_reviewer: {
        form_submission: FormSubmission;
    };
    review_form_response: ReviewFormResponse;
}

interface Props {
    assignment: ReviewerFormAssignment;
    response: ReviewFormResponse;
    formattedResponses: Record<string, {
        value: string;
        formatted_value: string;
    }>;
}

const props = defineProps<Props>();

const formatDate = (dateString?: string): string => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getFieldDisplayValue = (field: ReviewFormField): string => {
    const response = props.formattedResponses[field.id];
    if (!response || !response.formatted_value) {
        return 'Tidak ada respons yang diberikan';
    }
    return response.formatted_value;
};

const hasResponse = (field: ReviewFormField): boolean => {
    return !!props.formattedResponses[field.id]?.value?.trim();
};


const downloadSummary = () => {
    window.location.href = route('reviewer.evaluation-form.download-summary', props.assignment.id);
};

const getResponseClass = (field: ReviewFormField): string => {
    if (!hasResponse(field)) {
        return 'text-muted-foreground italic';
    }
    return 'text-foreground';
};
</script>

<template>

    <Head :title="`Dikirim: ${assignment.review_evaluation_form.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('reviewer.assignments.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ assignment.review_evaluation_form.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Evaluasi untuk: {{ assignment.submission_reviewer.form_submission.form.title }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Submission Status Card -->
            <Card class="border-green-200 bg-green-50">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <CheckCircle class="h-8 w-8 text-green-600" />
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-green-900">
                                    Evaluasi Berhasil Dikirim
                                </h3>
                                <p class="text-sm text-green-700">
                                    Evaluasi Anda telah dikirim dan sekarang menjadi bagian dari proses peninjauan.
                                </p>
                            </div>
                        </div>

                        <Button @click="downloadSummary" variant="outline" size="sm">
                            <Download class="h-4 w-4 mr-2" />
                            Download Ringkasan
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Details -->
            <Card>
                <CardContent class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <User class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm font-medium">Dikirim oleh:</span>
                                <span class="text-sm">{{
                                    assignment.submission_reviewer.form_submission.submitted_by.name
                                }}</span>
                            </div>

                            <div class="flex items-center space-x-2">
                                <FileText class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm font-medium">Formulir:</span>
                                <span class="text-sm">{{ assignment.submission_reviewer.form_submission.form.title
                                }}</span>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Badge :variant="assignment.is_required ? 'default' : 'outline'">
                                    {{ assignment.is_required ? 'Wajib' : 'Opsional' }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm font-medium">Dikirim pada:</span>
                                <span class="text-sm">{{ formatDate(response.submitted_at) }}</span>
                            </div>

                            <div v-if="assignment.due_date" class="flex items-center space-x-2">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm font-medium">Jatuh tempo:</span>
                                <span class="text-sm">{{ formatDate(assignment.due_date) }}</span>
                            </div>

                            <Badge variant="default" class="w-fit">
                                <CheckCircle class="h-3 w-3 mr-1" />
                                Dikirim
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Description -->
            <Card v-if="assignment.review_evaluation_form.description">
                <CardContent class="p-6">
                    <div class="flex items-start space-x-3">
                        <FileText class="h-5 w-5 text-blue-500 mt-0.5" />
                        <div>
                            <h3 class="font-medium mb-2">Instruksi Formulir</h3>
                            <p class="text-muted-foreground">{{ assignment.review_evaluation_form.description }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Submitted Responses -->
            <div class="space-y-4">
                <h3 class="text-lg font-medium">Respon Anda</h3>

                <div v-for="field in assignment.review_evaluation_form.review_form_fields" :key="field.id"
                    class="space-y-4">
                    <Card>
                        <CardContent class="p-6">
                            <div class="space-y-3">
                                <!-- Field Label and Info -->
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-base font-medium">
                                            {{ field.label }}
                                            <span v-if="field.is_required" class="text-red-500 ml-1">*</span>
                                        </h4>
                                        <p v-if="field.description" class="text-sm text-muted-foreground mt-1">
                                            {{ field.description }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <Badge v-if="field.is_required" variant="secondary" size="sm">
                                            Wajib
                                        </Badge>
                                        <Badge variant="outline" size="sm">
                                            {{ field.field_type.name }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Response Display -->
                                <div class="border-l-4 border-blue-200 pl-4 py-2">
                                    <div :class="getResponseClass(field)">
                                        <div v-if="hasResponse(field)" class="space-y-2">
                                            <!-- Multi-line responses -->
                                            <div v-if="field.field_type.name === 'textarea'"
                                                class="whitespace-pre-wrap">{{
                                                    getFieldDisplayValue(field) }}</div>

                                            <!-- Single line responses -->
                                            <div v-else>{{ getFieldDisplayValue(field) }}</div>
                                        </div>
                                        <div v-else>
                                            Tidak ada respon yang diberikan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Additional Notes -->
            <Card v-if="response.final_notes">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <FileText class="h-5 w-5" />
                        <span>Catatan Tambahan</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="border-l-4 border-blue-200 pl-4 py-2">
                        <div class="whitespace-pre-wrap">{{ response.final_notes }}</div>
                    </div>
                </CardContent>
            </Card>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between bg-white border rounded-lg p-4">
                <div class="text-sm text-muted-foreground">
                    Evaluasi ini dikirim pada {{ formatDate(response.submitted_at) }} dan tidak dapat diubah.
                </div>

                <div class="flex items-center space-x-2">
                    <Button @click="downloadSummary" variant="outline">
                        <Download class="h-4 w-4 mr-2" />
                        Download Ringkasan PDF
                    </Button>

                    <Button @click="$inertia.visit(route('reviewer.assignments.index'))">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Kembali ke Penugasan
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
