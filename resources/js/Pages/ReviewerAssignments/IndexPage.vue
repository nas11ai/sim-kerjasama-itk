<!-- resources/js/Pages/Admin/ReviewerAssignments/Index.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    ClipboardList,
    CircleAlert,
    CheckCircle,
    AlertTriangle,
    Eye,
    Search,
    Filter,
    User,
    Clock,
} from 'lucide-vue-next'

interface ReviewEvaluationForm {
    id: number
    title: string
    description?: string
}

interface FormSubmission {
    form: {
        title: string
    }
    submitted_by: {
        name: string
    }
}

interface SubmissionReviewer {
    form_submission: FormSubmission
}

interface ReviewFormResponse {
    id: number
    status: 'draft' | 'submitted' | 'locked'
    submitted_at?: string
}

interface ReviewerFormAssignment {
    id: number
    is_required: boolean
    due_date?: string
    assigned_at: string
    review_evaluation_form: ReviewEvaluationForm
    submission_reviewer: SubmissionReviewer
    review_form_response?: ReviewFormResponse
}

interface Stats {
    total: number
    pending: number
    completed: number
    overdue: number
}

interface PaginationLink {
    url: string | undefined
    label: string
    active: boolean
}

interface PaginationMeta {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
}

interface Props {
    assignments: {
        data: ReviewerFormAssignment[]
        links: PaginationLink[]
        meta: PaginationMeta
    }
    stats: Stats
    filters: {
        status?: string
        search?: string
    }
}

const props = defineProps<Props>()

const searchTerm = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')

const getStatusInfo = (assignment: ReviewerFormAssignment) => {
    if (!assignment.review_form_response) {
        return {
            label: 'Not Started',
            color: 'bg-gray-100 text-gray-800',
            icon: ClipboardList,
        }
    }

    switch (assignment.review_form_response.status) {
        case 'draft':
            return {
                label: 'In Progress',
                color: 'bg-blue-100 text-blue-800',
                icon: Clock,
            }
        case 'submitted':
            return {
                label: 'Completed',
                color: 'bg-green-100 text-green-800',
                icon: CheckCircle,
            }
        case 'locked':
            return {
                label: 'Locked',
                color: 'bg-red-100 text-red-800',
                icon: AlertTriangle,
            }
        default:
            return {
                label: 'Unknown',
                color: 'bg-gray-100 text-gray-800',
                icon: CircleAlert,
            }
    }
}

const getUrgencyLevel = (assignment: ReviewerFormAssignment) => {
    if (!assignment.due_date) return null

    const dueDate = new Date(assignment.due_date)
    const now = new Date()
    const diffTime = dueDate.getTime() - now.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays < 0) {
        return {
            level: 'overdue',
            label: `${Math.abs(diffDays)} days overdue`,
            color: 'bg-red-100 text-red-800',
        }
    } else if (diffDays <= 1) {
        return {
            level: 'urgent',
            label: diffDays === 0 ? 'Due today' : 'Due tomorrow',
            color: 'bg-orange-100 text-orange-800',
        }
    } else if (diffDays <= 3) {
        return {
            level: 'soon',
            label: `Due in ${diffDays} days`,
            color: 'bg-yellow-100 text-yellow-800',
        }
    }

    return {
        level: 'normal',
        label: `Due in ${diffDays} days`,
        color: 'bg-blue-100 text-blue-800',
    }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const applyFilters = () => {
    const params: Record<string, string> = {}

    if (searchTerm.value) {
        params.search = searchTerm.value
    }

    if (statusFilter.value) {
        params.status = statusFilter.value
    }

    router.get(route('reviewer.assignments.index'), params, {
        preserveState: true,
        preserveScroll: true,
    })
}

const clearFilters = () => {
    searchTerm.value = ''
    statusFilter.value = ''
    router.get(route('reviewer.assignments.index'))
}

const getActionRoute = (assignment: ReviewerFormAssignment) => {
    if (assignment.review_form_response?.status === 'submitted') {
        return route('reviewer.evaluation-form.submitted', assignment.id)
    }
    return route('reviewer.evaluation-form.show', assignment.id)
}

const getActionLabel = (assignment: ReviewerFormAssignment) => {
    const status = assignment.review_form_response?.status

    switch (status) {
        case 'submitted':
            return 'View Submitted'
        case 'draft':
            return 'Continue'
        case 'locked':
            return 'View (Locked)'
        default:
            return 'Start Evaluation'
    }
}

