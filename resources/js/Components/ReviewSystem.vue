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
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { AlertCircle, CheckCircle, XCircle, Clock, Shield, User, MessageSquare, Users, Plus, Send, X, Download, Info, FileText, Eye, ClipboardList } from 'lucide-vue-next'

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

interface EvaluationRequirements {
    required: boolean
    has_forms: boolean
    total_forms?: number
    required_forms?: number
    message: string
}

interface Props {
    submissionId: number
    reviewSummaries: any[]
    reviewComments: any[]
    availableReviewers?: any[]
    assignedReviewers: any[]
    canAssignReviewers: boolean
    canReview: boolean
    canCreateThread: boolean
    userRole: 'admin' | 'submitter' | 'reviewer' | 'user'
    reviewStats: any
    hasPendingEvaluations: boolean
    pendingEvaluationsCount: number
    hasReviewEvaluationForms?: boolean
    evaluationRequirements?: EvaluationRequirements
    reviewerFormAssignments?: ReviewerFormAssignment[]
    submissionReviewerId?: number // NEW: For creating assignments
}

const props = defineProps<Props>()
const page = usePage()

// State Management
const showThreadDialog = ref(false)
const newThreadNotes = ref('')
const newThreadAttachments = ref<File[]>([])
const newComments = ref<Record<number, string>>({})
const replyingTo = ref<number | null>(null)
const newReply = ref('')

// Computed Properties
const currentUser = computed(() => page.props.auth.user as any)
const displayedAssignedReviewers = computed(() => props.assignedReviewers || [])
const reviewerAssignments = computed(() => props.reviewerFormAssignments || [])

const isAssignedReviewer = computed(() => {
    const user = currentUser.value
    if (!user) return false
    return displayedAssignedReviewers.value.some(
        reviewer => reviewer?.user?.id === user.id
    )
})

const isAdmin = computed(() => {
    return props.userRole === 'admin'
})

// Check completed evaluations
const completedEvaluationsCount = computed(() => {
    return reviewerAssignments.value.filter(assignment =>
        assignment.review_form_response?.status === 'submitted'
    ).length
})

const totalEvaluationsCount = computed(() => {
    return reviewerAssignments.value.length
})

// Determine if reviewer can create threads
const canActuallyCreateThread = computed(() => {
    // Admin can always create
    if (isAdmin.value) return true

    if (!isAssignedReviewer.value) return false

    // If no evaluation forms required, can create immediately
    if (!props.hasReviewEvaluationForms) {
        return props.canReview
    }

    // If evaluation forms exist:
    // - Check if assignments exist
    if (reviewerAssignments.value.length === 0) {
        // No assignments yet - waiting for admin
        return false
    }

    // If evaluation forms exist and assignments exist, must complete them first
    return props.canCreateThread && !props.hasPendingEvaluations
})

const threadCreationMessage = computed(() => {
    if (!isAssignedReviewer.value) {
        return 'You are not assigned as a reviewer for this submission.'
    }

    if (!props.hasReviewEvaluationForms) {
        return 'You can create review threads for this submission.'
    }

    // Has evaluation forms
    if (reviewerAssignments.value.length === 0) {
        return 'Waiting for admin to assign evaluation forms. You cannot create review threads yet.'
    }

    if (props.hasPendingEvaluations) {
        return `Complete your ${props.pendingEvaluationsCount} pending evaluation form(s) before creating review threads.`
    }

    return 'You have completed all evaluations and can now create review threads.'
})

type StatusType = "open" | "resolved" | "closed"

const statusMap: Record<StatusType, { label: string; color: string; icon: any }> = {
    open: { label: "Open", color: "bg-green-100 text-green-800", icon: AlertCircle },
    resolved: { label: "Resolved", color: "bg-blue-100 text-blue-800", icon: CheckCircle },
    closed: { label: "Closed", color: "bg-red-100 text-red-800", icon: XCircle },
}

const getStatusInfo = (status: string) => {
    if (status in statusMap) {
        return statusMap[status as StatusType]
    }
    return { label: "Unknown", color: "bg-gray-100 text-gray-800", icon: Clock }
}

