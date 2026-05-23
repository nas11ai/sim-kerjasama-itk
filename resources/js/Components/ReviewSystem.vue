<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Avatar, AvatarFallback } from '@/Components/ui/avatar'
import { Separator } from '@/Components/ui/separator'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/Components/ui/dialog'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Input } from '@/Components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible'
import {
    AlertCircle,
    CheckCircle,
    Clock,
    MessageSquare,
    Users,
    Plus,
    ClipboardList,
    FileText,
    Eye,
    Send,
    X,
    Download,
    XCircle,
    Shield,
    User,
    ChevronDown,
    ChevronUp,
    MessageCircle,
    Lock,
    Edit,
    Settings,
} from 'lucide-vue-next'

interface ReviewerFormAssignment {
    id: number | null
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
    } | null
}

interface ReviewSummary {
    id: number
    reviewer_id: number | null
    status: 'open' | 'resolved' | 'closed'
    summary_notes: string | null
    created_at: string
    updated_at: string
    reviewer?: {
        user: {
            id: number
            name: string
            email: string
        }
        reviewer_role: {
            name: string
        }
    }
    attachments: Array<{
        id: number
        file_path: string
    }>
}

interface ReviewComment {
    id: number
    review_summary_id: number
    parent_comment_id: number | null
    user_id: number | null
    reviewer_id: number | null
    comment_text: string
    created_at: string
    user?: {
        id: number
        name: string
    }
    reviewer?: {
        user: {
            id: number
            name: string
        }
    }
    attachments: Array<{
        id: number
        file_path: string
    }>
    replies: ReviewComment[]
}

interface Props {
    submissionId: number
    reviewSummaries: ReviewSummary[]
    reviewComments: ReviewComment[]
    canCreateThread: boolean
    canReview: boolean
    userRole: 'admin' | 'submitter' | 'reviewer' | 'user'
    reviewStats: any
    hasPendingEvaluations: boolean
    pendingEvaluationsCount: number
    hasReviewEvaluationForms: boolean
    evaluationRequirements: {
        required: boolean
        has_forms: boolean
        message: string
    }
    reviewerFormAssignments?: ReviewerFormAssignment[]
    assignedReviewers: Array<{
        id: number
        user_id: number
        name: string
        role: string
    }>
    submissionStatus: string
}

const props = defineProps<Props>()
const page = usePage()

// State
const showThreadDialog = ref(false)
const newThreadNotes = ref('')
const newThreadAttachments = ref<File[]>([])
const newComments = ref<Record<number, string>>({})
const replyingTo = ref<number | null>(null)
const newReply = ref('')
const expandedThreads = ref<Set<number>>(new Set())

// Status update states
const showUpdateReviewStatusDialog = ref(false)
const showUpdateSubmissionStatusDialog = ref(false)
const selectedReviewSummary = ref<ReviewSummary | null>(null)
const selectedReviewStatus = ref('')
const selectedSubmissionStatus = ref('')
const statusUpdateNotes = ref('')

// Computed
const currentUser = computed(() => page.props.auth.user as any)
const isAdmin = computed(() => props.userRole === 'admin')
const isAssignedReviewer = computed(() => props.userRole === 'reviewer' && props.canReview)
const reviewerAssignments = computed(() => props.reviewerFormAssignments || [])

const completedEvaluationsCount = computed(() => {
    return reviewerAssignments.value.filter(
        (assignment) => assignment.review_form_response?.status === 'submitted'
    ).length
})

const totalEvaluationsCount = computed(() => reviewerAssignments.value.length)

// Check if user can update review summary status
const canUpdateReviewStatus = (reviewSummary: ReviewSummary) => {
    if (isAdmin.value) return true

    if (!isAssignedReviewer.value || !reviewSummary.reviewer_id) return false

    const isOwnReview = props.assignedReviewers.some((r) => {
        const match = r.user_id === currentUser.value?.id
        return match
    })

    const result = isOwnReview && !props.hasPendingEvaluations

    return result
}

// Check if user can update submission status
const canUpdateSubmissionStatus = computed(() => {
    if (isAdmin.value) return true
    if (!isAssignedReviewer.value) return false

    // Reviewer can update if they have completed evaluations
    if (props.hasReviewEvaluationForms && props.hasPendingEvaluations) {
        return false
    }

    return true
})

