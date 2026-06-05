<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Label } from '@/Components/ui/label'
import { Separator } from '@/Components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import {
    ArrowLeft,
    FileText,
    CheckCircle,
    AlertCircle,
    Download,
    Edit,
    Clock,
    MessageSquare,
    User,
    Mail,
} from 'lucide-vue-next'
import { SubmissionStatus } from '@/Constants/SubmissionStatus'
import { getSubmissionStatusInfo } from '@/Utils/getSubmissionStatusInfo'
import ReviewSystem from '@/Components/ReviewSystem.vue'

interface FieldType {
    name: string
}

interface FormFieldOption {
    id: number
    label: string
}

interface FormField {
    id: number
    label: string
    is_required: boolean
    order: number
    field_type: FieldType
    form_field_options: FormFieldOption[]
}

interface Form {
    id: number
    title: string
    description: string
    form_fields: FormField[]
}

interface ReviewerFormAssignment {
    id: number
    is_required: boolean
    due_date?: string
    review_evaluation_form: {
        id: number
        title: string
        description?: string
    }
    review_form_response?: {
        id: number
        status: string
        submitted_at?: string
    }
}

interface AssignedReviewer {
    id: number
    user: {
        id: number
        name: string
        email: string
    }
    reviewer_role: {
        id: number
        name: string
    }
}

interface EvaluationRequirements {
    required: boolean
    has_forms: boolean
    total_forms?: number
    required_forms?: number
    message: string
}

interface FormSubmission {
    id: number
    is_submitted: boolean
    status: SubmissionStatus
    submitted_at: string | null
    created_at: string
    updated_at: string
    form: Form
    reviewer_form_assignments?: ReviewerFormAssignment[]
    submission_reviewers?: any[]
    review_summaries?: any[]
    submitted_by?: {
        id: number
        name: string
        email: string
    }
}

interface Props {
    submission: FormSubmission
    responses: Record<number, string>
    reviewComments?: any[]
    reviewStats?: {
        total_reviewers: number
        open_reviews: number
        resolved_reviews: number
        closed_reviews: number
        total_comments: number
    }
    canCreateThread?: boolean
    canReview?: boolean
    userRole: 'submitter' | 'reviewer' | 'user'
    isOwnSubmission: boolean
    assignedReviewers?: AssignedReviewer[]
    reviewerFormAssignments?: ReviewerFormAssignment[]
    hasReviewEvaluationForms?: boolean
    evaluationRequirements?: EvaluationRequirements
    hasPendingEvaluations?: boolean
    pendingEvaluationsCount?: number
}

const props = defineProps<Props>()

const reviewStats = props.reviewStats || {
    total_reviewers: 0,
    open_reviews: 0,
    resolved_reviews: 0,
    closed_reviews: 0,
    total_comments: 0,
}

const reviewComments = props.reviewComments || []
const reviewSummaries = props.submission.review_summaries || []
const reviewerAssignments = props.submission.reviewer_form_assignments || []
const assignedReviewers = props.assignedReviewers || []
const reviewerFormAssignments = props.reviewerFormAssignments || []
const hasReviewEvaluationForms = props.hasReviewEvaluationForms || false
const evaluationRequirements = props.evaluationRequirements || {
    required: false,
    has_forms: false,
    message: '',
}

const canReview = computed(() => props.canReview || false)
const isReviewer = computed(() => props.userRole === 'reviewer')
const isSubmitter = computed(() => props.isOwnSubmission)

const hasPendingEvaluations = computed(() => {
    if (props.hasPendingEvaluations !== undefined) {
        return props.hasPendingEvaluations
    }

    if (!hasReviewEvaluationForms) return false

    return reviewerAssignments.some(
        (assignment) =>
            !assignment.review_form_response ||
            assignment.review_form_response.status !== 'submitted'
    )
})

const pendingEvaluationsCount = computed(() => {
    if (props.pendingEvaluationsCount !== undefined) {
        return props.pendingEvaluationsCount
    }

    if (!hasReviewEvaluationForms) return 0

    return reviewerAssignments.filter(
        (assignment) =>
            !assignment.review_form_response ||
            assignment.review_form_response.status !== 'submitted'
    ).length
})