const getAuthorDisplay = (comment: any) => {
    if (comment.reviewer?.user) {
        return {
            name: comment.reviewer.user.name,
            type: 'Reviewer',
            icon: Shield,
            isCurrentUser: comment.reviewer.user.id === currentUser.value?.id
        }
    } else if (comment.user) {
        return {
            name: comment.user.name,
            type: comment.user.id === currentUser.value?.id ? 'You' : 'Submitter',
            icon: User,
            isCurrentUser: comment.user.id === currentUser.value?.id
        }
    }
    return { name: 'Unknown', type: 'User', icon: User, isCurrentUser: false }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files) {
        newThreadAttachments.value = Array.from(target.files)
    }
}

const getAssignmentStatusInfo = (assignment: ReviewerFormAssignment) => {
    if (!assignment.review_form_response) {
        return {
            variant: 'secondary' as const,
            text: 'Not Started',
            icon: Clock
        }
    }

    switch (assignment.review_form_response.status) {
        case 'submitted':
            return {
                variant: 'default' as const,
                text: 'Completed',
                icon: CheckCircle
            }
        case 'draft':
            return {
                variant: 'outline' as const,
                text: 'In Progress',
                icon: Clock
            }
        default:
            return {
                variant: 'secondary' as const,
                text: 'Unknown',
                icon: AlertCircle
            }
    }
}

// Actions
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
        }
    })
}

const addComment = (reviewSummaryId: number, parentId?: number) => {
    const commentText = parentId ? newReply.value : newComments.value[reviewSummaryId]
    if (!commentText?.trim()) return

    router.post(`/review-summaries/${reviewSummaryId}/comments`, {
        comment_text: commentText,
        parent_comment_id: parentId || null
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            if (parentId) {
                newReply.value = ''
                replyingTo.value = null
            } else {
                newComments.value[reviewSummaryId] = ''
            }
        }
    })
}

const downloadAttachment = (filePath: string) => {
    window.open(`/review-attachments/download?path=${encodeURIComponent(filePath)}`, '_blank')
}
</script>

