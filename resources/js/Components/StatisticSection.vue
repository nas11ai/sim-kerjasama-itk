<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import {
    ArrowRight,
    BarChart3,
    Users,
    FileText,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    BookOpen,
    UserCheck,
    Inbox,
    CircleAlert,
    Calendar,
    UserIcon,
} from "lucide-vue-next";
import { computed } from "vue";
import type {
    FormPhaseStats,
    FormSubmissionStats,
    UserStats,
    SubmissionReviewerStats
} from "@/types/statistics";

const props = defineProps<{
    formPhase: FormPhaseStats;
    formSubmission: FormSubmissionStats;
    user: UserStats;
    submissionReviewer: SubmissionReviewerStats;
}>();

const totalFormPhases = computed(() => props.formPhase?.formPhaseTotal?.length || 0);
const totalForms = computed(() => {
    return props.formPhase?.formPhaseTotal?.reduce((sum, item) => sum + (item.total_forms || 0), 0) || 0;
});
const totalSubmissions = computed(() => {
    return props.formPhase?.formPhaseTotal?.reduce((sum, item) => sum + (item.total_submissions || 0), 0) || 0;
});

const pendingSubmissions = computed(() => {
    return props.formSubmission?.totalByStatus?.find(s => s.status === 'pending')?.total || 0;
});
const approvedSubmissions = computed(() => {
    return props.formSubmission?.totalByStatus?.find(s => s.status === 'approved')?.total || 0;
});
const rejectedSubmissions = computed(() => {
    return props.formSubmission?.totalByStatus?.find(s => s.status === 'rejected')?.total || 0;
});

const recentUsers24h = computed(() => props.user?.userRecent?.length || 0);
const recentReviewers24h = computed(() => props.submissionReviewer?.reviewerRecent?.length || 0);