// const completedEvaluationsCount = computed(() => {
//     return reviewerAssignments.filter(
//         (assignment) => assignment.review_form_response?.status === 'submitted'
//     ).length
// })

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const renderFieldValue = (field: FormField, value: string) => {
    if (!value) return '-'

    switch (field.field_type.name.toLowerCase()) {
        case 'select':
        case 'radio':
            const option = field.form_field_options.find((opt) => opt.id.toString() === value)
            return option ? option.label : value
        case 'checkbox':
            return value === '1' || value === 'true' ? 'Yes' : 'No'
        case 'date':
            try {
                return new Date(value).toLocaleDateString('id-ID')
            } catch {
                return value
            }
        case 'textarea':
        case 'text':
        default:
            return value
    }
}

const isFileField = (fieldType: string) => fieldType.toLowerCase() === 'file'
const isUrlField = (fieldType: string) => fieldType.toLowerCase() === 'url'
const isEmailField = (fieldType: string) => fieldType.toLowerCase() === 'email'

const goBack = () => {
    window.history.back()
}

// const getAssignmentStatusInfo = (assignment: ReviewerFormAssignment) => {
//     if (!assignment.review_form_response) {
//         return {
//             variant: 'secondary' as const,
//             text: 'Not Started',
//             icon: Clock,
//         }
//     }

//     switch (assignment.review_form_response.status) {
//         case 'submitted':
//             return {
//                 variant: 'default' as const,
//                 text: 'Completed',
//                 icon: CheckCircle,
//             }
//         case 'draft':
//             return {
//                 variant: 'outline' as const,
//                 text: 'In Progress',
//                 icon: Clock,
//             }
//         default:
//             return {
//                 variant: 'secondary' as const,
//                 text: 'Unknown',
//                 icon: AlertCircle,
//             }
//     }
// }

const mappedAssignedReviewers = computed(() =>
    assignedReviewers.map((r) => ({
        id: r.id,
        user_id: r.user.id,
        name: r.user.name,
        role: r.reviewer_role.name,
    }))
)
</script>

