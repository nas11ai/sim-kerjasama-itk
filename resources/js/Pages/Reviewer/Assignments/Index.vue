<!-- resources/js/Pages/Reviewer/Assignments/Index.vue -->
<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import { Progress } from "@/Components/ui/progress";
import {
    Clock,
    CheckCircle,
    AlertTriangle,
    FileText,
    Calendar,
    User,
    Search,
    Filter,
    Eye
} from "lucide-vue-next";

interface FormSubmission {
    id: number;
    form: {
        id: number;
        title: string;
    };
    submitted_by: {
        id: number;
        name: string;
    };
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
}

interface ReviewFormResponse {
    id: number;
    status: string;
    submitted_at?: string;
}

interface ReviewerFormAssignment {
    id: number;
    is_required: boolean;
    due_date?: string;
    assigned_at: string;
    review_evaluation_form: ReviewEvaluationForm;
    submission_reviewer: {
        form_submission: FormSubmission;
    };
    review_form_response?: ReviewFormResponse;
}

interface PaginationLink {
    url?: string;
    label: string;
    active: boolean;
}

interface PaginatedData {
    data: ReviewerFormAssignment[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface AssignmentStats {
    total: number;
    pending: number;
    completed: number;
    overdue: number;
}

interface Props {
    assignments: PaginatedData;
    stats: AssignmentStats;
    filters: {
        status?: string;
        search?: string;
    };
}

const props = defineProps<Props>();

const filterForm = useForm({
    status: props.filters.status || '',
    search: props.filters.search || '',
});

const searchAssignments = () => {
    filterForm.get(route('reviewer.assignments.index'), {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.reset();
    filterForm.get(route('reviewer.assignments.index'), {
        preserveState: true,
        replace: true,
    });
};

const getStatusInfo = (assignment: ReviewerFormAssignment): {
    variant: "default" | "destructive" | "outline" | "secondary";
    text: string;
    icon: any;
    color: string;
} => {
    if (!assignment.review_form_response) {
        if (assignment.due_date && new Date(assignment.due_date) < new Date()) {
            return {
                variant: "destructive",
                text: "Terlambat",
                icon: AlertTriangle,
                color: "text-red-600"
            };
        }
        return {
            variant: "secondary",
            text: "Belum Dimulai",
            icon: Clock,
            color: "text-gray-600"
        };
    }

    switch (assignment.review_form_response.status) {
        case "submitted":
            return {
                variant: "default",
                text: "Selesai",
                icon: CheckCircle,
                color: "text-green-600"
            };
        case "draft":
            return {
                variant: "outline",
                text: "Sedang Berlangsung",
                icon: Clock,
                color: "text-blue-600"
            };
        case "locked":
            return {
                variant: "destructive",
                text: "Terkunci",
                icon: AlertTriangle,
                color: "text-red-600"
            };
        default:
            return {
                variant: "secondary",
                text: "Tidak Diketahui",
                icon: Clock,
                color: "text-gray-600"
            };
    }
};

const getDaysUntilDue = (dueDate?: string): string => {
    if (!dueDate) return 'Tidak ada tenggat waktu';

    const due = new Date(dueDate);
    const now = new Date();
    const diffTime = due.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return `${Math.abs(diffDays)} hari terlambat`;
    } else if (diffDays === 0) {
        return 'Jatuh tempo hari ini';
    } else if (diffDays === 1) {
        return 'Jatuh tempo besok';
    } else {
        return `${diffDays} hari tersisa`;
    }
};

const formatDate = (dateString?: string): string => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

const getProgressPercentage = (): number => {
    if (props.stats.total === 0) return 100;
    return Math.round((props.stats.completed / props.stats.total) * 100);
};
</script>

<template>

    <Head title="Tugas Review Saya" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Tugas Review Saya
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Stats Overview -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <FileText class="h-5 w-5 text-blue-500" />
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Total</p>
                                <p class="text-2xl font-bold">{{ stats.total }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <Clock class="h-5 w-5 text-orange-500" />
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Pending</p>
                                <p class="text-2xl font-bold text-orange-600">{{ stats.pending }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <CheckCircle class="h-5 w-5 text-green-500" />
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Selesai</p>
                                <p class="text-2xl font-bold text-green-600">{{ stats.completed }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <AlertTriangle class="h-5 w-5 text-red-500" />
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Terlambat</p>
                                <p class="text-2xl font-bold text-red-600">{{ stats.overdue }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Progress Overview -->
            <Card>
                <CardContent class="p-6">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Progress Keseluruhan</span>
                            <span class="text-sm text-muted-foreground">
                                {{ stats.completed }}/{{ stats.total }} selesai
                            </span>
                        </div>
                        <Progress :value="getProgressPercentage()" class="h-2" />
                        <p class="text-xs text-muted-foreground">
                            {{ getProgressPercentage() }}% tugas selesai
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle>Filter</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="search">Cari</Label>
                            <div class="relative">
                                <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input id="search" v-model="filterForm.search"
                                    placeholder="Cari berdasarkan judul form atau pengajuan" class="pl-8"
                                    @keyup.enter="searchAssignments" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Status</Label>
                            <Select v-model="filterForm.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua status</SelectItem>
                                    <SelectItem value="pending">Pending</SelectItem>
                                    <SelectItem value="completed">Selesai</SelectItem>
                                    <SelectItem value="overdue">Terlambat</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-end space-x-2">
                            <Button @click="searchAssignments" class="flex-1">
                                <Filter class="h-4 w-4 mr-2" />
                                Filter
                            </Button>
                            <Button variant="outline" @click="clearFilters">
                                Bersihkan Filter
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assignments Table -->
            <Card>
                <CardHeader>
                    <CardTitle>
                        Review Tugas
                        <Badge variant="secondary" class="ml-2">
                            {{ assignments.total }} total
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="assignments.data.length === 0" class="text-center py-8">
                        <FileText class="h-16 w-16 mx-auto text-gray-400 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            Tidak ada tugas ditemukan
                        </h3>
                        <p class="text-gray-500">
                            Anda tidak memiliki tugas review saat ini.
                        </p>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Form Evaluasi</TableHead>
                                    <TableHead>Pengajuan</TableHead>
                                    <TableHead>Pengirim</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tanngal Jatuh Tempo</TableHead>
                                    <TableHead>Prioritas</TableHead>
                                    <TableHead class="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="assignment in assignments.data" :key="assignment.id"
                                    class="hover:bg-muted/50">
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">
                                                {{ assignment.review_evaluation_form.title }}
                                            </div>
                                            <div v-if="assignment.review_evaluation_form.description"
                                                class="text-sm text-muted-foreground">
                                                {{ assignment.review_evaluation_form.description }}
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div class="font-medium">
                                            {{ assignment.submission_reviewer.form_submission.form.title }}
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div class="flex items-center space-x-2">
                                            <User class="h-4 w-4 text-muted-foreground" />
                                            <span>{{ assignment.submission_reviewer.form_submission.submitted_by.name
                                                }}</span>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div class="flex items-center space-x-2">
                                            <component :is="getStatusInfo(assignment).icon" class="h-4 w-4"
                                                :class="getStatusInfo(assignment).color" />
                                            <Badge :variant="getStatusInfo(assignment).variant">
                                                {{ getStatusInfo(assignment).text }}
                                            </Badge>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div v-if="assignment.due_date">
                                            <div class="flex items-center space-x-2">
                                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                                <span class="text-sm">{{ formatDate(assignment.due_date) }}</span>
                                            </div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ getDaysUntilDue(assignment.due_date) }}
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-muted-foreground">
                                            Tidak ada tenggat waktu
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <Badge :variant="assignment.is_required ? 'default' : 'outline'">
                                            {{ assignment.is_required ? 'Wajib' : 'Opsional' }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <!-- Start/Continue Form Button -->
                                            <Button
                                                v-if="!assignment.review_form_response || assignment.review_form_response.status === 'draft'"
                                                size="sm" as-child>
                                                <a :href="route('reviewer.evaluation-form.show', assignment.id)">
                                                    <FileText class="h-4 w-4 mr-1" />
                                                    {{ assignment.review_form_response ? 'Lanjutkan' : 'Mulai' }}
                                                </a>
                                            </Button>

                                            <!-- View Completed Form Button -->
                                            <Button v-if="assignment.review_form_response?.status === 'submitted'"
                                                size="sm" variant="outline" as-child>
                                                <a :href="route('reviewer.evaluation-form.submitted', assignment.id)">
                                                    <Eye class="h-4 w-4 mr-1" />
                                                    Lihat
                                                </a>
                                            </Button>

                                            <!-- Locked Form Info -->
                                            <Badge v-if="assignment.review_form_response?.status === 'locked'"
                                                variant="destructive">
                                                Terkunci
                                            </Badge>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div v-if="assignments.last_page > 1" class="flex items-center justify-between mt-6">
                            <div class="text-sm text-muted-foreground">
                                Menampilkan {{ (assignments.current_page - 1) * assignments.per_page + 1 }}
                                hingga {{ Math.min(assignments.current_page * assignments.per_page, assignments.total) }}
                                dari {{ assignments.total }} hasil
                            </div>

                            <div class="flex space-x-2">
                                <Button v-for="link in assignments.links" :key="link.label"
                                    :variant="link.active ? 'default' : 'outline'" :disabled="!link.url"
                                    @click="link.url && router.get(link.url)" size="sm">
                                    {{ link.label }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
