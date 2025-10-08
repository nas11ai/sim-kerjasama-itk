<!-- resources/js/Pages/User/Submissions/ShowSubmission.vue -->
<script setup lang="ts">
import { computed } from "vue";
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
    CheckCircle,
    AlertCircle,
    Download,
    Edit,
    Clock,
    MessageSquare,
    ClipboardList,
    AlertTriangle
} from "lucide-vue-next";
import { SubmissionStatus } from "@/Constants/SubmissionStatus";
import { getSubmissionStatusInfo } from "@/Utils/getSubmissionStatusInfo";
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

interface ReviewerFormAssignment {
    id: number;
    is_required: boolean;
    due_date?: string;
    review_evaluation_form: {
        id: number;
        title: string;
        description?: string;
    };
    review_form_response?: {
        id: number;
        status: string;
        submitted_at?: string;
    };
}

interface FormSubmission {
    id: number;
    is_submitted: boolean;
    status: SubmissionStatus;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    form: Form;
    reviewer_form_assignments?: ReviewerFormAssignment[];
    submission_reviewers?: any[];
    review_summaries?: any[];
}

interface Props {
    submission: FormSubmission;
    responses: Record<number, string>;
    reviewComments?: any[];
    reviewStats?: {
        total_reviewers: number;
        open_reviews: number;
        resolved_reviews: number;
        closed_reviews: number;
        total_comments: number;
    };
    canCreateThread?: boolean;
    canReview?: boolean;
    userRole: "submitter" | "reviewer" | "user";
    isOwnSubmission: boolean;
}

const props = defineProps<Props>();

const reviewStats = props.reviewStats || {
    total_reviewers: 0,
    open_reviews: 0,
    resolved_reviews: 0,
    closed_reviews: 0,
    total_comments: 0
};

const reviewComments = props.reviewComments || [];
const reviewSummaries = props.submission.review_summaries || [];
const reviewerAssignments = props.submission.reviewer_form_assignments || [];

const canReview = computed(() => props.canReview || false);
const isReviewer = computed(() => props.userRole === 'reviewer');
const isSubmitter = computed(() => props.isOwnSubmission);

// Check if reviewer has pending evaluations
const hasPendingEvaluations = computed(() => {
    return reviewerAssignments.some(assignment =>
        !assignment.review_form_response ||
        assignment.review_form_response.status !== 'submitted'
    );
});

const pendingEvaluationsCount = computed(() => {
    return reviewerAssignments.filter(assignment =>
        !assignment.review_form_response ||
        assignment.review_form_response.status !== 'submitted'
    ).length;
});

const completedEvaluationsCount = computed(() => {
    return reviewerAssignments.filter(assignment =>
        assignment.review_form_response?.status === 'submitted'
    ).length;
});

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

