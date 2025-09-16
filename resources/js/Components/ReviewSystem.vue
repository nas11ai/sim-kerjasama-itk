<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogDescription } from '@/Components/ui/dialog'
import { Avatar, AvatarFallback } from '@/Components/ui/avatar'
import { Separator } from '@/Components/ui/separator'
import {
    MessageSquare,
    Users,
    Plus,
    X,
    Clock,
    CheckCircle,
    AlertCircle,
    XCircle,
    Download,
    Send,
    User,
    Shield
} from 'lucide-vue-next'

// Types
interface Reviewer {
    id: number
    name: string
    email: string
    role: string
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

interface ReviewStats {
    total_reviewers: number
    open_reviews: number
    resolved_reviews: number
    closed_reviews: number
    total_comments: number
}

interface Props {
    submissionId: number
    reviewSummaries: ReviewSummary[]
    reviewComments: ReviewComment[]
    availableReviewers: Reviewer[]
    assignedReviewers: AssignedReviewer[]
    canAssignReviewers: boolean
    canReview: boolean
    userRole: 'admin' | 'submitter' | 'reviewer' | 'user'
    reviewStats: ReviewStats
}

const props = defineProps<Props>()
const page = usePage()

// State Management
const showAssignDialog = ref(false)
const showThreadDialog = ref(false)
const showStatusDialog = ref(false)
const selectedReviewers = ref<number[]>([])
const newThreadNotes = ref('')
const newThreadAttachments = ref<File[]>([])
const newComments = ref<Record<number, string>>({})
const replyingTo = ref<number | null>(null)
const newReply = ref('')
const selectedStatus = ref('')
const statusNotes = ref('')

// Loading States
const isAssigning = ref(false)
const removingReviewerId = ref<number | null>(null)
const updatingStatus = ref(false)

// Computed Properties
const currentUser = computed(() => page.props.auth.user as any)
const displayedAssignedReviewers = computed(() => props.assignedReviewers || [])

const canUpdateSubmissionStatus = computed(() => {
    if (props.userRole === 'admin') return true
    if (props.userRole === 'reviewer') {
        return props.assignedReviewers.some(reviewer =>
            reviewer.user.id === currentUser.value?.id
        )
    }
    return false
})

type StatusType = "open" | "resolved" | "closed";

const statusMap: Record<
    StatusType,
    { label: string; color: string; icon: any }
> = {
    open: { label: "Open", color: "bg-green-100 text-green-800", icon: AlertCircle },
    resolved: { label: "Resolved", color: "bg-blue-100 text-blue-800", icon: CheckCircle },
    closed: { label: "Closed", color: "bg-red-100 text-red-800", icon: XCircle },
};

const getStatusInfo = (status: string) => {
    if (status in statusMap) {
        return statusMap[status as StatusType];
    }
    return { label: "Unknown", color: "bg-gray-100 text-gray-800", icon: Clock };
};

const getAuthorDisplay = (comment: ReviewComment) => {
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

// Main Actions
const assignReviewers = () => {
    if (selectedReviewers.value.length === 0) return

    isAssigning.value = true

    router.post(`/admin/submissions/${props.submissionId}/assign-reviewers`, {
        reviewer_ids: selectedReviewers.value
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            showAssignDialog.value = false
            selectedReviewers.value = []
        },
        onFinish: () => {
            isAssigning.value = false
        }
    })
}

const removeReviewer = (reviewerId: number) => {
    if (!confirm('Are you sure you want to remove this reviewer?')) return

    removingReviewerId.value = reviewerId

    router.delete(`/admin/submissions/${props.submissionId}/reviewers/${reviewerId}`, {
        preserveState: false,
        preserveScroll: true,
        onFinish: () => {
            removingReviewerId.value = null
        }
    })
}

const updateSubmissionStatus = () => {
    if (!selectedStatus.value) return

    updatingStatus.value = true

    router.patch(`/submissions/${props.submissionId}/status`, {
        status: selectedStatus.value,
        notes: statusNotes.value
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            showStatusDialog.value = false
            selectedStatus.value = ''
            statusNotes.value = ''
        },
        onFinish: () => {
            updatingStatus.value = false
        }
    })
}

