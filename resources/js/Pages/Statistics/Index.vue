<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
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
    Activity,
    Inbox,
    CircleAlert,
    Calendar,
    UserIcon,
} from "lucide-vue-next";
import { computed } from "vue";

interface FormPhaseStats {
    formPhaseFaculty: any[];
    formPhaseProdi: any[];
    formPhaseStatus: any[];
    formPhaseTotal: any[];
    formPhaseByPeriod: any[];
}

interface FormSubmissionStats {
    recentSubmissions: any[];
    totalSubmissions: any[];
    totalByStatus: any[];
    totalByFaculty: any[];
    totalByProdi: any[];
}

interface UserStats {
    user_recent: any[];
    total_users: number;
    total_admin: number;
    total_non_admin: number;
    total_prodi: number;
    total_faculty: number;
}

interface SubmissionReviewerStats {
    reviewer_recent: any[];
    total_reviewers: number;
    total_by_role: any[];
    evaluation_status: any[];
    reviewer_by_year: any[];
    reviewer_by_faculty: any[];
    reviewer_by_prodi: any[];
    reviewer_active_status: any[];
}

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

const recentUsers24h = computed(() => props.user?.user_recent?.length || 0);
const recentReviewers24h = computed(() => props.submissionReviewer?.reviewer_recent?.length || 0);

const hasFormPhaseData = computed(() => totalFormPhases.value > 0 || totalForms.value > 0);
const hasSubmissionData = computed(() => 
    (props.formSubmission?.recentSubmissions?.length || 0) > 0 || 
    (props.formSubmission?.totalByStatus?.length || 0) > 0
);
const hasUserData = computed(() => (props.user?.total_users || 0) > 0);
const hasReviewerData = computed(() => (props.submissionReviewer?.total_reviewers || 0) > 0);
</script>