<template>
    <div class="space-y-6">
        <!-- Review Statistics -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Review Overview
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold">{{ reviewStats.total_reviewers }}</div>
                        <div class="text-sm text-muted-foreground">Total Reviewers</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ reviewStats.open_reviews }}</div>
                        <div class="text-sm text-muted-foreground">Open</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-blue-600">{{ reviewStats.resolved_reviews }}</div>
                        <div class="text-sm text-muted-foreground">Resolved</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-600">{{ reviewStats.closed_reviews }}</div>
                        <div class="text-sm text-muted-foreground">Closed</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ reviewStats.total_comments }}</div>
                        <div class="text-sm text-muted-foreground">Comments</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Evaluation Forms Section (for reviewers) -->
        <Card v-if="isAssignedReviewer && hasReviewEvaluationForms && reviewerAssignments.length > 0">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5" />
                            Evaluation Forms
                        </CardTitle>
                        <p class="text-muted-foreground text-sm mt-1">
                            Complete all evaluation forms before creating review threads
                        </p>
                    </div>
                    <Badge variant="default">
                        {{ completedEvaluationsCount }}/{{ totalEvaluationsCount }} Completed
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div v-for="assignment in reviewerAssignments" :key="assignment.id"
                        class="border rounded-lg p-4 hover:bg-muted/50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h4 class="font-medium">{{ assignment.review_evaluation_form.title }}</h4>
                                    <Badge :variant="assignment.is_required ? 'default' : 'outline'" class="text-xs">
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
                                    Due: {{ formatDate(assignment.due_date) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Start/Continue Button -->
                                <Button
                                    v-if="!assignment.review_form_response || assignment.review_form_response.status === 'draft'"
                                    size="sm"
                                    @click="router.visit(route('reviewer.evaluation-form.show', { assignment: assignment.id }))">
                                    <FileText class="h-4 w-4 mr-1" />
                                    {{ assignment.review_form_response ? 'Continue' : 'Start' }}
                                </Button>

                                <!-- View Submitted Button -->
                                <Button v-if="assignment.review_form_response?.status === 'submitted'" size="sm"
                                    variant="outline"
                                    @click="router.visit(route('reviewer.evaluation-form.submitted', { assignment: assignment.id }))">
                                    <Eye class="h-4 w-4 mr-1" />
                                    View
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Submitted Evaluations (for Admin to view) -->
        <Card v-if="isAdmin && hasReviewEvaluationForms && reviewSummaries.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <ClipboardList class="h-5 w-5" />
                    Submitted Evaluations
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div v-for="reviewer in assignedReviewers" :key="reviewer.id" class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <Avatar>
                                    <AvatarFallback>{{ reviewer.user.name.charAt(0) }}</AvatarFallback>
                                </Avatar>
                                <div>
                                    <div class="font-medium">{{ reviewer.user.name }}</div>
                                    <div class="text-sm text-muted-foreground">{{ reviewer.reviewer_role.name }}</div>
                                </div>
                            </div>
                            <Button size="sm" variant="outline">
                                <Eye class="h-4 w-4 mr-1" />
                                View Evaluations
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Evaluation Requirements Info -->
        <Alert v-if="isAssignedReviewer && evaluationRequirements && hasPendingEvaluations" variant="default"
            class="border-amber-200 bg-amber-50">
            <AlertCircle class="h-4 w-4 text-amber-600" />
            <AlertDescription class="text-amber-800">
                <p class="font-medium mb-1">{{ evaluationRequirements.message }}</p>
                <p class="text-sm">
                    Complete {{ pendingEvaluationsCount }} pending evaluation form(s) above before creating review
                    threads.
                </p>
            </AlertDescription>
        </Alert>

        <!-- Review Actions for Assigned Reviewers -->
        <Card v-if="isAssignedReviewer && canReview">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Review Threads
                    </CardTitle>

                    <!-- Create Thread Button -->
                    <Button v-if="canActuallyCreateThread" size="sm" @click="showThreadDialog = true">
                        <Plus class="h-4 w-4 mr-2" />
                        New Thread
                    </Button>
                </div>
            </CardHeader>
            <CardContent v-if="hasPendingEvaluations && hasReviewEvaluationForms" class="text-center py-6">
                <AlertCircle class="h-8 w-8 mx-auto mb-2 text-amber-500" />
                <p class="text-sm text-muted-foreground">{{ threadCreationMessage }}</p>
            </CardContent>
        </Card>

        <!-- Admin Create Thread (always visible) -->
        <Card v-if="isAdmin">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Review Threads
                    </CardTitle>
                    <Button size="sm" @click="showThreadDialog = true">
                        <Plus class="h-4 w-4 mr-2" />
                        New Thread
                    </Button>
                </div>
            </CardHeader>
        </Card>

        <!-- Create Thread Dialog -->
        <Dialog v-model:open="showThreadDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Create Review Thread</DialogTitle>
                    <DialogDescription>
                        Create a discussion thread for this submission review.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="newThreadNotes" placeholder="Describe the issue or feedback..." rows="4" />
                    </div>
                    <div>
                        <Label>Attachments (Optional)</Label>
                        <Input type="file" multiple @change="handleFileUpload"
                            accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="showThreadDialog = false">Cancel</Button>
                        <Button @click="createThread" :disabled="!newThreadNotes.trim()">
                            Create Thread
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Review Threads List -->
        <div v-if="reviewSummaries.length > 0" class="space-y-4">
            <h3 class="text-lg font-medium">Discussion Threads ({{ reviewSummaries.length }})</h3>

            <Card v-for="reviewSummary in reviewSummaries" :key="reviewSummary.id" class="border-l-4" :class="{
                'border-l-green-500': reviewSummary.status === 'open',
                'border-l-blue-500': reviewSummary.status === 'resolved',
                'border-l-red-500': reviewSummary.status === 'closed'
            }">
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <Avatar>
                                <AvatarFallback>
                                    {{ reviewSummary.reviewer?.user.name.charAt(0) || '?' }}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div class="font-medium">
                                    {{ reviewSummary.reviewer?.user.name || 'General Discussion' }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ reviewSummary.reviewer?.reviewer_role.name || 'Discussion Thread' }} •
                                    {{ formatDate(reviewSummary.created_at) }}
                                </div>
                            </div>
                        </div>
                        <Badge :class="getStatusInfo(reviewSummary.status).color">
                            <component :is="getStatusInfo(reviewSummary.status).icon" class="h-3 w-3 mr-1" />
                            {{ getStatusInfo(reviewSummary.status).label }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <!-- Summary Notes -->
                    <div v-if="reviewSummary.summary_notes" class="bg-muted/50 p-4 rounded-lg">
                        <div class="whitespace-pre-wrap">{{ reviewSummary.summary_notes }}</div>
                    </div>

                    <!-- Attachments -->
                    <div v-if="reviewSummary.attachments.length > 0" class="space-y-2">
                        <Label class="text-sm font-medium">Attachments:</Label>
                        <div class="flex flex-wrap gap-2">
                            <Button v-for="attachment in reviewSummary.attachments" :key="attachment.id"
                                variant="outline" size="sm" @click="downloadAttachment(attachment.file_path)">
                                <Download class="h-3 w-3 mr-1" />
                                Download File
                            </Button>
                        </div>
                    </div>

                    <!-- Comments Section -->
                    <div class="space-y-4">
                        <Separator />

                        <!-- Existing Comments -->
                        <div v-for="comment in reviewComments.filter(c => c.review_summary_id === reviewSummary.id)"
                            :key="comment.id" class="space-y-3">
                            <div class="flex gap-3">
                                <Avatar class="h-8 w-8">
                                    <AvatarFallback>{{ getAuthorDisplay(comment).name.charAt(0) }}</AvatarFallback>
                                </Avatar>
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ getAuthorDisplay(comment).name }}</span>
                                        <Badge variant="outline" class="text-xs">
                                            <component :is="getAuthorDisplay(comment).icon" class="h-3 w-3 mr-1" />
                                            {{ getAuthorDisplay(comment).type }}
                                        </Badge>
                                        <span class="text-xs text-muted-foreground">
                                            {{ formatDate(comment.created_at) }}
                                        </span>
                                    </div>
                                    <div class="bg-muted/30 p-3 rounded-lg">
                                        <div class="whitespace-pre-wrap">{{ comment.comment_text }}</div>
                                    </div>

                                    <!-- Replies -->
                                    <div v-if="comment.replies.length > 0" class="ml-4 space-y-2">
                                        <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2">
                                            <Avatar class="h-6 w-6">
                                                <AvatarFallback>{{ getAuthorDisplay(reply).name.charAt(0) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-sm font-medium">{{ getAuthorDisplay(reply).name
                                                    }}</span>
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ formatDate(reply.created_at) }}
                                                    </span>
                                                </div>
                                                <div class="bg-muted/20 p-2 rounded text-sm">
                                                    {{ reply.comment_text }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reply Form -->
                                    <div v-if="replyingTo === comment.id" class="ml-4">
                                        <div class="flex gap-2">
                                            <Textarea v-model="newReply" placeholder="Write a reply..." rows="2"
                                                class="flex-1" />
                                            <div class="flex flex-col gap-1">
                                                <Button size="sm" @click="addComment(reviewSummary.id, comment.id)"
                                                    :disabled="!newReply.trim()">
                                                    <Send class="h-3 w-3" />
                                                </Button>
                                                <Button variant="outline" size="sm" @click="replyingTo = null">
                                                    <X class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                    <Button v-else variant="ghost" size="sm" @click="replyingTo = comment.id">
                                        Reply
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- New Comment Form -->
                        <div class="flex gap-3">
                            <Avatar class="h-8 w-8">
                                <AvatarFallback>{{ currentUser?.name.charAt(0) }}</AvatarFallback>
                            </Avatar>
                            <div class="flex-1 space-y-2">
                                <Textarea v-model="newComments[reviewSummary.id]" placeholder="Add a comment..."
                                    rows="3" />
                                <Button size="sm" @click="addComment(reviewSummary.id)"
                                    :disabled="!newComments[reviewSummary.id]?.trim()">
                                    <Send class="h-4 w-4 mr-2" />
                                    Comment
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-if="reviewSummaries.length === 0" class="text-center py-12">
            <CardContent>
                <MessageSquare class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium mb-2">No Review Threads Yet</h3>
                <p class="text-muted-foreground mb-4">
                    <span v-if="isAssignedReviewer && canActuallyCreateThread">
                        Create a review thread to start the discussion.
                    </span>
                    <span v-else-if="isAssignedReviewer && hasPendingEvaluations">
                        {{ threadCreationMessage }}
                    </span>
                    <span v-else>
                        No review threads have been created for this submission yet.
                    </span>
                </p>
            </CardContent>
        </Card>
    </div>
</template>
