<!-- filepath: e:\ITK\sim-kerjasama-itk\resources\js\Pages\Reviewers\Show.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    ArrowLeft,
    Edit,
    Trash2,
    User,
    Mail,
    Calendar,
    Shield,
    CheckCircle,
    XCircle,
    Clock,
    FileText,
    BarChart3,
    AlertTriangle,
    Power,
    PowerOff,
} from 'lucide-vue-next';

interface ReviewerRole {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface FormSubmission {
    id: number;
    form: {
        id: number;
        title: string;
    };
}

interface SubmissionReviewer {
    id: number;
    form_submission: FormSubmission;
    evaluation_status?: string;
    created_at?: string;
}

interface Reviewer {
    id: number;
    user_id: number;
    reviewer_role_id: number;
    start_date: string;
    end_date: string | null;
    created_at: string;
    updated_at: string;
    user: User;
    reviewer_role: ReviewerRole;
    submission_reviewers: SubmissionReviewer[];
    is_active: boolean;
}

interface Props {
    reviewer: Reviewer;
}

const props = defineProps<Props>();

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const handleEdit = () => {
    router.visit(route('admin.reviewers.edit', props.reviewer.id));
};

const goBack = () => {
    router.visit(route('admin.reviewers.index'));
};

const getStatusInfo = computed(() => {
    const now = new Date();
    const startDate = new Date(props.reviewer.start_date);
    const endDate = props.reviewer.end_date ? new Date(props.reviewer.end_date) : null;

    if (startDate > now) {
        return {
            variant: 'default' as const,
            icon: Clock,
            label: 'Scheduled',
            color: 'text-blue-600'
        };
    }

    if (endDate && endDate < now) {
        return {
            variant: 'secondary' as const,
            icon: XCircle,
            label: 'Inactive',
            color: 'text-gray-600'
        };
    }

    return {
        variant: 'outline' as const,
        icon: CheckCircle,
        label: 'Active',
        color: 'text-green-600'
    };
});
</script>

<template>
    <Head :title="`Detail Reviewer - ${reviewer.user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="goBack"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div class="flex items-center gap-3">
                        <div>
                            <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                                {{ reviewer.user.name }}
                                <Badge
                                    :variant="getStatusInfo.variant"
                                    class="text-xs"
                                >
                                    {{ getStatusInfo.label }}
                                </Badge>
                            </h2>
                            <p class="text-sm text-muted-foreground mt-0.5">
                                Detail informasi reviewer
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="handleEdit"
                        class="gap-2"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6 py-6 px-4 sm:px-6 lg:px-8">
            <!-- Reviewer Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <User class="h-5 w-5" />
                        Reviewer Information
                    </CardTitle>
                    <CardDescription>
                        Detail informasi reviewer dan periode aktif
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground">Nama Reviewer</label>
                            <p class="text-base font-semibold">{{ reviewer.user.name }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <Mail class="h-3 w-3" />
                                Email
                            </label>
                            <p class="text-sm">{{ reviewer.user.email }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <Shield class="h-3 w-3" />
                                Reviewer Role
                            </label>
                            <div>
                                <Badge variant="outline" class="gap-1">
                                    <Shield class="h-3 w-3" />
                                    {{ reviewer.reviewer_role.name }}
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground">Status</label>
                            <div>
                                <Badge
                                    :variant="getStatusInfo.variant"
                                    class="gap-1"
                                >
                                    <component :is="getStatusInfo.icon" class="h-3 w-3" />
                                    {{ getStatusInfo.label }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <Calendar class="h-3 w-3" />
                                Start Date
                            </label>
                            <p class="text-sm">{{ formatDate(reviewer.start_date) }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <Calendar class="h-3 w-3" />
                                End Date
                            </label>
                            <p class="text-sm">
                                {{ reviewer.end_date ? formatDate(reviewer.end_date) : 'Tidak ada batas waktu' }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <Calendar class="h-3 w-3" />
                                Dibuat Pada
                            </label>
                            <p class="text-sm">{{ formatDateTime(reviewer.created_at) }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <Calendar class="h-3 w-3" />
                                Terakhir Diperbarui
                            </label>
                            <p class="text-sm">{{ formatDateTime(reviewer.updated_at) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assigned Submissions -->
            <Card v-if="reviewer.submission_reviewers.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Assigned Submissions
                    </CardTitle>
                    <CardDescription>
                        Daftar submission yang ditugaskan untuk direview
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Form Title</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Assigned Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="submission in reviewer.submission_reviewers"
                                :key="submission.id"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <FileText class="h-4 w-4 text-gray-400" />
                                        <span class="font-medium">
                                            {{ submission.form_submission.form.title }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="default" v-if="submission.evaluation_status">
                                        {{ submission.evaluation_status }}
                                    </Badge>
                                    <Badge variant="secondary" v-else>
                                        Not Started
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ submission.created_at ? formatDate(submission.created_at) : '-' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Empty State for No Submissions -->
            <Card v-else>
                <CardContent class="py-12">
                    <div class="text-center space-y-3">
                        <div class="flex justify-center">
                            <div class="p-4 bg-gray-100 rounded-full">
                                <FileText class="h-8 w-8 text-gray-400" />
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Belum Ada Submission</h3>
                            <p class="text-sm text-muted-foreground mt-1">
                                Belum ada submission yang ditugaskan untuk reviewer ini
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