const hasFormPhaseData = computed(() => totalFormPhases.value > 0 || totalForms.value > 0);
const hasSubmissionData = computed(() =>
    (props.formSubmission?.recentSubmissions?.length || 0) > 0 ||
    (props.formSubmission?.totalByStatus?.length || 0) > 0
);
const hasUserData = computed(() => (props.user?.totalUsers || 0) > 0);
const hasReviewerData = computed(() => (props.submissionReviewer?.totalReviewers || 0) > 0);
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card class="border-l-4 border-l-blue-500 hover:shadow-lg transition-shadow">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Formulir</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">
                                {{ totalForms }}
                            </p>
                            <p v-if="totalForms === 0" class="text-xs text-gray-400 mt-1">Belum ada data</p>
                        </div>
                        <div class="h-12 w-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <FileText class="h-6 w-6 text-blue-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-l-4 border-l-green-500 hover:shadow-lg transition-shadow">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Pengajuan</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">
                                {{ totalSubmissions }}
                            </p>
                            <p v-if="totalSubmissions === 0" class="text-xs text-gray-400 mt-1">Belum ada pengajuan</p>
                        </div>
                        <div class="h-12 w-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <Calendar class="h-6 w-6 text-green-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-l-4 border-l-purple-500 hover:shadow-lg transition-shadow">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total User</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">
                                {{ user?.totalUsers || 0 }}
                            </p>
                            <p v-if="!user?.totalUsers" class="text-xs text-gray-400 mt-1">Belum ada user</p>
                        </div>
                        <div class="h-12 w-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <Users class="h-6 w-6 text-purple-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-l-4 border-l-orange-500 hover:shadow-lg transition-shadow">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Reviewer</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">
                                {{ submissionReviewer?.totalReviewers || 0 }}
                            </p>
                            <p v-if="!submissionReviewer?.totalReviewers" class="text-xs text-gray-400 mt-1">Belum ada reviewer</p>
                        </div>
                        <div class="h-12 w-12 bg-orange-100 rounded-xl flex items-center justify-center">
                            <UserCheck class="h-6 w-6 text-orange-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card class="shadow-lg hover:shadow-xl transition-shadow">
                <CardHeader class="border-b pb-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 bg-blue-500 rounded-xl flex items-center justify-center">
                                <FileText class="h-6 w-6 text-white" />
                            </div>
                            <CardTitle class="text-xl">Tahap Formulir</CardTitle>
                        </div>

                        <Button as-child variant="outline" size="sm" class="w-full sm:w-auto">
                            <a :href="route('admin.stats.form-phase')" class="flex items-center justify-center">
                                <ArrowRight class="h-4 w-4 mr-2" />
                                Lihat Statistik Tahap Formulir
                            </a>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-6 space-y-4">
                    <div v-if="!hasFormPhaseData" class="text-center py-12">
                        <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                        <h3 class="text-lg font-semibold text-gray-500 mb-2">Tidak ada data Tahap Formulir</h3>

                    </div>

                    <template v-else>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Total Tahap</p>
                                <p class="text-2xl font-bold text-blue-600">{{ totalFormPhases }}</p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600">Total Formulir</p>
                                <p class="text-2xl font-bold text-green-600">{{ totalForms }}</p>
                            </div>
                            <div class="text-center p-4 bg-purple-50 rounded-lg">
                                <p class="text-sm text-gray-600">Total Pengajuan</p>
                                <p class="text-2xl font-bold text-purple-600">{{ totalSubmissions }}</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <Clock class="h-4 w-4 text-gray-500" />
                                Periode Terbaru
                            </h4>
                            <div class="space-y-2">
                                <div
                                    v-for="period in formPhase.formPhaseByPeriod.slice(0, 3)"
                                    :key="period.submission_period_id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <FileText class="h-4 w-4 text-blue-600" />
                                        </div>
                                        <span class="font-medium text-sm text-gray-900">
                                            {{ period.submission_period_name }}
                                        </span>
                                    </div>
                                    <Badge variant="secondary">{{ period.total_submissions }} pengajuan</Badge>
                                </div>
                                <div v-if="!formPhase.formPhaseByPeriod || formPhase.formPhaseByPeriod.length === 0"
                                    class="text-center py-8 bg-gray-50 rounded-lg">
                                    <Inbox class="h-10 w-10 mx-auto text-gray-300 mb-2" />
                                    <p class="text-sm text-gray-500">Tidak ada data periode</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button as-child class="w-full" size="lg">
                                <a :href="route('admin.stats.form-phase')">
                                    <BarChart3 class="h-4 w-4 mr-2" />
                                    Lihat Statistik Detail Tahap Formulir
                                    <ArrowRight class="h-4 w-4 ml-2" />
                                </a>
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <Card class="shadow-lg hover:shadow-xl transition-shadow">
                <CardHeader class="border-b pb-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 bg-green-500 rounded-xl flex items-center justify-center">
                                <Calendar class="h-6 w-6 text-white" />
                                </div>
                                <CardTitle class="text-xl">Form Pengajuan</CardTitle>
                            </div>
                            <Button as-child variant="outline" size="sm" class="w-full sm:w-auto">
                                <a :href="route('admin.stats.form-submission')" class="flex items-center justify-center">
                                    <ArrowRight class="h-4 w-4 mr-2" />
                                    Lihat Statistik Form Pengajuan
                                </a>
                            </Button>
                        </div>
                </CardHeader>
                <CardContent class="p-6 space-y-4">
                    <div v-if="!hasSubmissionData" class="text-center py-12">
                        <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                        <h3 class="text-lg font-semibold text-gray-500 mb-2">Belum Ada Data Pengajuan</h3>

                    </div>

                    <template v-else>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center p-4 bg-yellow-50 rounded-lg border-2 border-yellow-200">
                                <AlertCircle class="h-6 w-6 text-yellow-600 mx-auto mb-1" />
                                <p class="text-sm text-gray-600">Pending</p>
                                <p class="text-2xl font-bold text-yellow-600">{{ pendingSubmissions }}</p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg border-2 border-green-200">
                                <CheckCircle class="h-6 w-6 text-green-600 mx-auto mb-1" />
                                <p class="text-sm text-gray-600">Disetujui</p>
                                <p class="text-2xl font-bold text-green-600">{{ approvedSubmissions }}</p>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-lg border-2 border-red-200">
                                <XCircle class="h-6 w-6 text-red-600 mx-auto mb-1" />
                                <p class="text-sm text-gray-600">Ditolak</p>
                                <p class="text-2xl font-bold text-red-600">{{ rejectedSubmissions }}</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <Clock class="h-4 w-4 text-gray-500" />
                                Pengajuan Terbaru (24j)
                            </h4>
                            <div class="space-y-2">
                                <div
                                    v-for="submission in formSubmission.recentSubmissions.slice(0, 3)"
                                    :key="submission.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 bg-green-100 rounded-lg flex items-center justify-center">
                                            <Calendar class="h-4 w-4 text-green-600" />
                                        </div>
                                        <span class="font-medium text-sm text-gray-900">
                                            {{ submission.name }}
                                        </span>
                                    </div>
                                    <Badge variant="secondary">{{ submission.total_submissions }} baru</Badge>
                                </div>
                                <div v-if="!formSubmission.recentSubmissions || formSubmission.recentSubmissions.length === 0"
                                    class="text-center py-8 bg-gray-50 rounded-lg">
                                    <Inbox class="h-10 w-10 mx-auto text-gray-300 mb-2" />
                                    <p class="text-sm text-gray-500">Tidak ada pengajuan terbaru dalam 24 jam terakhir</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button as-child class="w-full" size="lg">
                                <a :href="route('admin.stats.form-submission')">
                                    <FileText class="h-4 w-4 mr-2" />
                                    Lihat Statistik Detail Pengajuan
                                    <ArrowRight class="h-4 w-4 ml-2" />
                                </a>
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <Card class="shadow-lg hover:shadow-xl transition-shadow">
                <CardHeader class="border-b pb-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 bg-purple-500 rounded-xl flex items-center justify-center">
                                <UserIcon class="h-6 w-6 text-white" />
                            </div>
                                <CardTitle class="text-xl">User</CardTitle>
                            </div>
                            <Button as-child variant="outline" size="sm" class="w-full sm:w-auto">
                                <a :href="route('admin.stats.user')" class="flex items-center justify-center">
                                    <ArrowRight class="h-4 w-4 mr-2" />
                                    Lihat Statistik User
                                </a>
                            </Button>
                        </div>
                </CardHeader>
                <CardContent class="p-6 space-y-4">
                    <div v-if="!hasUserData" class="text-center py-12">
                        <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                        <h3 class="text-lg font-semibold text-gray-500 mb-2">Belum Ada Data User</h3>
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-purple-50 rounded-lg">
                                <p class="text-sm text-gray-600">Admin User</p>
                                <p class="text-2xl font-bold text-purple-600">{{ user.totalAdmin }}</p>
                            </div>
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Regular User</p>
                                <p class="text-2xl font-bold text-blue-600">{{ user.totalNonAdmin }}</p>
                            </div>
                            <div class="p-4 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600">User Fakultas</p>
                                <p class="text-2xl font-bold text-green-600">{{ user.totalFaculty }}</p>
                            </div>
                            <div class="p-4 bg-orange-50 rounded-lg">
                                <p class="text-sm text-gray-600">User Program Studi</p>
                                <p class="text-2xl font-bold text-orange-600">{{ user.totalProdi }}</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <Clock class="h-4 w-4 text-gray-500" />
                                User Baru (24h)
                            </h4>
                            <div class="flex items-center justify-center p-6 bg-purple-50 rounded-lg border-2 border-purple-200">
                                <div class="text-center">
                                    <p class="text-4xl font-bold ">{{ recentUsers24h }}</p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ recentUsers24h === 0 ? 'Tidak ada pendaftaran baru' : 'Pendaftaran baru' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button as-child class="w-full" size="lg">
                                <a :href="route('admin.stats.user')">
                                    <Users class="h-4 w-4 mr-2" />
                                    Lihat Statistik Detail User
                                    <ArrowRight class="h-4 w-4 ml-2" />
                                </a>
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <Card class="shadow-lg hover:shadow-xl transition-shadow">
                <CardHeader class="border-b pb-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 bg-orange-500 rounded-xl flex items-center justify-center">
                                <Users class="h-6 w-6 text-white" />
                            </div>
                                <CardTitle class="text-xl">Reviewer</CardTitle>
                            </div>
                            <Button as-child variant="outline" size="sm" class="w-full sm:w-auto">
                                <a :href="route('admin.stats.reviewer')" class="flex items-center justify-center">
                                    <ArrowRight class="h-4 w-4 mr-2" />
                                    Lihat Statistik Reviewer
                                </a>
                            </Button>
                        </div>
                </CardHeader>
                <CardContent class="p-6 space-y-4">
                    <div v-if="!hasReviewerData" class="text-center py-12">
                        <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                        <h3 class="text-lg font-semibold text-gray-500 mb-2">Belum Ada Data Reviewer</h3>
                    </div>

                    <template v-else>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <BookOpen class="h-4 w-4 text-gray-500" />
                                Berdasarkan Role
                            </h4>
                            <div class="space-y-2">
                                <div
                                    v-for="(role, index) in submissionReviewer.totalByRole.slice(0, 4)"
                                    :key="role.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-8 w-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                                            :class="[
                                                index === 0 ? 'bg-orange-500' : '',
                                                index === 1 ? 'bg-amber-500' : '',
                                                index === 2 ? 'bg-yellow-500' : '',
                                                index === 3 ? 'bg-lime-500' : ''
                                            ]"
                                        >
                                            {{ role.total_reviewers }}
                                        </div>
                                        <span class="font-medium text-sm text-gray-900">
                                            {{ role.reviewer_role_name }}
                                        </span>
                                    </div>
                                    <Badge variant="outline">
                                        {{
                                            submissionReviewer.totalReviewers
                                            ? Math.round((role.total_reviewers / submissionReviewer.totalReviewers) * 100)
                                            : 0
                                        }}%
                                    </Badge>
                                </div>
                                <div v-if="!submissionReviewer.totalByRole || submissionReviewer.totalByRole.length === 0"
                                    class="text-center py-8 bg-gray-50 rounded-lg">
                                    <Inbox class="h-10 w-10 mx-auto text-gray-300 mb-2" />
                                    <p class="text-sm text-gray-500">Belum ada data role reviewer</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <Clock class="h-4 w-4 text-gray-500" />
                                Reviewer Baru (24h)
                            </h4>
                            <div class="flex items-center justify-center p-6 bg-orange-50 rounded-lg border-2 border-orange-200">
                                <div class="text-center">
                                    <p class="text-4xl font-bold ">{{ recentReviewers24h }}</p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ recentReviewers24h === 0 ? 'Tidak ada reviewer baru' : 'Reviewer baru ditambahkan' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button as-child class="w-full" size="lg">
                                <a :href="route('admin.stats.reviewer')">
                                    <UserCheck class="h-4 w-4 mr-2" />
                                    Lihat Statistik Detail Reviewer
                                    <ArrowRight class="h-4 w-4 ml-2" />
                                </a>
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
