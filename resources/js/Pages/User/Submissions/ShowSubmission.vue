<!-- resources/js/Pages/Submissions/UserSubmissionDetail.vue -->
<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Label } from "@/Components/ui/label";
import { Separator } from "@/Components/ui/separator";
import {
    ArrowLeft,
    FileText,
    CheckCircle,
    AlertCircle,
    RefreshCw,
    Download,
    Edit,
    Clock
} from "lucide-vue-next";
import { SubmissionStatus } from "@/Constants/SubmissionStatus";
import { getSubmissionStatusInfo } from "@/Utils/getSubmissionStatusInfo";

interface FieldType {
    name: string;
}

interface FormFieldOption {
    id: number;
    label: string;
}

interface FormField {
    id: number;
    label: string;
    is_required: boolean;
    order: number;
    field_type: FieldType;
    form_field_options: FormFieldOption[];
}

interface Form {
    id: number;
    title: string;
    description: string;
    form_fields: FormField[];
}

interface FormSubmission {
    id: number;
    is_submitted: boolean;
    status: SubmissionStatus;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    form: Form;
}

interface Props {
    submission: FormSubmission;
    responses: Record<number, string>;
}

const props = defineProps<Props>();

type BadgeVariant = "default" | "destructive" | "outline" | "secondary" | null | undefined;

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString("id-ID", {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const renderFieldValue = (field: FormField, value: string) => {
    if (!value) return '-';

    switch (field.field_type.name.toLowerCase()) {
        case 'select':
        case 'radio':
            const option = field.form_field_options.find(opt => opt.id.toString() === value);
            return option ? option.label : value;

        case 'checkbox':
            return value === '1' || value === 'true' ? 'Yes' : 'No';

        case 'date':
            try {
                return new Date(value).toLocaleDateString("id-ID");
            } catch {
                return value;
            }

        case 'time':
            return value;

        case 'file':
            return value; // This would typically be a file path

        case 'url':
            return value;

        case 'email':
        case 'phone':
        case 'number':
        case 'text':
        case 'textarea':
        default:
            return value;
    }
};

const isFileField = (fieldType: string) => {
    return fieldType.toLowerCase() === 'file';
};

const isUrlField = (fieldType: string) => {
    return fieldType.toLowerCase() === 'url';
};

const isEmailField = (fieldType: string) => {
    return fieldType.toLowerCase() === 'email';
};

const goBack = () => {
    window.history.back();
};
</script>

<template>

    <Head :title="`${submission.form.title} - Submission Detail`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ submission.form.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Submission Detail
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Submission Status -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <component :is="getSubmissionStatusInfo(submission.status).icon" class="h-5 w-5" />
                                Submission Status
                            </CardTitle>
                            <p class="text-muted-foreground mt-1">{{
                                getSubmissionStatusInfo(submission.status).description }}
                            </p>
                        </div>
                        <Badge :variant="getSubmissionStatusInfo(submission.status).variant" class="text-sm">
                            {{ getSubmissionStatusInfo(submission.status).text }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Created</Label>
                            <p class="font-medium flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                {{ formatDateTime(submission.created_at) }}
                            </p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Last Updated</Label>
                            <p class="font-medium">{{ formatDateTime(submission.updated_at) }}</p>
                        </div>
                        <div v-if="submission.submitted_at">
                            <Label class="text-sm font-medium text-muted-foreground">Submitted</Label>
                            <p class="font-medium text-green-600">{{ formatDateTime(submission.submitted_at) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Form Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div>
                        <h3 class="font-semibold text-lg">{{ submission.form.title }}</h3>
                        <p class="text-muted-foreground mt-1" v-if="submission.form.description">
                            {{ submission.form.description }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Responses -->
            <Card>
                <CardHeader>
                    <CardTitle>Form Responses</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <div v-for="field in submission.form.form_fields" :key="field.id" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Label class="font-medium">{{ field.label }}</Label>
                                <Badge v-if="field.is_required" variant="destructive" class="text-xs">
                                    Required
                                </Badge>
                            </div>

                            <div class="pl-4 border-l-2 border-muted">
                                <!-- File Field -->
                                <div v-if="isFileField(field.field_type.name) && responses[field.id]"
                                    class="flex items-center gap-2">
                                    <span class="font-medium">{{ renderFieldValue(field, responses[field.id]) }}</span>
                                    <Button size="sm" variant="outline">
                                        <Download class="h-4 w-4 mr-1" />
                                        Download
                                    </Button>
                                </div>

                                <!-- URL Field -->
                                <a v-else-if="isUrlField(field.field_type.name) && responses[field.id]"
                                    :href="responses[field.id]" target="_blank"
                                    class="text-blue-600 hover:underline font-medium">
                                    {{ renderFieldValue(field, responses[field.id]) }}
                                </a>

                                <!-- Email Field -->
                                <a v-else-if="isEmailField(field.field_type.name) && responses[field.id]"
                                    :href="`mailto:${responses[field.id]}`"
                                    class="text-blue-600 hover:underline font-medium">
                                    {{ renderFieldValue(field, responses[field.id]) }}
                                </a>

                                <!-- Textarea Field -->
                                <div v-else-if="field.field_type.name.toLowerCase() === 'textarea' && responses[field.id]"
                                    class="whitespace-pre-wrap bg-muted/50 p-3 rounded-lg">
                                    {{ renderFieldValue(field, responses[field.id]) }}
                                </div>

                                <!-- Other Fields -->
                                <span v-else class="font-medium">
                                    {{ renderFieldValue(field, responses[field.id]) }}
                                </span>
                            </div>

                            <Separator
                                v-if="field !== submission.form.form_fields[submission.form.form_fields.length - 1]"
                                class="mt-4" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <Card v-if="!submission.is_submitted">
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Continue Editing</h4>
                            <p class="text-sm text-muted-foreground">
                                This form is still in draft mode. You can continue editing and submit when ready.
                            </p>
                        </div>
                        <Button>
                            <Edit class="h-4 w-4 mr-2" />
                            Continue Editing
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