// Review status options
const reviewStatusOptions = [
    { value: 'open', label: 'Terbuka', color: 'green' },
    { value: 'resolved', label: 'Terselesaikan', color: 'blue' },
    { value: 'closed', label: 'Ditutup', color: 'red' },
]

// Submission status options
const submissionStatusOptions = [
    { value: 'pending', label: 'Menunggu Review', color: 'yellow' },
    { value: 'under_review', label: 'Sedang Direview', color: 'blue' },
    { value: 'needs_revision', label: 'Perlu Revisi', color: 'orange' },
    { value: 'approved', label: 'Disetujui', color: 'green' },
    { value: 'rejected', label: 'Ditolak', color: 'red' },
]

// Thread expansion
const isThreadExpanded = (threadId: number) => expandedThreads.value.has(threadId)

const toggleThread = (threadId: number) => {
    if (expandedThreads.value.has(threadId)) {
        expandedThreads.value.delete(threadId)
    } else {
        expandedThreads.value.add(threadId)
    }
}

// Get comments for specific thread
const getThreadComments = (reviewSummaryId: number) => {
    return props.reviewComments.filter((c) => c.review_summary_id === reviewSummaryId)
}

// Check if user can comment on thread
const canCommentOnThread = (status: string) => {
    return status === 'open'
}

// Determine if reviewer can create threads
const canActuallyCreateThread = computed(() => {
    if (isAdmin.value) return true
    if (!isAssignedReviewer.value) return false
    if (!props.hasReviewEvaluationForms) return props.canReview

    const requiredForms = reviewerAssignments.value.filter((a) => a.is_required)
    const completedRequired = requiredForms.filter(
        (a) => a.review_form_response?.status === 'submitted'
    )

    return requiredForms.length > 0 && completedRequired.length === requiredForms.length
})

const threadCreationMessage = computed(() => {
    if (!isAssignedReviewer.value) {
        return 'Anda tidak ditugaskan sebagai reviewer untuk pengajuan ini.'
    }

    if (!props.hasReviewEvaluationForms) {
        return 'Anda dapat membuat thread review untuk pengajuan ini.'
    }

    const requiredForms = reviewerAssignments.value.filter((a) => a.is_required)
    const completedRequired = requiredForms.filter(
        (a) => a.review_form_response?.status === 'submitted'
    )

    if (requiredForms.length === 0) {
        return 'Tidak ada form evaluasi wajib. Anda dapat membuat thread review.'
    }

    if (completedRequired.length < requiredForms.length) {
        const remaining = requiredForms.length - completedRequired.length
        return `Selesaikan ${remaining} form evaluasi wajib terlebih dahulu sebelum membuat thread review.`
    }

    return 'Anda telah menyelesaikan semua evaluasi dan sekarang dapat membuat thread review.'
})

const getAssignmentStatusInfo = (assignment: ReviewerFormAssignment) => {
    if (!assignment.review_form_response) {
        return {
            variant: 'secondary' as const,
            text: 'Not Started',
            icon: Clock,
        }
    }

    switch (assignment.review_form_response.status) {
        case 'submitted':
            return {
                variant: 'default' as const,
                text: 'Completed',
                icon: CheckCircle,
            }
        case 'draft':
            return {
                variant: 'outline' as const,
                text: 'In Progress',
                icon: Clock,
            }
        default:
            return {
                variant: 'secondary' as const,
                text: 'Unknown',
                icon: AlertCircle,
            }
    }
}

type ReviewStatus = ReviewSummary['status']

const statusMap: Record<
    ReviewStatus,
    {
        label: string
        color: string
        icon: any
    }
> = {
    open: {
        label: 'Terbuka',
        color: 'bg-green-100 text-green-800',
        icon: AlertCircle,
    },
    resolved: {
        label: 'Terselesaikan',
        color: 'bg-blue-100 text-blue-800',
        icon: CheckCircle,
    },
    closed: {
        label: 'Ditutup',
        color: 'bg-red-100 text-red-800',
        icon: XCircle,
    },
}

const getStatusInfo = (status: ReviewStatus) => {
    return statusMap[status]
}

const getSubmissionStatusInfo = (status: string) => {
    const option = submissionStatusOptions.find((s) => s.value === status)
    return option || { label: status, color: 'gray' }
}