<template>
    <Head title="Statistics Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Statistics Dashboard</h2>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card class="border-l-4 border-l-blue-500 hover:shadow-lg transition-shadow">
                    <CardContent class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Forms</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">
                                    {{ totalForms }}
                                </p>
                                <p v-if="totalForms === 0" class="text-xs text-gray-400 mt-1">No data yet</p>
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
                                <p class="text-sm font-medium text-gray-500">Total Submissions</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">
                                    {{ totalSubmissions }}
                                </p>
                                <p v-if="totalSubmissions === 0" class="text-xs text-gray-400 mt-1">No submissions</p>
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
                                <p class="text-sm font-medium text-gray-500">Total Users</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">
                                    {{ user?.total_users || 0 }}
                                </p>
                                <p v-if="!user?.total_users" class="text-xs text-gray-400 mt-1">No users</p>
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
                                <p class="text-sm font-medium text-gray-500">Total Reviewers</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">
                                    {{ submissionReviewer?.total_reviewers || 0 }}
                                </p>
                                <p v-if="!submissionReviewer?.total_reviewers" class="text-xs text-gray-400 mt-1">No reviewers</p>
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
                                <CardTitle class="text-xl">Form Phase</CardTitle>
                            </div>

                            <Button as-child variant="outline" size="sm" class="w-full sm:w-auto">
                                <a :href="route('admin.stats.form-phase')" class="flex items-center justify-center">
                                    <ArrowRight class="h-4 w-4 mr-2" />
                                    Go to Form Phase Statistics
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <div v-if="!hasFormPhaseData" class="text-center py-12">
                            <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-500 mb-2">No Form Phase Data</h3>
                            
                        </div>

                        <template v-else>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center p-4 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Total Phases</p>
                                    <p class="text-2xl font-bold text-blue-600">{{ totalFormPhases }}</p>
                                </div>
                                <div class="text-center p-4 bg-green-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Total Forms</p>
                                    <p class="text-2xl font-bold text-green-600">{{ totalForms }}</p>
                                </div>
                                <div class="text-center p-4 bg-purple-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Submissions</p>
                                    <p class="text-2xl font-bold text-purple-600">{{ totalSubmissions }}</p>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-gray-500" />
                                    Recent Periods
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
                                        <Badge variant="secondary">{{ period.total_submissions }} submissions</Badge>
                                    </div>
                                    <div v-if="!formPhase.formPhaseByPeriod || formPhase.formPhaseByPeriod.length === 0" 
                                        class="text-center py-8 bg-gray-50 rounded-lg">
                                        <Inbox class="h-10 w-10 mx-auto text-gray-300 mb-2" />
                                        <p class="text-sm text-gray-500">No period data available</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <Button as-child class="w-full" size="lg">
                                    <a :href="route('admin.stats.form-phase')">
                                        <BarChart3 class="h-4 w-4 mr-2" />
                                        View Detailed Form Phase Statistics
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
                                    <CardTitle class="text-xl">Form Submission</CardTitle>
                                </div>
                                <Button as-child variant="outline" size="sm" class="w-full sm:w-auto">
                                    <a :href="route('admin.stats.form-submission')" class="flex items-center justify-center">
                                        <ArrowRight class="h-4 w-4 mr-2" />
                                        Go to Form Submission Statistics
                                    </a>
                                </Button>
                            </div>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <div v-if="!hasSubmissionData" class="text-center py-12">
                            <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-500 mb-2">No Submission Data</h3>
                            
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
                                    <p class="text-sm text-gray-600">Approved</p>
                                    <p class="text-2xl font-bold text-green-600">{{ approvedSubmissions }}</p>
                                </div>
                                <div class="text-center p-4 bg-red-50 rounded-lg border-2 border-red-200">
                                    <XCircle class="h-6 w-6 text-red-600 mx-auto mb-1" />
                                    <p class="text-sm text-gray-600">Rejected</p>
                                    <p class="text-2xl font-bold text-red-600">{{ rejectedSubmissions }}</p>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-gray-500" />
                                    Recent Submissions (24h)
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
                                        <Badge variant="secondary">{{ submission.total_submissions }} new</Badge>
                                    </div>
                                    <div v-if="!formSubmission.recentSubmissions || formSubmission.recentSubmissions.length === 0" 
                                        class="text-center py-8 bg-gray-50 rounded-lg">
                                        <Inbox class="h-10 w-10 mx-auto text-gray-300 mb-2" />
                                        <p class="text-sm text-gray-500">No recent submissions in last 24 hours</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <Button as-child class="w-full" size="lg">
                                    <a :href="route('admin.stats.form-submission')">
                                        <FileText class="h-4 w-4 mr-2" />
                                        View Detailed Submission Statistics
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
                                        Go to User Statistics
                                    </a>
                                </Button>
                            </div>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <div v-if="!hasUserData" class="text-center py-12">
                            <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-500 mb-2">No User Data</h3>
                        </div>

                        <template v-else>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-purple-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Admin Users</p>
                                    <p class="text-2xl font-bold text-purple-600">{{ user.total_admin }}</p>
                                </div>
                                <div class="p-4 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Regular Users</p>
                                    <p class="text-2xl font-bold text-blue-600">{{ user.total_non_admin }}</p>
                                </div>
                                <div class="p-4 bg-green-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Faculty Users</p>
                                    <p class="text-2xl font-bold text-green-600">{{ user.total_faculty }}</p>
                                </div>
                                <div class="p-4 bg-orange-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Program Users</p>
                                    <p class="text-2xl font-bold text-orange-600">{{ user.total_prodi }}</p>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-gray-500" />
                                    New Users (24h)
                                </h4>
                                <div class="flex items-center justify-center p-6 bg-purple-50 rounded-lg border-2 border-purple-200">
                                    <div class="text-center">
                                        <p class="text-4xl font-bold ">{{ recentUsers24h }}</p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ recentUsers24h === 0 ? 'No new registrations' : 'New registrations' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <Button as-child class="w-full" size="lg">
                                    <a :href="route('admin.stats.user')">
                                        <Users class="h-4 w-4 mr-2" />
                                        View Detailed User Statistics
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
                                        Go to Reviewer Statistics
                                    </a>
                                </Button>
                            </div>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <div v-if="!hasReviewerData" class="text-center py-12">
                            <CircleAlert class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-500 mb-2">No Reviewer Data</h3>
                        </div>

                        <template v-else>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <BookOpen class="h-4 w-4 text-gray-500" />
                                    By Role
                                </h4>
                                <div class="space-y-2">
                                    <div
                                        v-for="(role, index) in submissionReviewer.total_by_role.slice(0, 4)"
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
                                            {{ Math.round((role.total_reviewers / submissionReviewer.total_reviewers) * 100) }}%
                                        </Badge>
                                    </div>
                                    <div v-if="!submissionReviewer.total_by_role || submissionReviewer.total_by_role.length === 0" 
                                        class="text-center py-8 bg-gray-50 rounded-lg">
                                        <Inbox class="h-10 w-10 mx-auto text-gray-300 mb-2" />
                                        <p class="text-sm text-gray-500">No reviewer role data available</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-gray-500" />
                                    New Reviewers (24h)
                                </h4>
                                <div class="flex items-center justify-center p-6 bg-orange-50 rounded-lg border-2 border-orange-200">
                                    <div class="text-center">
                                        <p class="text-4xl font-bold ">{{ recentReviewers24h }}</p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ recentReviewers24h === 0 ? 'No new reviewers' : 'New reviewers added' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <Button as-child class="w-full" size="lg">
                                    <a :href="route('admin.stats.reviewer')">
                                        <UserCheck class="h-4 w-4 mr-2" />
                                        View Detailed Reviewer Statistics
                                        <ArrowRight class="h-4 w-4 ml-2" />
                                    </a>
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>