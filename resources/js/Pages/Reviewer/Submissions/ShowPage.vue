<script setup lang="ts">
import { route } from 'ziggy-js'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import {
    type LucideIcon,
    ArrowLeft,
    User,
    Mail,
    Clock,
    FileText,
    Star,
    CheckCircle,
    XCircle,
    AlertCircle,
    MessageSquare,
    Send,
    FileCheck,
    History,
} from 'lucide-vue-next'

interface ReviewStats {
    total_reviewers: number
    open_reviews: number
    resolved_reviews: number
    total_comments: number
}

interface MyReviewSummary {
    id: number
    status: string
    summary_notes: string | null
    created_at: string
    updated_at: string
}

interface ReviewerInfo {
    id: number
    reviewer_role: { name: string }
}

interface FormField {
    id: number
    label: string
    is_required: boolean
    order: number
    field_type: { name: string }
    form_field_options?: Array<{ id: number; label: string }>
}

interface Form {
    id: number
    title: string
    description?: string
    form_fields?: FormField[]
}

interface AssignedReviewer {
    id: number
    user: { id: number; name: string }
    reviewer_role: { name: string }
}

interface Submission {
    id: number
    form?: Form
    status: string
    submitted_by?: { id: number; name: string; email: string }
    submitted_at?: string
    created_at: string
    updated_at: string
    assigned_reviewers?: AssignedReviewer[]
}

interface Props {
    submission: Submission
    responses: Record<number, string>
    reviewStats: ReviewStats
    myReviewSummary?: MyReviewSummary
    reviewer: ReviewerInfo
    canReview: boolean
    userRole: string
    availableStatuses?: { value: string; label: string; icon?: LucideIcon; color?: string }[]
}

const props = defineProps<Props>()

const formatDateTime = (dateString?: string) => {
    if (!dateString) return '-'
    try {
        return new Date(dateString).toLocaleString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        })
    } catch {
        return dateString
    }
}

const goBack = () => {
    router.visit(route('reviewer.submissions.index'))
}

const getMyReviewStatus = () => {
    if (!props.myReviewSummary) {
        return { label: 'Not Started', color: 'bg-gray-100 text-gray-800', icon: Clock }
    }

    switch (props.myReviewSummary.status) {
        case 'open':
            return { label: 'In Progress', color: 'bg-blue-100 text-blue-800', icon: AlertCircle }
        case 'resolved':
            return { label: 'Completed', color: 'bg-green-100 text-green-800', icon: CheckCircle }
        case 'closed':
            return { label: 'Closed', color: 'bg-red-100 text-red-800', icon: XCircle }
        default:
            return { label: 'Unknown', color: 'bg-gray-100 text-gray-800', icon: Clock }
    }
}

// Status modal
const showStatusModal = ref(false)
const selectedStatus = ref('')
const reviewNotes = ref('')
const isSubmitting = ref(false)

const availableStatuses = props.availableStatuses || [
    { value: 'resolved', label: 'Resolved', icon: CheckCircle, color: 'text-green-600' },
    {
        value: 'needs_revision',
        label: 'Needs Revision',
        icon: AlertCircle,
        color: 'text-yellow-600',
    },
    { value: 'rejected', label: 'Rejected', icon: XCircle, color: 'text-red-600' },
]

const getStatusIcon = (statusValue: string) => {
    const status = availableStatuses.find((s) => s.value === statusValue)
    return status?.icon || AlertCircle
}

const getStatusColor = (statusValue: string) => {
    const status = availableStatuses.find((s) => s.value === statusValue)
    return status?.color || 'text-gray-600'
}

const openStatusModal = () => {
    selectedStatus.value = ''
    reviewNotes.value = props.myReviewSummary?.summary_notes || ''
    showStatusModal.value = true
}