const formatDate = (dateString?: string) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const startEvaluationForm = (formId: number) => {
    router.post(route('reviewer.evaluation-form.start'), {
        submission_id: props.submissionId,
        review_evaluation_form_id: formId,
    })
}

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files) {
        newThreadAttachments.value = Array.from(target.files)
    }
}

const createThread = () => {
    const formData = new FormData()
    formData.append('summary_notes', newThreadNotes.value)

    newThreadAttachments.value.forEach((file, index) => {
        formData.append(`attachments[${index}]`, file)
    })

    router.post(`/submissions/${props.submissionId}/review-threads`, formData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            showThreadDialog.value = false
            newThreadNotes.value = ''
            newThreadAttachments.value = []
        },
    })
}

const addComment = (reviewSummaryId: number, parentId?: number) => {
    const commentText = parentId ? newReply.value : newComments.value[reviewSummaryId]
    if (!commentText?.trim()) return

    router.post(
        `/review-summaries/${reviewSummaryId}/comments`,
        {
            comment_text: commentText,
            parent_comment_id: parentId || null,
        },
        {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
                if (parentId) {
                    newReply.value = ''
                    replyingTo.value = null
                } else {
                    newComments.value[reviewSummaryId] = ''
                }
            },
        }
    )
}

const getAuthorDisplay = (comment: ReviewComment) => {
    if (comment.reviewer?.user) {
        return {
            name: comment.reviewer.user.name,
            type: 'Reviewer',
            icon: Shield,
            isCurrentUser: comment.reviewer.user.id === currentUser.value?.id,
        }
    } else if (comment.user) {
        return {
            name: comment.user.name,
            type: comment.user.id === currentUser.value?.id ? 'You' : 'Submitter',
            icon: User,
            isCurrentUser: comment.user.id === currentUser.value?.id,
        }
    }
    return { name: 'Unknown', type: 'User', icon: User, isCurrentUser: false }
}

const downloadAttachment = (filePath: string) => {
    window.open(`/review-attachments/download?path=${encodeURIComponent(filePath)}`, '_blank')
}

// Open update review status dialog
const openUpdateReviewStatusDialog = (reviewSummary: ReviewSummary) => {
    selectedReviewSummary.value = reviewSummary
    selectedReviewStatus.value = reviewSummary.status
    statusUpdateNotes.value = reviewSummary.summary_notes || ''
    showUpdateReviewStatusDialog.value = true
}

// Update review summary status
const updateReviewSummaryStatus = () => {
    if (!selectedReviewSummary.value || !selectedReviewStatus.value) return

    router.patch(
        `/review-summaries/${selectedReviewSummary.value.id}/status`,
        {
            status: selectedReviewStatus.value,
            summary_notes: statusUpdateNotes.value || null,
        },
        {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
                showUpdateReviewStatusDialog.value = false
                selectedReviewSummary.value = null
                selectedReviewStatus.value = ''
                statusUpdateNotes.value = ''
            },
        }
    )
}

// Open update submission status dialog
const openUpdateSubmissionStatusDialog = () => {
    selectedSubmissionStatus.value = props.submissionStatus
    showUpdateSubmissionStatusDialog.value = true
}

// Update submission status
const updateSubmissionStatus = () => {
    if (!selectedSubmissionStatus.value) return

    router.patch(
        `/submissions/${props.submissionId}/status`,
        {
            status: selectedSubmissionStatus.value,
        },
        {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
                showUpdateSubmissionStatusDialog.value = false
                selectedSubmissionStatus.value = ''
            },
        }
    )
}
</script>

