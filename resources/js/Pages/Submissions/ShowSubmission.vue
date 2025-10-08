<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Label } from "@/Components/ui/label";
import { Separator } from "@/Components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import {
    ArrowLeft,
    FileText,
    User,
    CheckCircle,
    AlertCircle,
    Download,
    Clock,
    Mail,
    MessageSquare
} from "lucide-vue-next";
import { getSubmissionStatusInfo } from "@/Utils/getSubmissionStatusInfo";
import { SubmissionStatus } from "@/Constants/SubmissionStatus";
import ReviewSystem from "@/Components/ReviewSystem.vue";

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

interface ReviewSummary {
    id: number;
    reviewer_id: number | null;
    status: 'open' | 'resolved' | 'closed';
    summary_notes: string | null;
    created_at: string;
    updated_at: string;
    reviewer?: {
        user: {
            id: number;
            name: string;
            email: string;
        };
        reviewer_role: {
            name: string;
        };
    };
    attachments: Array<{
        id: number;
        file_path: string;
    }>;
}

interface ReviewComment {
    id: number;
    review_summary_id: number;
    parent_comment_id: number | null;
    user_id: number | null;
    reviewer_id: number | null;
    comment_text: string;
    created_at: string;
    user?: {
        id: number;
        name: string;
    };
    reviewer?: {
        user: {
            id: number;
            name: string;
        };
    };
    attachments: Array<{
        id: number;
        file_path: string;
    }>;
    replies: ReviewComment[];
}

interface Reviewer {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface AssignedReviewer {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    reviewer_role: {
        id: number;
        name: string;
    };
}

interface FormSubmission {
    id: number;
    is_submitted: boolean;
    status: SubmissionStatus;
    created_at: string;
    updated_at: string;
    form: Form;
    submitted_by: SubmittedBy;
    review_summaries?: ReviewSummary[];
    review_comments?: ReviewComment[];
    assigned_reviewers?: AssignedReviewer[];
}

interface Props {
    submission: FormSubmission;
    responses: Record<number, string>;
    reviewStats?: {
        total_reviewers: number;
        open_reviews: number;
        resolved_reviews: number;
        closed_reviews: number;
        total_comments: number;
    };
    availableReviewers?: Reviewer[];
    canAssignReviewers?: boolean;
    canReview?: boolean;
    canCreateThread?: boolean;
    hasPendingEvaluations?: boolean;
    pendingEvaluationsCount?: number;
    userRole: "admin" | "submitter" | "reviewer" | "user";
    error?: string;
}

const props = defineProps<Props>();

// Default values
const reviewStats = props.reviewStats || {
    total_reviewers: 0,
    open_reviews: 0,
    resolved_reviews: 0,
    closed_reviews: 0,
    total_comments: 0
};

const availableReviewers = props.availableReviewers || [];
const canAssignReviewers = props.canAssignReviewers || false;
const canReview = props.canReview || false;
const canCreateThread = props.canCreateThread || false;
const hasPendingEvaluations = props.hasPendingEvaluations || false;
const pendingEvaluationsCount = props.pendingEvaluationsCount || 0;
const userRole = props.userRole || 'admin';
const reviewSummaries = props.submission.review_summaries || [];
const reviewComments = props.submission.review_comments || [];
const assignedReviewers = props.submission.assigned_reviewers || [];

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
        case 'textarea':
        case 'text':
        default:
            return value;
    }
};

const isFileField = (fieldType: string) => fieldType.toLowerCase() === 'file';
const isUrlField = (fieldType: string) => fieldType.toLowerCase() === 'url';
const isEmailField = (fieldType: string) => fieldType.toLowerCase() === 'email';

const goBack = () => {
    window.history.back();
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
            <!-- Error Alert -->
            <Card v-if="error" class="border-orange-200 bg-orange-50">
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2 text-orange-800">
                        <AlertCircle class="h-4 w-4" />
                        <span class="text-sm">{{ error }}</span>
                    </div>
                </CardContent>
            </Card>

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
                                <component :is="getSubmissionStatusInfo(submission.status).icon" class="h-5 w-5" />
                                Submission Status
                            </CardTitle>
                            <p class="text-muted-foreground mt-1">
                                {{ getSubmissionStatusInfo(submission.status).description }}
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
                            <p class="font-medium text-green-600">{{ formatDateTime(submission.updated_at) }}</p>
                        </div>
                        <div v-if="reviewStats.total_reviewers > 0">
                            <Label class="text-sm font-medium text-muted-foreground">Review Progress</Label>
                            <p class="font-medium text-blue-600">
                                {{ reviewStats.resolved_reviews }}/{{ reviewStats.total_reviewers }} Completed
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Main Content Tabs -->
            <Tabs default-value="submission" class="space-y-4">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="submission" class="flex items-center gap-2">
                        <FileText class="h-4 w-4" />
                        Submission Details
                    </TabsTrigger>
                    <TabsTrigger value="review" class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4" />
                        Review System
                        <Badge v-if="reviewStats.total_comments > 0" variant="secondary" class="ml-1">
                            {{ reviewStats.total_comments }}
                        </Badge>
                    </TabsTrigger>
                </TabsList>

                <!-- Submission Details Tab -->
                <TabsContent value="submission" class="space-y-6">
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
                                            <span class="font-medium">{{ renderFieldValue(field, responses[field.id])
                                            }}</span>
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
                </TabsContent>

                <!-- Review System Tab -->
                <TabsContent value="review">
                    <ReviewSystem :submission-id="submission.id" :review-summaries="reviewSummaries"
                        :review-comments="reviewComments" :available-reviewers="availableReviewers"
                        :can-assign-reviewers="canAssignReviewers" :can-review="canReview"
                        :can-create-thread="canCreateThread" :has-pending-evaluations="hasPendingEvaluations"
                        :pending-evaluations-count="pendingEvaluationsCount" :user-role="userRole"
                        :assigned-reviewers="assignedReviewers" :review-stats="reviewStats" />
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
