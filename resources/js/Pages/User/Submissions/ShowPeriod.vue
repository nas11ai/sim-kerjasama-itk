<!-- resources/js/Pages/Submissions/UserPeriodDetail.vue -->
<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    ArrowLeft,
    Calendar,
    Clock,
    FileText,
    CheckCircle,
    AlertCircle,
    Edit,
    Eye,
    RefreshCw
} from "lucide-vue-next";

interface SubmissionDate {
    id: number;
    datetime: string;
    submission_date_label: {
        name: string;
    };
}

interface Form {
    id: number;
    title: string;
    description: string;
    form_type: {
        name: string;
    };
}

interface UserSubmission {
    id: number;
    form: Form;
    is_submitted: boolean;
    can_proceed: boolean;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
}

interface FormPhase {
    id: number;
    title: string;
    description: string;
    user_submissions: UserSubmission[];
}

interface SubmissionPeriod {
    id: number;
    name: string;
    created_at: string;
    submission_dates: SubmissionDate[];
}

interface Props {
    submissionPeriod: SubmissionPeriod;
    formPhases: FormPhase[];
}

const props = defineProps<Props>();

type BadgeVariant = "default" | "destructive" | "outline" | "secondary" | null | undefined;

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString("id-ID", {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getSubmissionBadge = (submission: UserSubmission) => {
    if (submission.is_submitted) {
        return {
            variant: (submission.can_proceed ? 'default' : 'secondary') as BadgeVariant,
            text: submission.can_proceed ? 'Submitted' : 'Under Review',
            icon: submission.can_proceed ? CheckCircle : RefreshCw
        };
    }
    return {
        variant: 'destructive' as BadgeVariant,
        text: 'Draft',
        icon: AlertCircle
    };
};
</script>

<template>

    <Head :title="`${submissionPeriod.name} - My Submissions`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('user.submissions.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back to Submissions
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ submissionPeriod.name }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        My Submissions & Drafts
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Period Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Period Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4">
                        <div>
                            <div class="text-sm font-medium text-muted-foreground mb-1">Created</div>
                            <div>{{ formatDate(submissionPeriod.created_at) }}</div>
                        </div>

                        <div v-if="submissionPeriod.submission_dates.length > 0">
                            <div class="text-sm font-medium text-muted-foreground mb-2">Important Dates</div>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="date in submissionPeriod.submission_dates" :key="date.id"
                                    class="p-3 bg-muted/50 rounded-lg">
                                    <div class="font-medium text-sm">{{ date.submission_date_label.name }}</div>
                                    <div class="text-xs text-muted-foreground flex items-center gap-1">
                                        <Clock class="h-3 w-3" />
                                        {{ formatDateTime(date.datetime) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Phases -->
            <div class="space-y-6">
                <div v-for="phase in formPhases.filter(p => p.user_submissions.length > 0)" :key="phase.id">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg">{{ phase.title }}</CardTitle>
                            <CardDescription v-if="phase.description">
                                {{ phase.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div v-for="submission in phase.user_submissions" :key="submission.id"
                                    class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                                <FileText class="h-5 w-5 text-primary" />
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-medium text-gray-900 truncate">
                                                {{ submission.form.title }}
                                            </h4>
                                            <p class="text-sm text-muted-foreground truncate"
                                                v-if="submission.form.description">
                                                {{ submission.form.description }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-2">
                                                <Badge variant="outline" class="text-xs">
                                                    {{ submission.form.form_type.name }}
                                                </Badge>
                                                <Badge :variant="getSubmissionBadge(submission).variant"
                                                    class="text-xs">
                                                    <component :is="getSubmissionBadge(submission).icon"
                                                        class="h-3 w-3 mr-1" />
                                                    {{ getSubmissionBadge(submission).text }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <div class="text-right text-xs text-muted-foreground">
                                            <div v-if="submission.submitted_at">
                                                Submitted: {{ formatDateTime(submission.submitted_at) }}
                                            </div>
                                            <div v-else>
                                                Last saved: {{ formatDateTime(submission.updated_at) }}
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1">
                                            <Link :href="route('user.submissions.show', submission.id)">
                                            <Button size="sm" variant="outline">
                                                <Eye class="h-4 w-4 mr-1" />
                                                View
                                            </Button>
                                            </Link>

                                            <Link v-if="!submission.is_submitted" :href="route('user.form-phase', {
                                                period: submissionPeriod.id,
                                                phase: phase.id
                                            })">
                                            <Button size="sm">
                                                <Edit class="h-4 w-4 mr-1" />
                                                Continue
                                            </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty State for Phase with No Submissions -->
                <Card v-if="formPhases.filter(p => p.user_submissions.length > 0).length === 0">
                    <CardContent class="text-center py-12">
                        <FileText class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            No Submissions Found
                        </h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            You haven't submitted any forms for this period yet.
                        </p>
                        <Link :href="route('user.dashboard')">
                        <Button>
                            <FileText class="h-4 w-4 mr-2" />
                            Go to Dashboard
                        </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