<template>
    <div class="space-y-6">
        <!-- Submission Status Card -->
        <Card v-if="isAssignedReviewer || isAdmin">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Status Pengajuan
                    </CardTitle>
                    <div class="flex items-center gap-3">
                        <Badge
                            :class="`bg-${getSubmissionStatusInfo(submissionStatus).color}-100 text-${getSubmissionStatusInfo(submissionStatus).color}-800`"
                        >
                            {{ getSubmissionStatusInfo(submissionStatus).label }}
                        </Badge>
                        <Button
                            v-if="canUpdateSubmissionStatus"
                            size="sm"
                            variant="outline"
                            @click="openUpdateSubmissionStatusDialog"
                        >
                            <Edit class="h-4 w-4 mr-2" />
                            Perbarui Status
                        </Button>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <!-- Review Statistics -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Ikhtisar Review
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold">
                            {{ reviewStats.total_reviewers }}
                        </div>
                        <div class="text-sm text-muted-foreground">Total Reviewers</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">
                            {{ reviewStats.open_reviews }}
                        </div>
                        <div class="text-sm text-muted-foreground">Terbuka</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-blue-600">
                            {{ reviewStats.resolved_reviews }}
                        </div>
                        <div class="text-sm text-muted-foreground">Terselesaikan</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-600">
                            {{ reviewStats.closed_reviews }}
                        </div>
                        <div class="text-sm text-muted-foreground">Ditutup</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">
                            {{ reviewStats.total_comments }}
                        </div>
                        <div class="text-sm text-muted-foreground">Komentar</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Evaluation Forms Section -->
        <Card
            v-if="isAssignedReviewer && hasReviewEvaluationForms && reviewerAssignments.length > 0"
        >
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5" />
                            Form Evaluasi
                        </CardTitle>
                        <p class="text-muted-foreground text-sm mt-1">
                            Lengkapi form evaluasi yang wajib sebelum membuat thread review
                        </p>
                    </div>
                    <Badge variant="default">
                        {{ completedEvaluationsCount }}/{{ totalEvaluationsCount }}
                        Selesai
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div
                        v-for="assignment in reviewerAssignments"
                        :key="assignment.review_evaluation_form.id"
                        class="border rounded-lg p-4 hover:bg-muted/50 transition-colors"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h4 class="font-medium">
                                        {{ assignment.review_evaluation_form.title }}
                                    </h4>
                                    <Badge
                                        :variant="assignment.is_required ? 'default' : 'outline'"
                                        class="text-xs"
                                    >
                                        {{ assignment.is_required ? 'Required' : 'Optional' }}
                                    </Badge>
                                    <Badge
                                        v-if="assignment.review_form_response"
                                        :variant="getAssignmentStatusInfo(assignment).variant"
                                        class="text-xs"
                                    >
                                        <component
                                            :is="getAssignmentStatusInfo(assignment).icon"
                                            class="h-3 w-3 mr-1"
                                        />
                                        {{ getAssignmentStatusInfo(assignment).text }}
                                    </Badge>
                                </div>

                                <p
                                    v-if="assignment.review_evaluation_form.description"
                                    class="text-sm text-muted-foreground mb-2"
                                >
                                    {{ assignment.review_evaluation_form.description }}
                                </p>

                                <div
                                    v-if="assignment.due_date"
                                    class="text-xs text-muted-foreground flex items-center gap-1"
                                >
                                    <Clock class="h-3 w-3" />
                                    Tenggat:
                                    {{ formatDate(assignment.due_date) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="!assignment.id"
                                    size="sm"
                                    @click="
                                        startEvaluationForm(assignment.review_evaluation_form.id)
                                    "
                                >
                                    <FileText class="h-4 w-4 mr-1" />
                                    Mulai
                                </Button>

                                <Button
                                    v-else-if="assignment.review_form_response?.status === 'draft'"
                                    size="sm"
                                    @click="
                                        router.visit(
                                            route('reviewer.evaluation-form.show', {
                                                assignment: assignment.id,
                                            })
                                        )
                                    "
                                >
                                    <FileText class="h-4 w-4 mr-1" />
                                    Lanjutkan
                                </Button>

                                <Button
                                    v-else-if="
                                        assignment.review_form_response?.status === 'submitted'
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        router.visit(
                                            route('reviewer.evaluation-form.submitted', {
                                                assignment: assignment.id,
                                            })
                                        )
                                    "
                                >
                                    <Eye class="h-4 w-4 mr-1" />
                                    Lihat
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Evaluation Requirements Alert -->
        <Alert
            v-if="isAssignedReviewer && hasReviewEvaluationForms && hasPendingEvaluations"
            class="border-amber-200 bg-amber-50"
        >
            <AlertCircle class="h-4 w-4 text-amber-600" />
            <AlertDescription class="text-amber-800">
                <p class="font-medium">
                    {{ threadCreationMessage }}
                </p>
            </AlertDescription>
        </Alert>

        <!-- Review Threads Section -->
        <Card v-if="isAssignedReviewer || isAdmin">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Thread Review
                        <Badge v-if="reviewSummaries.length > 0" variant="secondary" class="ml-2">
                            {{ reviewSummaries.length }}
                        </Badge>
                    </CardTitle>

                    <Button
                        v-if="canActuallyCreateThread"
                        size="sm"
                        @click="showThreadDialog = true"
                    >
                        <Plus class="h-4 w-4 mr-2" />
                        Thread Baru
                    </Button>
                </div>
            </CardHeader>

            <CardContent
                v-if="!canActuallyCreateThread && isAssignedReviewer"
                class="text-center py-6"
            >
                <AlertCircle class="h-8 w-8 mx-auto mb-2 text-amber-500" />
                <p class="text-sm text-muted-foreground">
                    {{ threadCreationMessage }}
                </p>
            </CardContent>
        </Card>

        <!-- Review Threads List -->
        <div v-if="reviewSummaries.length > 0" class="space-y-4">
            <Card
                v-for="reviewSummary in reviewSummaries"
                :key="reviewSummary.id"
                class="border-l-4"
                :class="{
                    'border-l-green-500': reviewSummary.status === 'open',
                    'border-l-blue-500': reviewSummary.status === 'resolved',
                    'border-l-red-500': reviewSummary.status === 'closed',
                }"
            >
                <Collapsible
                    :open="isThreadExpanded(reviewSummary.id)"
                    @update:open="() => toggleThread(reviewSummary.id)"
                >
                    <!-- Thread Header (Always Visible) -->
                    <CardHeader
                        class="cursor-pointer hover:bg-muted/30 transition-colors"
                        @click="toggleThread(reviewSummary.id)"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3 flex-1">
                                <Avatar>
                                    <AvatarFallback>
                                        {{ reviewSummary.reviewer?.user.name.charAt(0) || '?' }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">
                                            {{
                                                reviewSummary.reviewer?.user.name ||
                                                'General Discussion'
                                            }}
                                        </span>
                                        <Badge
                                            :class="getStatusInfo(reviewSummary.status).color"
                                            class="text-xs"
                                        >
                                            <component
                                                :is="getStatusInfo(reviewSummary.status).icon"
                                                class="h-3 w-3 mr-1"
                                            />
                                            {{ getStatusInfo(reviewSummary.status).label }}
                                        </Badge>
                                    </div>
                                    <div class="text-sm text-muted-foreground mt-1">
                                        {{
                                            reviewSummary.reviewer?.reviewer_role.name ||
                                            'Discussion Thread'
                                        }}
                                        •
                                        {{ formatDate(reviewSummary.created_at) }}
                                    </div>

                                    <!-- Comment Count Badge -->
                                    <div class="flex items-center gap-2 mt-2">
                                        <Badge variant="outline" class="text-xs">
                                            <MessageCircle class="h-3 w-3 mr-1" />
                                            {{ getThreadComments(reviewSummary.id).length }}
                                            Komentar
                                        </Badge>
                                        <component
                                            :is="
                                                isThreadExpanded(reviewSummary.id)
                                                    ? ChevronUp
                                                    : ChevronDown
                                            "
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>
                                </div>
                            </div>
                            <!-- Update Status Button -->
                            <Button
                                v-if="canUpdateReviewStatus(reviewSummary)"
                                variant="ghost"
                                size="sm"
                                class="ml-2"
                                @click.stop="openUpdateReviewStatusDialog(reviewSummary)"
                            >
                                <Edit class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>

                    <!-- Thread Content (Collapsible) -->
                    <CollapsibleContent>
                        <CardContent class="space-y-4 pt-0">
                            <!-- Summary Notes -->
                            <div
                                v-if="reviewSummary.summary_notes"
                                class="bg-muted/50 p-4 rounded-lg"
                            >
                                <Label class="text-sm font-medium mb-2 block">Catatan Review</Label>
                                <div class="whitespace-pre-wrap text-sm">
                                    {{ reviewSummary.summary_notes }}
                                </div>
                            </div>

                            <!-- Attachments -->
                            <div v-if="reviewSummary.attachments.length > 0" class="space-y-2">
                                <Label class="text-sm font-medium">Lampiran:</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="attachment in reviewSummary.attachments"
                                        :key="attachment.id"
                                        variant="outline"
                                        size="sm"
                                        @click="downloadAttachment(attachment.file_path)"
                                    >
                                        <Download class="h-3 w-3 mr-1" />
                                        Unduh File
                                    </Button>
                                </div>
                            </div>

                            <Separator />

                            <!-- Comments Section -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <Label class="text-sm font-medium">Diskusi</Label>
                                    <Badge variant="outline" class="text-xs">
                                        {{ getThreadComments(reviewSummary.id).length }}
                                        Komentar
                                    </Badge>
                                </div>

                                <!-- Existing Comments -->
                                <div
                                    v-if="getThreadComments(reviewSummary.id).length > 0"
                                    class="space-y-4"
                                >
                                    <div
                                        v-for="comment in getThreadComments(reviewSummary.id)"
                                        :key="comment.id"
                                        class="space-y-3"
                                    >
                                        <div class="flex gap-3">
                                            <Avatar class="h-8 w-8">
                                                <AvatarFallback>
                                                    {{ getAuthorDisplay(comment).name.charAt(0) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="flex-1 space-y-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-sm">{{
                                                        getAuthorDisplay(comment).name
                                                    }}</span>
                                                    <Badge variant="outline" class="text-xs">
                                                        <component
                                                            :is="getAuthorDisplay(comment).icon"
                                                            class="h-3 w-3 mr-1"
                                                        />
                                                        {{ getAuthorDisplay(comment).type }}
                                                    </Badge>
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ formatDate(comment.created_at) }}
                                                    </span>
                                                </div>
                                                <div class="bg-muted/30 p-3 rounded-lg">
                                                    <p class="text-sm whitespace-pre-wrap">
                                                        {{ comment.comment_text }}
                                                    </p>

                                                    <!-- Comment Attachments -->
                                                    <div
                                                        v-if="comment.attachments.length > 0"
                                                        class="mt-2 flex flex-wrap gap-1"
                                                    >
                                                        <Button
                                                            v-for="attachment in comment.attachments"
                                                            :key="attachment.id"
                                                            variant="ghost"
                                                            size="sm"
                                                            @click="
                                                                downloadAttachment(
                                                                    attachment.file_path
                                                                )
                                                            "
                                                        >
                                                            <Download class="h-3 w-3 mr-1" />
                                                            File
                                                        </Button>
                                                    </div>
                                                </div>

                                                <!-- Replies -->
                                                <div
                                                    v-if="comment.replies.length > 0"
                                                    class="ml-4 space-y-2"
                                                >
                                                    <div
                                                        v-for="reply in comment.replies"
                                                        :key="reply.id"
                                                        class="flex gap-2"
                                                    >
                                                        <Avatar class="h-6 w-6">
                                                            <AvatarFallback>
                                                                {{
                                                                    getAuthorDisplay(
                                                                        reply
                                                                    ).name.charAt(0)
                                                                }}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div class="flex-1">
                                                            <div
                                                                class="flex items-center gap-2 mb-1"
                                                            >
                                                                <span class="text-sm font-medium">{{
                                                                    getAuthorDisplay(reply).name
                                                                }}</span>
                                                                <span
                                                                    class="text-xs text-muted-foreground"
                                                                >
                                                                    {{
                                                                        formatDate(reply.created_at)
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="bg-muted/20 p-2 rounded text-sm"
                                                            >
                                                                {{ reply.comment_text }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Reply Form -->
                                                <div
                                                    v-if="
                                                        replyingTo === comment.id &&
                                                        canCommentOnThread(reviewSummary.status)
                                                    "
                                                    class="ml-4"
                                                >
                                                    <div class="flex gap-2">
                                                        <Textarea
                                                            v-model="newReply"
                                                            placeholder="Tulis Balasan"
                                                            rows="2"
                                                            class="flex-1"
                                                        />
                                                        <div class="flex flex-col gap-1">
                                                            <Button
                                                                size="sm"
                                                                :disabled="!newReply.trim()"
                                                                @click="
                                                                    addComment(
                                                                        reviewSummary.id,
                                                                        comment.id
                                                                    )
                                                                "
                                                            >
                                                                <Send class="h-3 w-3" />
                                                            </Button>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                @click="replyingTo = null"
                                                            >
                                                                <X class="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <Button
                                                    v-else-if="
                                                        canCommentOnThread(reviewSummary.status)
                                                    "
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="replyingTo = comment.id"
                                                >
                                                    Balas
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty Comments State -->
                                <div v-else class="text-center py-8 text-muted-foreground">
                                    <MessageCircle class="h-8 w-8 mx-auto mb-2 opacity-50" />
                                    <p class="text-sm">
                                        Belum ada komentar. Jadilah yang pertama memberikan
                                        komentar!
                                    </p>
                                </div>

                                <!-- New Comment Form (Only if thread is open) -->
                                <div v-if="canCommentOnThread(reviewSummary.status)">
                                    <Separator class="my-4" />
                                    <div class="flex gap-3">
                                        <Avatar class="h-8 w-8">
                                            <AvatarFallback>
                                                {{ currentUser?.name.charAt(0) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="flex-1 space-y-2">
                                            <Textarea
                                                v-model="newComments[reviewSummary.id]"
                                                placeholder="Tambahkan komentar..."
                                                rows="3"
                                            />
                                            <Button
                                                size="sm"
                                                :disabled="!newComments[reviewSummary.id]?.trim()"
                                                @click="addComment(reviewSummary.id)"
                                            >
                                                <Send class="h-4 w-4 mr-2" />
                                                Komentar
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Thread Closed Message -->
                                <div
                                    v-else
                                    class="bg-muted/50 p-4 rounded-lg border border-dashed flex items-center gap-3"
                                >
                                    <Lock class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm font-medium">
                                            This thread is
                                            {{ reviewSummary.status }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Komentar dinonaktifkan untuk thread dengan status
                                            {{ reviewSummary.status }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Collapsible>
            </Card>
        </div>

        <!-- Create Thread Dialog -->
        <Dialog v-model:open="showThreadDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Buat Thread Review</DialogTitle>
                    <DialogDescription>
                        Buat thread diskusi untuk review pengajuan ini.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label>Descripsi</Label>
                        <Textarea
                            v-model="newThreadNotes"
                            placeholder="Deskripsikan isu atau umpan balik..."
                            rows="4"
                        />
                    </div>
                    <div>
                        <Label>Lampiran (Opsional)</Label>
                        <Input
                            type="file"
                            multiple
                            accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                            @change="handleFileUpload"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="showThreadDialog = false"> Batal </Button>
                        <Button :disabled="!newThreadNotes.trim()" @click="createThread">
                            Buat Thread
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Update Review Status Dialog -->
        <Dialog v-model:open="showUpdateReviewStatusDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Perbarui Status Thread Review</DialogTitle>
                    <DialogDescription> Ubah status thread review ini. </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label>Pilih Status</Label>
                        <Select v-model="selectedReviewStatus">
                            <SelectTrigger>
                                <SelectValue placeholder="Choose status..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in reviewStatusOptions"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="`w-2 h-2 rounded-full bg-${status.color}-500`"
                                        />
                                        {{ status.label }}
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="showUpdateReviewStatusDialog = false">
                            Batal
                        </Button>
                        <Button
                            :disabled="!selectedReviewStatus"
                            @click="updateReviewSummaryStatus"
                        >
                            Perbarui Status
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Update Submission Status Dialog -->
        <Dialog v-model:open="showUpdateSubmissionStatusDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Perbarui Status Pengajuan</DialogTitle>
                    <DialogDescription> Ubah status keseluruhan pengajuan ini. </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label>Pilih Status</Label>
                        <Select v-model="selectedSubmissionStatus">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih status..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in submissionStatusOptions"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="`w-2 h-2 rounded-full bg-${status.color}-500`"
                                        />
                                        {{ status.label }}
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Alert>
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            Ini akan memperbarui status pengajuan untuk semua reviewer dan pengaju.
                        </AlertDescription>
                    </Alert>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="showUpdateSubmissionStatusDialog = false">
                            Batal
                        </Button>
                        <Button
                            :disabled="!selectedSubmissionStatus"
                            @click="updateSubmissionStatus"
                        >
                            Perbarui Status
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Empty State -->
        <Card v-if="reviewSummaries.length === 0" class="text-center py-12">
            <CardContent>
                <MessageSquare class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium mb-2">Belum Ada Thread Review</h3>
                <p class="text-muted-foreground mb-4">
                    <span v-if="canActuallyCreateThread">
                        Buat thread review untuk memulai diskusi.
                    </span>
                    <span v-else-if="isAssignedReviewer">
                        {{ threadCreationMessage }}
                    </span>
                    <span v-else> Belum ada thread review yang dibuat untuk pengajuan ini. </span>
                </p>
                <Button v-if="canActuallyCreateThread" @click="showThreadDialog = true">
                    <Plus class="h-4 w-4 mr-2" />
                    Buat Thread Pertama
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
