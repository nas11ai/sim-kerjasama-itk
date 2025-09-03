<!-- resources/js/Pages/Admin/Submissions/SubmissionDetail.vue -->
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
    User,
    CheckCircle,
    AlertCircle,
    Download,
    Clock,
    Building2,
    Mail,
    GraduationCap
} from "lucide-vue-next";

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

interface SubmittedBy {
    id: number;
    name: string;
    email: string;
}

interface FormSubmission {
    id: number;
    is_submitted: boolean;
    can_proceed: boolean;
    created_at: string;
    updated_at: string;
    form: Form;
    submitted_by: SubmittedBy;
}

interface Props {
    submission: FormSubmission;
    responses: Record<number, string>;
}

const props = defineProps<Props>();

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

const getStatusInfo = (): {
    variant: "default" | "destructive" | "outline" | "secondary";
    text: string;
    icon: any;
    description: string;
} => {
    if (props.submission.can_proceed) {
        return {
            variant: 'default',
            text: 'Approved',
            icon: CheckCircle,
            description: 'This submission has been approved and user can proceed.'
        };
    }
    return {
        variant: 'secondary',
        text: 'Under Review',
        icon: AlertCircle,
        description: 'This submission is currently under review.'
    };
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
            return value;

        case 'url':
        case 'email':
        case 'phone':
        case 'number':
        case 'text':
        case 'textarea':
        default:
            return value;
    }
};

const isFileField = (fieldType: string) => fieldType.toLowerCase() === 'file';
const isUrlField = (fieldType: string) => fieldType.toLowerCase() === 'url';
const isEmailField = (fieldType: string) => fieldType.toLowerCase() === 'email';

const statusInfo = getStatusInfo();

const goBack = () => {
    router.visit(window.history.length > 1 ? 'javascript:history.back()' : '/admin/submissions');
};
</script>

<template>

    <Head :title="`${submission.form.title} - Admin Submission Detail`" />

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
                        Submission by {{ submission.submitted_by.name }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- User Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <User class="h-5 w-5" />
                        Submitted By
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Name</Label>
                                <p class="font-medium flex items-center gap-2">
                                    <User class="h-4 w-4" />
                                    {{ submission.submitted_by.name }}
                                </p>
                            </div>

                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                <p class="font-medium">
                                    <a :href="`mailto:${submission.submitted_by.email}`"
                                        class="text-blue-600 hover:underline flex items-center gap-2">
                                        <Mail class="h-4 w-4" />
                                        {{ submission.submitted_by.email }}
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Status -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <component :is="statusInfo.icon" class="h-5 w-5" />
                                Submission Status
                            </CardTitle>
                            <p class="text-muted-foreground mt-1">{{ statusInfo.description }}</p>
                        </div>
                        <Badge :variant="statusInfo.variant" class="text-sm">
                            {{ statusInfo.text }}
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
                            <p class="font-medium text-green-600">{{ formatDateTime(submission.updated_at) }}</p>
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
                                <Badge variant="outline" class="text-xs capitalize">
                                    {{ field.field_type.name }}
                                </Badge>
                            </div>

                            <div class="pl-4 border-l-2 border-muted">
                                <!-- File Field -->
                                <div v-if="isFileField(field.field_type.name) && responses[field.id]"
                                    class="flex items-center gap-2">
                                    <span class="font-medium">{{ renderFieldValue(field, responses[field.id]) }}</span>
                                    <Button size="sm" variant="outline">
                                        <Download class="h-4 w-4 mr-1" />
                                        Download File
                                    </Button>
                                </div>

                                <!-- URL Field -->
                                <a v-else-if="isUrlField(field.field_type.name) && responses[field.id]"
                                    :href="responses[field.id]" target="_blank"
                                    class="text-blue-600 hover:underline font-medium inline-flex items-center gap-1">
                                    {{ renderFieldValue(field, responses[field.id]) }}
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>

                                <!-- Email Field -->
                                <a v-else-if="isEmailField(field.field_type.name) && responses[field.id]"
                                    :href="`mailto:${responses[field.id]}`"
                                    class="text-blue-600 hover:underline font-medium">
                                    {{ renderFieldValue(field, responses[field.id]) }}
                                </a>

                                <!-- Textarea Field -->
                                <div v-else-if="field.field_type.name.toLowerCase() === 'textarea' && responses[field.id]"
                                    class="whitespace-pre-wrap bg-muted/50 p-3 rounded-lg max-h-64 overflow-y-auto">
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

            <!-- Admin Actions (if needed in the future) -->
            <Card v-if="!submission.can_proceed">
                <CardHeader>
                    <CardTitle>Review Actions</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Review Status</h4>
                            <p class="text-sm text-muted-foreground">
                                This submission is currently under review. Future functionality will allow
                                approving/rejecting
                                submissions here.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Future: Add approve/reject buttons here -->
                            <Badge variant="secondary">Review System Coming Soon</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