<template>
    <Head :title="`${submission.form.title} - Detail Pengajuan`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ submission.form.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ isSubmitter ? 'Pengajuan Anda' : 'Detail Pengajuan' }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Submitter Information (for reviewers) -->
            <Card v-if="isReviewer && submission.submitted_by">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <User class="h-5 w-5" />
                        Diajukan Oleh
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Nama</Label>
                            <p class="font-medium flex items-center gap-2">
                                <User class="h-4 w-4" />
                                {{ submission.submitted_by.name }}
                            </p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                            <p class="font-medium">
                                <a
                                    :href="`mailto:${submission.submitted_by.email}`"
                                    class="text-blue-600 hover:underline flex items-center gap-2"
                                >
                                    <Mail class="h-4 w-4" />
                                    {{ submission.submitted_by.email }}
                                </a>
                            </p>
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
                                <component
                                    :is="getSubmissionStatusInfo(submission.status).icon"
                                    class="h-5 w-5"
                                />
                                Status Pengajuan
                            </CardTitle>
                            <p class="text-muted-foreground mt-1">
                                {{ getSubmissionStatusInfo(submission.status).description }}
                            </p>
                        </div>
                        <Badge
                            :variant="getSubmissionStatusInfo(submission.status).variant"
                            class="text-sm"
                        >
                            {{ getSubmissionStatusInfo(submission.status).text }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground"
                                >Dibuat Pada</Label
                            >
                            <p class="font-medium flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                {{ formatDateTime(submission.created_at) }}
                            </p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground"
                                >Terakhir Diperbarui</Label
                            >
                            <p class="font-medium">
                                {{ formatDateTime(submission.updated_at) }}
                            </p>
                        </div>
                        <div v-if="submission.submitted_at">
                            <Label class="text-sm font-medium text-muted-foreground"
                                >Diajukan Pada</Label
                            >
                            <p class="font-medium text-green-600">
                                {{ formatDateTime(submission.submitted_at) }}
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
                        Detail Pengajuan
                    </TabsTrigger>
                    <TabsTrigger value="review" class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4" />
                        Ulasan & Diskusi
                        <Badge
                            v-if="reviewStats.total_comments > 0"
                            variant="secondary"
                            class="ml-1"
                        >
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
                                Informasi Formulir
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div>
                                <h3 class="font-semibold text-lg">
                                    {{ submission.form.title }}
                                </h3>
                                <p
                                    v-if="submission.form.description"
                                    class="text-muted-foreground mt-1"
                                >
                                    {{ submission.form.description }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Form Responses -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Respon Formulir</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-6">
                                <div
                                    v-for="field in submission.form.form_fields"
                                    :key="field.id"
                                    class="space-y-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <Label class="font-medium">{{ field.label }}</Label>
                                        <Badge
                                            v-if="field.is_required"
                                            variant="destructive"
                                            class="text-xs"
                                        >
                                            Wajib
                                        </Badge>
                                    </div>

                                    <div class="pl-4 border-l-2 border-muted">
                                        <!-- File Field -->
                                        <div
                                            v-if="
                                                isFileField(field.field_type.name) &&
                                                responses[field.id]
                                            "
                                            class="flex items-center gap-2"
                                        >
                                            <span class="font-medium">{{
                                                renderFieldValue(field, responses[field.id])
                                            }}</span>
                                            <Button size="sm" variant="outline">
                                                <Download class="h-4 w-4 mr-1" />
                                                Download
                                            </Button>
                                        </div>

                                        <!-- URL Field -->
                                        <a
                                            v-else-if="
                                                isUrlField(field.field_type.name) &&
                                                responses[field.id]
                                            "
                                            :href="responses[field.id]"
                                            target="_blank"
                                            class="text-blue-600 hover:underline font-medium"
                                        >
                                            {{ renderFieldValue(field, responses[field.id]) }}
                                        </a>

                                        <!-- Email Field -->
                                        <a
                                            v-else-if="
                                                isEmailField(field.field_type.name) &&
                                                responses[field.id]
                                            "
                                            :href="`mailto:${responses[field.id]}`"
                                            class="text-blue-600 hover:underline font-medium"
                                        >
                                            {{ renderFieldValue(field, responses[field.id]) }}
                                        </a>

                                        <!-- Textarea Field -->
                                        <div
                                            v-else-if="
                                                field.field_type.name.toLowerCase() ===
                                                    'textarea' && responses[field.id]
                                            "
                                            class="whitespace-pre-wrap bg-muted/50 p-3 rounded-lg"
                                        >
                                            {{ renderFieldValue(field, responses[field.id]) }}
                                        </div>

                                        <!-- Other Fields -->
                                        <span v-else class="font-medium">
                                            {{ renderFieldValue(field, responses[field.id]) }}
                                        </span>
                                    </div>

                                    <Separator
                                        v-if="
                                            field !==
                                            submission.form.form_fields[
                                                submission.form.form_fields.length - 1
                                            ]
                                        "
                                        class="mt-4"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Edit Action (for submitter on draft) -->
                    <Card v-if="isSubmitter && !submission.is_submitted">
                        <CardContent class="pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Lanjutkan Mengedit</h4>
                                    <p class="text-sm text-muted-foreground">
                                        Formulir ini masih dalam mode draft. Anda dapat melanjutkan
                                        mengedit dan mengirimkan saat siap.
                                    </p>
                                </div>
                                <Button>
                                    <Edit class="h-4 w-4 mr-2" />
                                    Lanjutkan Mengedit
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Review & Discussion Tab -->
                <TabsContent value="review">
                    <ReviewSystem
                        :submission-id="submission.id"
                        :review-summaries="reviewSummaries"
                        :review-comments="reviewComments"
                        :can-create-thread="!hasPendingEvaluations && canReview"
                        :can-review="canReview"
                        :user-role="userRole"
                        :review-stats="reviewStats"
                        :has-pending-evaluations="hasPendingEvaluations"
                        :pending-evaluations-count="pendingEvaluationsCount"
                        :assigned-reviewers="mappedAssignedReviewers"
                        :has-review-evaluation-forms="hasReviewEvaluationForms"
                        :evaluation-requirements="evaluationRequirements"
                        :reviewer-form-assignments="reviewerFormAssignments"
                        :submission-status="submission.status"
                    />
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