// Sort assignments by urgency
const sortedAssignments = computed(() => {
    return [...props.assignments.data].sort((a, b) => {
        const urgencyA = getUrgencyLevel(a)
        const urgencyB = getUrgencyLevel(b)

        if (!urgencyA && !urgencyB) return 0
        if (!urgencyA) return 1
        if (!urgencyB) return -1

        type UrgencyLevel = 'overdue' | 'urgent' | 'soon' | 'normal'

        const urgencyOrder: Record<UrgencyLevel, number> = {
            overdue: 0,
            urgent: 1,
            soon: 2,
            normal: 3,
        }

        return (
            urgencyOrder[urgencyA.level as UrgencyLevel] -
            urgencyOrder[urgencyB.level as UrgencyLevel]
        )
    })
})
</script>

<template>
    <Head title="My Evaluation Assignments" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        My Evaluation Assignments
                    </h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        Complete evaluation forms for submissions assigned to you
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Total Assigned </CardTitle>
                        <ClipboardList class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total }}
                        </div>
                        <p class="text-xs text-muted-foreground">All evaluation forms assigned</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Pending </CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">
                            {{ stats.pending }}
                        </div>
                        <p class="text-xs text-muted-foreground">Need to be completed</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Completed </CardTitle>
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ stats.completed }}
                        </div>
                        <p class="text-xs text-muted-foreground">Successfully submitted</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Overdue </CardTitle>
                        <AlertTriangle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">
                            {{ stats.overdue }}
                        </div>
                        <p class="text-xs text-muted-foreground">Past deadline</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Filter Assignments
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label class="text-sm font-medium mb-2 block">Search</label>
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="searchTerm"
                                    placeholder="Search evaluation forms or submissions..."
                                    class="pl-10"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                        </div>

                        <div class="w-48">
                            <label class="text-sm font-medium mb-2 block">Status</label>
                            <Select v-model="statusFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value=""> All statuses </SelectItem>
                                    <SelectItem value="pending"> Pending </SelectItem>
                                    <SelectItem value="completed"> Completed </SelectItem>
                                    <SelectItem value="overdue"> Overdue </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex gap-2">
                            <Button @click="applyFilters">
                                <Search class="h-4 w-4 mr-2" />
                                Filter
                            </Button>
                            <Button variant="outline" @click="clearFilters"> Clear </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assignments Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Evaluation Assignments</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="sortedAssignments.length === 0" class="text-center py-12">
                        <ClipboardList class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">No Assignments Found</h3>
                        <p class="text-muted-foreground">
                            No evaluation assignments match your current filters or you haven't been
                            assigned any yet.
                        </p>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Evaluation Form</TableHead>
                                    <TableHead>Submission</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Due Date</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead class="text-right"> Actions </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="assignment in sortedAssignments"
                                    :key="assignment.id"
                                >
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">
                                                {{ assignment.review_evaluation_form.title }}
                                            </div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ assignment.review_evaluation_form.description }}
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <Badge
                                                    v-if="assignment.is_required"
                                                    variant="destructive"
                                                    class="text-xs"
                                                >
                                                    Required
                                                </Badge>
                                                <Badge v-else variant="secondary" class="text-xs">
                                                    Optional
                                                </Badge>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">
                                                {{
                                                    assignment.submission_reviewer.form_submission
                                                        .form.title
                                                }}
                                            </div>
                                            <div
                                                class="flex items-center gap-1 text-sm text-muted-foreground"
                                            >
                                                <User class="h-3 w-3" />
                                                {{
                                                    assignment.submission_reviewer.form_submission
                                                        .submitted_by.name
                                                }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :class="getStatusInfo(assignment).color">
                                            <component
                                                :is="getStatusInfo(assignment).icon"
                                                class="h-3 w-3 mr-1"
                                            />
                                            {{ getStatusInfo(assignment).label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div v-if="assignment.due_date">
                                            <div class="text-sm">
                                                {{ formatDate(assignment.due_date) }}
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-muted-foreground">
                                            No deadline
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            v-if="getUrgencyLevel(assignment)"
                                            :class="getUrgencyLevel(assignment)!.color"
                                            class="text-xs"
                                        >
                                            {{ getUrgencyLevel(assignment)!.label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="getActionRoute(assignment)">
                                            <Button size="sm" variant="outline">
                                                <Eye class="h-4 w-4 mr-2" />
                                                {{ getActionLabel(assignment) }}
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-muted-foreground">
                                Showing {{ assignments.meta.from || 0 }} to
                                {{ assignments.meta.to || 0 }} of
                                {{ assignments.meta.total }} results
                            </div>

                            <div class="flex gap-2">
                                <Link
                                    v-for="link in assignments.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'px-3 py-2 text-sm border rounded-md',
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-background hover:bg-muted border-border',
                                        !link.url
                                            ? 'opacity-50 cursor-not-allowed'
                                            : 'cursor-pointer',
                                    ]"
                                >
                                    {{ link.label }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