const updateStatus = () => {
    if (!selectedStatus.value) return

    isSubmitting.value = true

    router.post(
        route('reviewer.submissions.updateStatus', props.submission.id),
        {
            status: selectedStatus.value,
            notes: reviewNotes.value,
        },
        {
            onSuccess: () => {
                showStatusModal.value = false
                selectedStatus.value = ''
                reviewNotes.value = ''
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}
</script>

<template>
    <Head :title="`Review: ${submission.form?.title || 'Detail Pengajuan'}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" @click="goBack">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Kembali
                    </Button>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            {{ submission.form?.title || 'Detail Pengajuan' }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Review pengajuan dari
                            {{ submission.submitted_by?.name || 'Tidak Diketahui' }}
                        </p>
                    </div>
                </div>
                <Badge variant="secondary" class="flex items-center gap-1">
                    <Star class="h-3 w-3" />
                    {{ reviewer.reviewer_role?.name || 'Reviewer' }}
                </Badge>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- My Review Status Banner -->
            <Card :class="getMyReviewStatus().color">
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <component :is="getMyReviewStatus().icon" class="h-6 w-6" />
                            <div>
                                <h3 class="font-semibold text-lg">
                                    Status Review Saya: {{ getMyReviewStatus().label }}
                                </h3>
                                <p v-if="myReviewSummary" class="text-sm opacity-80 mt-1">
                                    Terakhir diperbarui:
                                    {{ formatDateTime(myReviewSummary.updated_at) }}
                                </p>
                                <p v-else class="text-sm opacity-80 mt-1">
                                    Anda belum memulai review pengajuan ini
                                </p>
                            </div>
                        </div>
                        <Button
                            :variant="myReviewSummary ? 'outline' : 'default'"
                            class="ml-4"
                            @click="openStatusModal"
                        >
                            <FileCheck class="h-4 w-4 mr-2" />
                            {{ myReviewSummary ? 'Perbarui Review' : 'Mulai Review' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Review Progress Stats -->
            <Card v-if="reviewStats">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <History class="h-5 w-5" />
                        Progress Review
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="text-center p-4 bg-muted/50 rounded-lg">
                            <div class="text-2xl font-bold">
                                {{ reviewStats.total_reviewers || 0 }}
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">Total Reviewer</p>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">
                                {{ reviewStats.open_reviews || 0 }}
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">Sedang Berlangsung</p>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                {{ reviewStats.resolved_reviews || 0 }}
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">Selesai</p>
                        </div>
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">
                                {{ reviewStats.total_comments || 0 }}
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">Komentar</p>
                        </div>
                    </div>

                    <!-- Assigned Reviewers -->
                    <div v-if="submission.assigned_reviewers?.length" class="mt-6 pt-6 border-t">
                        <Label class="text-sm font-medium mb-3 block"
                            >Reviewer yang Ditugaskan</Label
                        >
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="rev in submission.assigned_reviewers"
                                :key="rev.id"
                                :variant="rev.id === reviewer.id ? 'default' : 'outline'"
                                class="flex items-center gap-1 py-1.5 px-3"
                            >
                                <User class="h-3 w-3" />
                                {{ rev.user?.name || 'N/A' }}
                                <span class="text-xs opacity-70"
                                    >({{ rev.reviewer_role?.name || 'N/A' }})</span
                                >
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Overview -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Submitted By -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <User class="h-5 w-5" />
                            Dikirim Oleh
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Nama</Label>
                            <p class="font-medium">
                                {{ submission.submitted_by?.name || 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                            <p class="font-medium">
                                <a
                                    :href="`mailto:${submission.submitted_by?.email}`"
                                    class="text-blue-600 hover:underline flex items-center gap-2"
                                >
                                    <Mail class="h-4 w-4" />
                                    {{ submission.submitted_by?.email || 'N/A' }}
                                </a>
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submission Status -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Clock class="h-5 w-5" />
                            Status Pengajuan
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Status</Label>
                            <Badge variant="outline" class="text-sm">
                                {{ submission.status || 'N/A' }}
                            </Badge>
                        </div>
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground"
                                >Dikirim Pada</Label
                            >
                            <p class="font-medium">
                                {{
                                    formatDateTime(submission.submitted_at || submission.created_at)
                                }}
                            </p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground"
                                >Terakhir Diperbarui</Label
                            >
                            <p class="font-medium text-green-600">
                                {{ formatDateTime(submission.updated_at) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

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
                            {{ submission.form?.title || 'N/A' }}
                        </h3>
                        <p v-if="submission.form?.description" class="text-muted-foreground mt-1">
                            {{ submission.form.description }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Responses -->
            <Card>
                <CardHeader>
                    <CardTitle>Respon yang Dikirim</CardTitle>
                    <CardDescription>
                        Semua bidang formulir dan nilai yang dikirimkan
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="submission.form?.form_fields?.length" class="space-y-4">
                        <div
                            v-for="field in submission.form.form_fields"
                            :key="field.id"
                            class="border-b pb-4 last:border-0"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <Label class="font-medium">{{ field.label }}</Label>
                                <Badge
                                    v-if="field.is_required"
                                    variant="destructive"
                                    class="text-xs"
                                >
                                    Wajib
                                </Badge>
                                <Badge variant="outline" class="text-xs capitalize">
                                    {{ field.field_type?.name || 'text' }}
                                </Badge>
                            </div>
                            <div class="pl-4 border-l-2 border-muted">
                                <span class="font-medium">
                                    {{ responses[field.id] || '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground">
                        <FileText class="h-12 w-12 mx-auto mb-3 opacity-50" />
                        <p>Tidak ada bidang formulir tersedia</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Review Actions -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Tindakan Review
                    </CardTitle>
                    <CardDescription> Kirim review Anda dan berikan umpan balik </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Current Review Status -->
                        <div v-if="myReviewSummary" class="bg-muted/50 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <Label class="text-sm font-medium">Status Review Saat Ini</Label>
                                <Badge
                                    :variant="
                                        myReviewSummary.status === 'resolved'
                                            ? 'default'
                                            : 'outline'
                                    "
                                >
                                    {{ myReviewSummary.status }}
                                </Badge>
                            </div>
                            <p
                                v-if="myReviewSummary.summary_notes"
                                class="text-sm text-muted-foreground"
                            >
                                {{ myReviewSummary.summary_notes }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground italic">
                                Belum ada catatan yang diberikan
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            <Button class="flex-1" size="lg" @click="openStatusModal">
                                <Send class="h-4 w-4 mr-2" />
                                {{ myReviewSummary ? 'Perbarui Review Saya' : 'Kirim Review' }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Update Status Modal -->
        <Dialog v-model:open="showStatusModal">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileCheck class="h-5 w-5" />
                        {{ myReviewSummary ? 'Perbarui Status Review' : 'Kirim Review Anda' }}
                    </DialogTitle>
                    <DialogDescription>
                        Berikan keputusan review dan umpan balik Anda untuk pengajuan ini.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Status Selection -->
                    <div class="space-y-2">
                        <Label>Keputusan Review <span class="text-red-500">*</span></Label>
                        <Select v-model="selectedStatus">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih keputusan review..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in availableStatuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="option.icon"
                                            :class="['h-4 w-4', option.color]"
                                        />
                                        <span>{{ option.label }}</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Pilih status yang sesuai berdasarkan review Anda
                        </p>
                    </div>

                    <!-- Review Notes -->
                    <div class="space-y-2">
                        <Label>Review Notes</Label>
                        <Textarea
                            v-model="reviewNotes"
                            placeholder="Tambahkan komentar, saran, atau kekhawatiran review Anda di sini..."
                            class="min-h-[120px] resize-none"
                        />
                        <p class="text-xs text-muted-foreground">
                            Berikan umpan balik yang rinci untuk membantu meningkatkan pengajuan
                        </p>
                    </div>

                    <!-- Selected Status Preview -->
                    <div v-if="selectedStatus" class="bg-muted/50 p-3 rounded-lg">
                        <div class="flex items-center gap-2 text-sm">
                            <component
                                :is="getStatusIcon(selectedStatus)"
                                :class="['h-4 w-4', getStatusColor(selectedStatus)]"
                            />
                            <span class="font-medium">
                                Anda akan menandai ini sebagai:
                                <span :class="getStatusColor(selectedStatus)">
                                    {{
                                        availableStatuses.find((s) => s.value === selectedStatus)
                                            ?.label
                                    }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="showStatusModal = false"
                    >
                        Batal
                    </Button>
                    <Button :disabled="!selectedStatus || isSubmitting" @click="updateStatus">
                        <Send v-if="!isSubmitting" class="h-4 w-4 mr-2" />
                        <span v-if="isSubmitting">Mengirim...</span>
                        <span v-else>{{
                            myReviewSummary ? 'Perbarui Review' : 'Kirim Review'
                        }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
