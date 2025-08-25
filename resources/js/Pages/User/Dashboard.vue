<!-- resources/js/Pages/User/Dashboard.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    Calendar,
    Clock,
    FileText,
    CheckCircle,
    AlertCircle,
    PlayCircle
} from 'lucide-vue-next';

interface SubmissionPeriod {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    status: string;
    submission_dates: SubmissionDate[];
    form_phases: FormPhase[];
}

interface SubmissionDate {
    id: number;
    datetime: string;
    submission_date_label: {
        name: string;
    };
}

interface FormPhase {
    id: number;
    title: string;
    description: string;
    user_can_access: boolean;
    user_progress: {
        total_forms: number;
        completed_forms: number;
        pending_review: number;
        can_proceed: boolean;
    };
}

interface Props {
    submissionPeriods: SubmissionPeriod[];
    userRole: string;
    studyProgram?: {
        id: number;
        name: string;
        faculty: {
            name: string;
        };
    };
}

const props = defineProps<Props>();

const activeSubmissionPeriods = computed(() =>
    props.submissionPeriods.filter(period => period.is_active)
);

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'upcoming':
            return 'secondary';
        case 'expired':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getProgressPercentage = (completed: number, total: number) => {
    if (total === 0) return 0;
    return Math.round((completed / total) * 100);
};
</script>

<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Dashboard
                    </h2>
                    <p class="text-sm text-muted-foreground mt-1" v-if="studyProgram">
                        {{ studyProgram.faculty.name }} - {{ studyProgram.name }}
                    </p>
                </div>
                <Badge variant="outline" class="capitalize">
                    {{ userRole }}
                </Badge>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Welcome Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Selamat Datang di Sistem Pengisian Form
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        Pilih periode pengajuan aktif di bawah ini untuk mulai mengisi form sesuai dengan role dan
                        program studi
                        Anda.
                    </p>
                </CardContent>
            </Card>

            <!-- Active Submission Periods -->
            <div v-if="activeSubmissionPeriods.length > 0">
                <h3 class="text-lg font-semibold mb-4">Periode Pengajuan Aktif</h3>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-1">
                    <Card v-for="period in activeSubmissionPeriods" :key="period.id"
                        class="hover:shadow-lg transition-shadow">
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div>
                                    <CardTitle class="text-lg">{{ period.name }}</CardTitle>
                                    <div class="flex items-center gap-2 mt-2">
                                        <Badge :variant="getStatusBadgeVariant(period.status)" class="capitalize">
                                            {{ period.status }}
                                        </Badge>
                                        <span class="text-sm text-muted-foreground">
                                            {{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent>
                            <!-- Submission Dates -->
                            <div class="mb-6">
                                <h4 class="font-medium mb-3 flex items-center gap-2">
                                    <Calendar class="h-4 w-4" />
                                    Tanggal Penting
                                </h4>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div v-for="date in period.submission_dates" :key="date.id"
                                        class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-sm">{{ date.submission_date_label.name }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ formatDateTime(date.datetime) }}
                                            </p>
                                        </div>
                                        <Clock class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                </div>
                            </div>

                            <!-- Form Phases -->
                            <div class="mb-6">
                                <h4 class="font-medium mb-3">Form Phases yang Tersedia</h4>
                                <div class="space-y-3">
                                    <div v-for="phase in period.form_phases" :key="phase.id"
                                        class="flex items-center justify-between p-4 border rounded-lg" :class="{
                                            'bg-green-50 border-green-200': phase.user_progress.completed_forms === phase.user_progress.total_forms && phase.user_progress.total_forms > 0,
                                            'bg-yellow-50 border-yellow-200': phase.user_progress.pending_review > 0,
                                            'bg-muted/30': !phase.user_can_access
                                        }">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <h5 class="font-medium">{{ phase.title }}</h5>
                                                <CheckCircle
                                                    v-if="phase.user_progress.completed_forms === phase.user_progress.total_forms && phase.user_progress.total_forms > 0"
                                                    class="h-4 w-4 text-green-600" />
                                                <AlertCircle v-else-if="phase.user_progress.pending_review > 0"
                                                    class="h-4 w-4 text-yellow-600" />
                                            </div>

                                            <p class="text-sm text-muted-foreground mb-2">{{ phase.description }}</p>

                                            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                                <span>{{ phase.user_progress.completed_forms }}/{{
                                                    phase.user_progress.total_forms }} Form Selesai</span>
                                                <span v-if="phase.user_progress.pending_review > 0"
                                                    class="text-yellow-600">
                                                    {{ phase.user_progress.pending_review }} Menunggu Review
                                                </span>
                                            </div>

                                            <!-- Progress Bar -->
                                            <div class="w-full bg-muted rounded-full h-2 mt-2">
                                                <div class="bg-primary h-2 rounded-full transition-all duration-300"
                                                    :style="{ width: `${getProgressPercentage(phase.user_progress.completed_forms, phase.user_progress.total_forms)}%` }">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ml-4">
                                            <Link v-if="phase.user_can_access && phase.user_progress.can_proceed"
                                                :href="route('user.form-phase', { period: period.id, phase: phase.id })">
                                            <Button size="sm">
                                                <PlayCircle class="h-4 w-4 mr-2" />
                                                {{ phase.user_progress.completed_forms > 0 ? 'Lanjutkan' : 'Mulai' }}
                                            </Button>
                                            </Link>

                                            <Button v-else-if="phase.user_progress.pending_review > 0" size="sm"
                                                variant="outline" disabled>
                                                <AlertCircle class="h-4 w-4 mr-2" />
                                                Menunggu Review
                                            </Button>

                                            <Button v-else-if="!phase.user_can_access" size="sm" variant="outline"
                                                disabled>
                                                Tidak Dapat Diakses
                                            </Button>

                                            <Button v-else size="sm" variant="outline" disabled>
                                                Belum Dapat Diakses
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="text-center py-12">
                    <div class="mx-auto max-w-md">
                        <Calendar class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            Tidak Ada Periode Pengajuan Aktif
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            Saat ini tidak ada periode pengajuan yang sedang aktif.
                            Silakan hubungi administrator untuk informasi lebih lanjut.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
