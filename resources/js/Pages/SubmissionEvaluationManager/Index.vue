<!-- resources/js/Components/Admin/SubmissionEvaluationManager.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Checkbox } from '@/Components/ui/checkbox'
import {
    Plus,
    Trash2,
    Edit,
    UserPlus,
    FileText,
    Clock,
    CheckCircle,
    AlertTriangle,
    Calendar,
    User,
    Eye,
} from 'lucide-vue-next'

// Types
type BadgeVariant = 'default' | 'destructive' | 'outline' | 'secondary' | null | undefined

interface ReviewEvaluationForm {
    id: number
    title: string
    description?: string
    is_required: boolean
    order: number
}

interface ReviewFormResponse {
    id: number
    status: string
    submitted_at?: string
}

interface ReviewerFormAssignment {
    id: number
    is_required: boolean
    due_date?: string
    assigned_at: string
    review_evaluation_form: ReviewEvaluationForm
    review_form_response?: ReviewFormResponse
}

interface Reviewer {
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

interface SubmissionReviewer {
    id: number
    reviewer: Reviewer
    evaluation_status: string
    reviewer_form_assignments: ReviewerFormAssignment[]
}

interface FormSubmission {
    id: number
    title: string
    status: string
}

interface EvaluationStats {
    total_reviewers: number
    completed_evaluations: number
    pending_evaluations: number
    no_evaluation_required: number
    completion_percentage: number
    all_completed: boolean
}

interface Props {
    submission: FormSubmission
    submissionReviewers: SubmissionReviewer[]
    availableEvaluationForms: ReviewEvaluationForm[]
    evaluationStats: EvaluationStats
    hasEvaluationForms: boolean
}

const props = defineProps<Props>()

// State
const assignFormsDialog = ref(false)
const bulkAssignDialog = ref(false)
const removeAssignmentDialog = ref(false)
const selectedReviewer = ref<SubmissionReviewer | null>(null)
const selectedAssignment = ref<ReviewerFormAssignment | null>(null)

// Forms
const assignFormData = useForm({
    evaluation_form_ids: [] as number[],
    assignments: [] as Array<{
        form_id: number
        is_required: boolean
        due_date: string
    }>,
})

const bulkAssignData = useForm({
    reviewer_ids: [] as number[],
    form_assignments: [] as Array<{
        form_id: number
        is_required: boolean
        due_date: string
    }>,
})

const removeAssignmentData = useForm({})

// Methods
const getEvaluationStatusInfo = (status: string) => {
    switch (status) {
        case 'completed':
            return {
                variant: 'default' as BadgeVariant,
                text: 'Completed',
                icon: CheckCircle,
                color: 'text-green-600',
            }
        case 'pending':
            return {
                variant: 'outline' as BadgeVariant,
                text: 'Pending',
                icon: Clock,
                color: 'text-orange-600',
            }
        case 'not_required':
            return {
                variant: 'secondary' as BadgeVariant,
                text: 'Not Required',
                icon: AlertTriangle,
                color: 'text-blue-600',
            }
        default:
            return {
                variant: 'secondary' as BadgeVariant,
                text: 'Unknown',
                icon: AlertTriangle,
                color: 'text-gray-600',
            }
    }
}

const getAssignmentStatusInfo = (assignment: ReviewerFormAssignment) => {
    if (!assignment.review_form_response) {
        if (assignment.due_date && new Date(assignment.due_date) < new Date()) {
            return {
                variant: 'destructive' as BadgeVariant,
                text: 'Overdue',
                icon: AlertTriangle,
                color: 'text-red-600',
            }
        }
        return {
            variant: 'secondary' as BadgeVariant,
            text: 'Not Started',
            icon: Clock,
            color: 'text-gray-600',
        }
    }

    switch (assignment.review_form_response.status) {
        case 'submitted':
            return {
                variant: 'default' as BadgeVariant,
                text: 'Completed',
                icon: CheckCircle,
                color: 'text-green-600',
            }
        case 'draft':
            return {
                variant: 'outline' as BadgeVariant,
                text: 'In Progress',
                icon: Clock,
                color: 'text-blue-600',
            }
        case 'locked':
            return {
                variant: 'destructive' as BadgeVariant,
                text: 'Locked',
                icon: AlertTriangle,
                color: 'text-red-600',
            }
        default:
            return {
                variant: 'secondary' as BadgeVariant,
                text: 'Unknown',
                icon: AlertTriangle,
                color: 'text-gray-600',
            }
    }
}

const formatDate = (dateString?: string): string => {
    if (!dateString) return 'No deadline'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

const openAssignFormsDialog = (reviewer: SubmissionReviewer) => {
    selectedReviewer.value = reviewer
    assignFormData.reset()
    assignFormData.assignments = props.availableEvaluationForms.map((form) => ({
        form_id: form.id,
        is_required: form.is_required,
        due_date: '',
    }))
    assignFormsDialog.value = true
}

const openBulkAssignDialog = () => {
    bulkAssignData.reset()
    bulkAssignData.form_assignments = props.availableEvaluationForms.map((form) => ({
        form_id: form.id,
        is_required: form.is_required,
        due_date: '',
    }))
    bulkAssignDialog.value = true
}

const openRemoveAssignmentDialog = (assignment: ReviewerFormAssignment) => {
    selectedAssignment.value = assignment
    removeAssignmentDialog.value = true
}

const assignForms = () => {
    if (!selectedReviewer.value) return

    const selectedAssignments = assignFormData.assignments.filter((assignment) =>
        assignFormData.evaluation_form_ids.includes(assignment.form_id)
    )

    assignFormData.assignments = selectedAssignments

    assignFormData.post(
        route('admin.submissions.assign-evaluation-forms', {
            submission: props.submission.id,
            reviewer: selectedReviewer.value.reviewer.id,
        }),
        {
            onSuccess: () => {
                assignFormsDialog.value = false
                selectedReviewer.value = null
            },
        }
    )
}

const bulkAssignForms = () => {
    const selectedAssignments = bulkAssignData.form_assignments.filter(
        () => bulkAssignData.reviewer_ids.length > 0
    )

    bulkAssignData.form_assignments = selectedAssignments

    bulkAssignData.post(route('admin.submissions.assign-forms-bulk', props.submission.id), {
        onSuccess: () => {
            bulkAssignDialog.value = false
        },
    })
}

const removeAssignment = () => {
    if (!selectedAssignment.value) return

    removeAssignmentData.delete(
        route('admin.form-assignments.remove', selectedAssignment.value.id),
        {
            onSuccess: () => {
                removeAssignmentDialog.value = false
                selectedAssignment.value = null
            },
        }
    )
}

const toggleFormSelection = (formId: number, isSelected: boolean) => {
    if (isSelected) {
        if (!assignFormData.evaluation_form_ids.includes(formId)) {
            assignFormData.evaluation_form_ids.push(formId)
        }
    } else {
        const index = assignFormData.evaluation_form_ids.indexOf(formId)
        if (index > -1) {
            assignFormData.evaluation_form_ids.splice(index, 1)
        }
    }
}

const toggleReviewerSelection = (reviewerId: number, isSelected: boolean) => {
    if (isSelected) {
        if (!bulkAssignData.reviewer_ids.includes(reviewerId)) {
            bulkAssignData.reviewer_ids.push(reviewerId)
        }
    } else {
        const index = bulkAssignData.reviewer_ids.indexOf(reviewerId)
        if (index > -1) {
            bulkAssignData.reviewer_ids.splice(index, 1)
        }
    }
}

const updateAssignmentDueDate = (formId: number, event: Event) => {
    const target = event.target as HTMLInputElement
    const assignment = assignFormData.assignments.find((a) => a.form_id === formId)
    if (assignment) {
        assignment.due_date = target.value
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Evaluation Statistics -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center justify-between">
                    <span>Ringkasan Evaluasi</span>
                    <div class="flex space-x-2">
                        <Dialog v-if="hasEvaluationForms" v-model:open="bulkAssignDialog">
                            <DialogTrigger as-child>
                                <Button size="sm" variant="outline" @click="openBulkAssignDialog">
                                    <UserPlus class="h-4 w-4 mr-2" />
                                    Penugasan Formulir Massal
                                </Button>
                            </DialogTrigger>
                        </Dialog>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">
                            {{ evaluationStats.total_reviewers }}
                        </div>
                        <div class="text-sm text-muted-foreground">Total Reviewer</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">
                            {{ evaluationStats.completed_evaluations }}
                        </div>
                        <div class="text-sm text-muted-foreground">Selesai</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">
                            {{ evaluationStats.pending_evaluations }}
                        </div>
                        <div class="text-sm text-muted-foreground">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold">
                            {{ evaluationStats.completion_percentage }}%
                        </div>
                        <div class="text-sm text-muted-foreground">Selesai</div>
                    </div>
                </div>

                <div
                    v-if="!hasEvaluationForms"
                    class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg"
                >
                    <div class="flex items-center space-x-2 text-blue-800">
                        <AlertTriangle class="h-4 w-4" />
                        <span class="text-sm font-medium"
                            >Tidak ada formulir evaluasi yang tersedia untuk fase formulir pengajuan
                            ini.</span
                        >
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Reviewer Assignments -->
        <div v-if="submissionReviewers.length > 0">
            <div
                v-for="submissionReviewer in submissionReviewers"
                :key="submissionReviewer.id"
                class="mb-4"
            >
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <User class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <h4 class="font-medium">
                                        {{ submissionReviewer.reviewer.user.name }}
                                    </h4>
                                    <p class="text-sm text-muted-foreground">
                                        {{ submissionReviewer.reviewer.reviewer_role.name }} •
                                        {{ submissionReviewer.reviewer.user.email }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <component
                                    :is="
                                        getEvaluationStatusInfo(
                                            submissionReviewer.evaluation_status
                                        ).icon
                                    "
                                    class="h-4 w-4"
                                    :class="
                                        getEvaluationStatusInfo(
                                            submissionReviewer.evaluation_status
                                        ).color
                                    "
                                />
                                <Badge
                                    :variant="
                                        getEvaluationStatusInfo(
                                            submissionReviewer.evaluation_status
                                        ).variant
                                    "
                                >
                                    {{
                                        getEvaluationStatusInfo(
                                            submissionReviewer.evaluation_status
                                        ).text
                                    }}
                                </Badge>

                                <Button
                                    v-if="hasEvaluationForms"
                                    size="sm"
                                    variant="outline"
                                    @click="openAssignFormsDialog(submissionReviewer)"
                                >
                                    <Plus class="h-4 w-4 mr-1" />
                                    Tugaskan Formulir
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent v-if="submissionReviewer.reviewer_form_assignments.length > 0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Formulir Evaluasi</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tanggal Jatuh Tempo</TableHead>
                                    <TableHead>Prioritas</TableHead>
                                    <TableHead class="text-right"> Aksi </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="assignment in submissionReviewer.reviewer_form_assignments"
                                    :key="assignment.id"
                                >
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">
                                                {{ assignment.review_evaluation_form.title }}
                                            </div>
                                            <div
                                                v-if="assignment.review_evaluation_form.description"
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ assignment.review_evaluation_form.description }}
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div class="flex items-center space-x-2">
                                            <component
                                                :is="getAssignmentStatusInfo(assignment).icon"
                                                class="h-4 w-4"
                                                :class="getAssignmentStatusInfo(assignment).color"
                                            />
                                            <Badge
                                                :variant="
                                                    getAssignmentStatusInfo(assignment).variant
                                                "
                                            >
                                                {{ getAssignmentStatusInfo(assignment).text }}
                                            </Badge>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div class="flex items-center space-x-2">
                                            <Calendar class="h-4 w-4 text-muted-foreground" />
                                            <span class="text-sm">{{
                                                formatDate(assignment.due_date)
                                            }}</span>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <Badge
                                            :variant="
                                                assignment.is_required ? 'default' : 'outline'
                                            "
                                        >
                                            {{ assignment.is_required ? 'Wajib' : 'Opsional' }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end space-x-1">
                                            <Button size="sm" variant="ghost">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button size="sm" variant="ghost">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="text-destructive"
                                                @click="openRemoveAssignmentDialog(assignment)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>

                    <CardContent v-else class="text-center py-8 text-muted-foreground">
                        <FileText class="h-12 w-12 mx-auto mb-2 opacity-50" />
                        <p>Tidak ada formulir evaluasi yang ditugaskan</p>
                        <p class="text-xs">
                            Klik "Tugaskan Formulir" untuk menambahkan formulir evaluasi
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- No Reviewers State -->
        <Card v-else>
            <CardContent class="text-center py-8">
                <User class="h-16 w-16 mx-auto text-gray-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    Tidak ada reviewer yang ditugaskan
                </h3>
                <p class="text-gray-500">
                    Tugaskan reviewer ke pengajuan ini untuk mengelola formulir evaluasi.
                </p>
            </CardContent>
        </Card>

        <!-- Assign Forms Dialog -->
        <Dialog v-model:open="assignFormsDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Tugaskan Formulir Evaluasi</DialogTitle>
                    <DialogDescription>
                        Pilih formulir evaluasi untuk ditugaskan kepada
                        {{ selectedReviewer?.reviewer.user.name }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <div
                        v-for="form in availableEvaluationForms"
                        :key="form.id"
                        class="border rounded-lg p-4"
                    >
                        <div class="flex items-start space-x-3">
                            <Checkbox
                                :checked="assignFormData.evaluation_form_ids.includes(form.id)"
                                @update:checked="
                                    (checked: boolean) => toggleFormSelection(form.id, checked)
                                "
                            />
                            <div class="flex-1 space-y-3">
                                <div>
                                    <h4 class="font-medium">
                                        {{ form.title }}
                                    </h4>
                                    <p
                                        v-if="form.description"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ form.description }}
                                    </p>
                                    <Badge
                                        :variant="form.is_required ? 'default' : 'outline'"
                                        class="mt-1"
                                    >
                                        {{
                                            form.is_required
                                                ? 'Wajib secara default'
                                                : 'Opsional secara default'
                                        }}
                                    </Badge>
                                </div>

                                <div
                                    v-if="assignFormData.evaluation_form_ids.includes(form.id)"
                                    class="grid gap-3 md:grid-cols-2"
                                >
                                    <div class="space-y-2">
                                        <Label>Wajib untuk reviewer ini</Label>
                                        <Switch
                                            :checked="
                                                assignFormData.assignments.find(
                                                    (a) => a.form_id === form.id
                                                )?.is_required
                                            "
                                            @update:checked="
                                                (checked: boolean) => {
                                                    const assignment =
                                                        assignFormData.assignments.find(
                                                            (a) => a.form_id === form.id
                                                        )
                                                    if (assignment) assignment.is_required = checked
                                                }
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Tanggal Jatuh Tempo</Label>
                                        <Input
                                            type="datetime-local"
                                            :value="
                                                assignFormData.assignments.find(
                                                    (a) => a.form_id === form.id
                                                )?.due_date
                                            "
                                            @input="
                                                (e: Event) => updateAssignmentDueDate(form.id, e)
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="assignFormsDialog = false"> Batal </Button>
                    <Button
                        :disabled="
                            assignFormData.processing ||
                            assignFormData.evaluation_form_ids.length === 0
                        "
                        @click="assignForms"
                    >
                        {{ assignFormData.processing ? 'Menugaskan...' : 'Tugaskan Formulir' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Assign Dialog -->
        <Dialog v-model:open="bulkAssignDialog">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Tugaskan Formulir Secara Massal</DialogTitle>
                    <DialogDescription>
                        Tugaskan formulir evaluasi ke beberapa reviewer sekaligus.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-6">
                    <!-- Reviewer Selection -->
                    <div>
                        <Label class="text-base font-medium">Pilih Reviewer</Label>
                        <div class="mt-2 space-y-2 max-h-32 overflow-y-auto border rounded-lg p-3">
                            <div
                                v-for="submissionReviewer in submissionReviewers"
                                :key="submissionReviewer.id"
                                class="flex items-center space-x-3"
                            >
                                <Checkbox
                                    :checked="
                                        bulkAssignData.reviewer_ids.includes(
                                            submissionReviewer.reviewer.id
                                        )
                                    "
                                    @update:checked="
                                        (checked: boolean) =>
                                            toggleReviewerSelection(
                                                submissionReviewer.reviewer.id,
                                                checked
                                            )
                                    "
                                />
                                <div>
                                    <div class="font-medium">
                                        {{ submissionReviewer.reviewer.user.name }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ submissionReviewer.reviewer.reviewer_role.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Assignment Configuration -->
                    <div>
                        <Label class="text-base font-medium">Konfigurasi Formulir</Label>
                        <div class="mt-2 space-y-3 max-h-64 overflow-y-auto">
                            <div
                                v-for="(assignment, index) in bulkAssignData.form_assignments"
                                :key="assignment.form_id"
                                class="border rounded-lg p-4"
                            >
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h4 class="font-medium">
                                                {{
                                                    availableEvaluationForms.find(
                                                        (f) => f.id === assignment.form_id
                                                    )?.title
                                                }}
                                            </h4>
                                            <p class="text-sm text-muted-foreground">
                                                {{
                                                    availableEvaluationForms.find(
                                                        (f) => f.id === assignment.form_id
                                                    )?.description
                                                }}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive"
                                            @click="
                                                bulkAssignData.form_assignments.splice(index, 1)
                                            "
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="flex items-center space-x-2">
                                            <Switch v-model="assignment.is_required" />
                                            <Label>Wajib</Label>
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Tanggal Jatuh Tempo</Label>
                                            <Input
                                                v-model="assignment.due_date"
                                                type="datetime-local"
                                                class="text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="bulkAssignDialog = false"> Batal </Button>
                    <Button
                        :disabled="
                            bulkAssignData.processing || bulkAssignData.reviewer_ids.length === 0
                        "
                        @click="bulkAssignForms"
                    >
                        {{ bulkAssignData.processing ? 'Menugaskan...' : 'Tugaskan Secara Massal' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Remove Assignment Dialog -->
        <Dialog v-model:open="removeAssignmentDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Penugasan</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus penugasan formulir ini? Tindakan ini tidak
                        dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="removeAssignmentDialog = false">
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="removeAssignmentData.processing"
                        @click="removeAssignment"
                    >
                        {{ removeAssignmentData.processing ? 'Menghapus...' : 'Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