const getAssignmentStatusInfo = (assignment: ReviewerFormAssignment) => {
    if (!assignment.review_form_response) {
        return {
            variant: 'secondary' as const,
            text: 'Not Started',
            icon: Clock
        };
    }

    switch (assignment.review_form_response.status) {
        case 'submitted':
            return {
                variant: 'default' as const,
                text: 'Completed',
                icon: CheckCircle
            };
        case 'draft':
            return {
                variant: 'outline' as const,
                text: 'In Progress',
                icon: Clock
            };
        default:
            return {
                variant: 'secondary' as const,
                text: 'Unknown',
                icon: AlertCircle
            };
    }
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
                        {{ isSubmitter ? 'Your Submission' : 'Submission Detail' }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
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
                            <p class="font-medium">{{ formatDateTime(submission.updated_at) }}</p>
                        </div>
                        <div v-if="submission.submitted_at">
                            <Label class="text-sm font-medium text-muted-foreground">Submitted</Label>
                            <p class="font-medium text-green-600">{{ formatDateTime(submission.submitted_at) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Reviewer Evaluation Forms (for reviewers only) -->
            <Card v-if="isReviewer && reviewerAssignments.length > 0">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <ClipboardList class="h-5 w-5" />
                                Your Evaluation Forms
                            </CardTitle>
                            <p class="text-muted-foreground text-sm mt-1">
                                Complete evaluation forms before participating in discussions
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="default">
                                {{ completedEvaluationsCount }}/{{ reviewerAssignments.length }} Completed
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Warning for pending evaluations -->
                    <Card v-if="hasPendingEvaluations" class="border-amber-200 bg-amber-50 mb-4">
                        <CardContent class="p-4">
                            <div class="flex items-start gap-3">
                                <AlertTriangle class="h-5 w-5 text-amber-600 mt-0.5" />
                                <div>
                                    <h4 class="text-amber-800 font-medium text-sm mb-1">
                                        Evaluations Required
                                    </h4>
                                    <p class="text-amber-700 text-sm">
                                        You have {{ pendingEvaluationsCount }} pending evaluation form(s).
                                        Please complete them before creating review threads.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="space-y-3">
                        <div v-for="assignment in reviewerAssignments" :key="assignment.id"
                            class="border rounded-lg p-4 hover:bg-muted/50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h4 class="font-medium">{{ assignment.review_evaluation_form.title }}</h4>
                                        <Badge :variant="assignment.is_required ? 'default' : 'outline'"
                                            class="text-xs">
                                            {{ assignment.is_required ? 'Required' : 'Optional' }}
                                        </Badge>
                                        <Badge :variant="getAssignmentStatusInfo(assignment).variant" class="text-xs">
                                            <component :is="getAssignmentStatusInfo(assignment).icon"
                                                class="h-3 w-3 mr-1" />
                                            {{ getAssignmentStatusInfo(assignment).text }}
                                        </Badge>
                                    </div>

                                    <p v-if="assignment.review_evaluation_form.description"
                                        class="text-sm text-muted-foreground mb-2">
                                        {{ assignment.review_evaluation_form.description }}
                                    </p>

                                    <div v-if="assignment.due_date"
                                        class="text-xs text-muted-foreground flex items-center gap-1">
                                        <Clock class="h-3 w-3" />
                                        Due: {{ formatDateTime(assignment.due_date) }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Start/Continue Button -->
                                    <Button
                                        v-if="!assignment.review_form_response || assignment.review_form_response.status === 'draft'"
                                        size="sm" as-child>
                                        <Link :href="route('reviewer.evaluation-form.show', assignment.id)">
                                        <FileText class="h-4 w-4 mr-1" />
                                        {{ assignment.review_form_response ? 'Continue' : 'Start' }}
                                        </Link>
                                    </Button>

                                    <!-- View Submitted Button -->
                                    <Button v-if="assignment.review_form_response?.status === 'submitted'" size="sm"
                                        variant="outline" as-child>
                                        <Link :href="route('reviewer.evaluation-form.submitted', assignment.id)">
                                        <CheckCircle class="h-4 w-4 mr-1" />
                                        View
                                        </Link>
                                    </Button>
                                </div>
                            </div>
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
                        Review & Discussion
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

                    <!-- Edit Action (for submitter on draft) -->
                    <Card v-if="isSubmitter && !submission.is_submitted">
                        <CardContent class="pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Continue Editing</h4>
                                    <p class="text-sm text-muted-foreground">
                                        This form is still in draft mode. You can continue editing and submit when
                                        ready.
                                    </p>
                                </div>
                                <Button>
                                    <Edit class="h-4 w-4 mr-2" />
                                    Continue Editing
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Review & Discussion Tab -->
                <TabsContent value="review">
                    <ReviewSystem :submission-id="submission.id" :review-summaries="reviewSummaries"
                        :review-comments="reviewComments" :can-create-thread="!hasPendingEvaluations && canReview"
                        :can-review="canReview" :user-role="userRole" :review-stats="reviewStats"
                        :has-pending-evaluations="hasPendingEvaluations"
                        :pending-evaluations-count="pendingEvaluationsCount" :available-reviewers="[]"
                        :assigned-reviewers="[]" :can-assign-reviewers="false" />
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