const createThread = () => {
    const formData = new FormData()
    formData.append('summary_notes', newThreadNotes.value)

    newThreadAttachments.value.forEach((file, index) => {
        formData.append(`attachments[${index}]`, file)
    })

    router.post(`/submissions/${props.submissionId}/review-threads`, formData, {
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

const updateReviewStatus = (reviewSummaryId: number, status: string) => {
    router.patch(`/review-summaries/${reviewSummaryId}/status`, { status })
}

const downloadAttachment = (filePath: string) => {
    window.open(`/review-attachments/download?path=${encodeURIComponent(filePath)}`, '_blank')
}

const openStatusDialog = () => {
    showStatusDialog.value = true
    selectedStatus.value = ''
    statusNotes.value = ''
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

        <!-- Assigned Reviewers Management (Admin Only) -->
        <Card v-if="canAssignReviewers">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Assigned Reviewers
                    </CardTitle>

                    <!-- Assign Reviewer Dialog -->
                    <Dialog v-model:open="showAssignDialog">
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus class="h-4 w-4 mr-2" />
                                Assign Reviewer
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Assign Reviewers</DialogTitle>
                                <DialogDescription>
                                    Select one or more reviewers to assign to this submission.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-4">
                                <div>
                                    <Label>Select Reviewers</Label>
                                    <Select v-model="selectedReviewers" multiple>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose reviewers..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="reviewer in availableReviewers" :key="reviewer.id"
                                                :value="reviewer.id">
                                                {{ reviewer.name }} ({{ reviewer.role }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button variant="outline" @click="showAssignDialog = false">
                                        Cancel
                                    </Button>
                                    <Button @click="assignReviewers"
                                        :disabled="selectedReviewers.length === 0 || isAssigning">
                                        <span v-if="isAssigning" class="mr-2">
                                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                    stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </span>
                                        {{ isAssigning ? 'Assigning...' : 'Assign' }}
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="displayedAssignedReviewers.length === 0" class="text-center py-6 text-muted-foreground">
                    <p>No reviewers assigned yet</p>
                </div>
                <div v-else class="space-y-3">
                    <div v-for="reviewer in displayedAssignedReviewers" :key="reviewer.id"
                        class="flex items-center justify-between p-3 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <Avatar>
                                <AvatarFallback>{{ reviewer.user.name.charAt(0) }}</AvatarFallback>
                            </Avatar>
                            <div>
                                <div class="font-medium">{{ reviewer.user.name }}</div>
                                <div class="text-sm text-muted-foreground">{{ reviewer.reviewer_role.name }}</div>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" @click="removeReviewer(reviewer.id)"
                            :disabled="removingReviewerId === reviewer.id">
                            <span v-if="removingReviewerId === reviewer.id" class="mr-1">
                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </span>
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Review Actions -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Review Actions
                    </CardTitle>
                    <div class="flex gap-2">
                        <!-- Direct Status Update -->
                        <Dialog v-if="canUpdateSubmissionStatus" v-model:open="showStatusDialog">
                            <DialogTrigger asChild>
                                <Button size="sm" variant="outline">
                                    <CheckCircle class="h-4 w-4 mr-2" />
                                    Update Status
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Update Submission Status</DialogTitle>
                                    <DialogDescription>
                                        Change the status of this submission directly.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="space-y-4">
                                    <div>
                                        <Label>New Status</Label>
                                        <Select v-model="selectedStatus">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select new status..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="approved">
                                                    <div class="flex items-center gap-2">
                                                        <CheckCircle class="h-4 w-4 text-green-600" />
                                                        Approved
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="needs_revision">
                                                    <div class="flex items-center gap-2">
                                                        <AlertCircle class="h-4 w-4 text-orange-600" />
                                                        Needs Revision
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="rejected">
                                                    <div class="flex items-center gap-2">
                                                        <XCircle class="h-4 w-4 text-red-600" />
                                                        Rejected
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" @click="showStatusDialog = false">Cancel</Button>
                                        <Button @click="updateSubmissionStatus"
                                            :disabled="!selectedStatus || updatingStatus">
                                            <span v-if="updatingStatus" class="mr-2">
                                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                            </span>
                                            {{ updatingStatus ? 'Updating...' : 'Update Status' }}
                                        </Button>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>

                        <!-- Create Review Thread -->
                        <Dialog v-if="userRole === 'reviewer'" v-model:open="showThreadDialog">
                            <DialogTrigger asChild>
                                <Button size="sm">
                                    <Plus class="h-4 w-4 mr-2" />
                                    New Thread
                                </Button>
                            </DialogTrigger>
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
                                        <Textarea v-model="newThreadNotes"
                                            placeholder="Describe the issue or feedback..." rows="4" />
                                    </div>
                                    <div>
                                        <Label>Attachments</Label>
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
                    </div>
                </div>
            </CardHeader>
            <CardContent v-if="!canUpdateSubmissionStatus && userRole !== 'reviewer'"
                class="text-center py-6 text-muted-foreground">
                <MessageSquare class="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p class="text-sm">You don't have permission to create threads or update status.</p>
            </CardContent>
        </Card>

        <!-- Review Threads -->
        <div v-if="reviewSummaries.length > 0" class="space-y-4">
            <h3 class="text-lg font-medium">Review Threads ({{ reviewSummaries.length }})</h3>

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

                                        <!-- Comment Attachments -->
                                        <div v-if="comment.attachments.length > 0" class="mt-2 flex flex-wrap gap-1">
                                            <Button v-for="attachment in comment.attachments" :key="attachment.id"
                                                variant="ghost" size="sm"
                                                @click="downloadAttachment(attachment.file_path)">
                                                <Download class="h-3 w-3 mr-1" />
                                                File
                                            </Button>
                                        </div>
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
                    <span v-if="userRole === 'reviewer'">
                        You can either create a review thread for discussion or directly update the submission status.
                    </span>
                    <span v-else-if="canAssignReviewers">
                        Assign reviewers to start the review process.
                    </span>
                    <span v-else>
                        No review threads have been created for this submission yet.
                    </span>
                </p>
            </CardContent>
        </Card>
    </div>
</template>
