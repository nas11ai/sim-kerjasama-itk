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
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { AlertCircle, CheckCircle, XCircle, Clock, Shield, User, MessageSquare, Users, Plus, Send, X, Download } from 'lucide-vue-next'

// Types
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
    availableReviewers?: any[]
    assignedReviewers: AssignedReviewer[]
    canAssignReviewers: boolean
    canReview: boolean
    canCreateThread: boolean
    userRole: 'admin' | 'submitter' | 'reviewer' | 'user'
    reviewStats: ReviewStats
    hasPendingEvaluations: boolean
    pendingEvaluationsCount: number
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

const isAssignedReviewer = computed(() => {
    const user = currentUser.value
    if (!user) return false

    // Check if current user is in the assigned reviewers list
    return displayedAssignedReviewers.value.some(
        reviewer => reviewer?.user?.id === user.id
    )
})

// Log for debugging
console.log('ReviewSystem Debug:', {
    canReview: props.canReview,
    canCreateThread: props.canCreateThread,
    isAssignedReviewer: isAssignedReviewer.value,
    currentUserId: currentUser.value?.id,
    assignedReviewers: displayedAssignedReviewers.value,
    hasPendingEvaluations: props.hasPendingEvaluations,
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

        <!-- Review Actions for Assigned Reviewers -->
        <Card v-if="isAssignedReviewer && canReview">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Review Actions
                    </CardTitle>

                    <!-- Create Thread Button -->
                    <Button v-if="canCreateThread && !hasPendingEvaluations" size="sm" @click="showThreadDialog = true">
                        <Plus class="h-4 w-4 mr-2" />
                        New Thread
                    </Button>

                    <!-- Pending Evaluations Warning -->
                    <Badge v-else-if="hasPendingEvaluations" variant="secondary" class="flex items-center gap-1">
                        <AlertCircle class="h-3 w-3" />
                        {{ pendingEvaluationsCount }} evaluation(s) pending
                    </Badge>
                </div>
            </CardHeader>
            <CardContent v-if="hasPendingEvaluations" class="text-center py-6 text-muted-foreground">
                <AlertCircle class="h-8 w-8 mx-auto mb-2 text-amber-500" />
                <p class="text-sm">Complete your evaluation forms before creating review threads.</p>
            </CardContent>
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
                    <span v-if="isAssignedReviewer && canCreateThread && !hasPendingEvaluations">
                        Create a review thread to start the discussion.
                    </span>
                    <span v-else-if="isAssignedReviewer && hasPendingEvaluations">
                        Complete your evaluation forms before creating review threads.
                    </span>
                    <span v-else>
                        No review threads have been created for this submission yet.
                    </span>
                </p>
            </CardContent>
        </Card>
    </div>
</template>
