<!-- resources/js/Pages/Admin/Submissions/ShowPeriod.vue -->
<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Label } from "@/Components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Input } from "@/Components/ui/input";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    ArrowLeft,
    FileText,
    User,
    CheckCircle,
    AlertCircle,
    Search,
    Filter,
    Calendar,
    Building2,
    Mail,
    GraduationCap,
    Eye
} from "lucide-vue-next";
import { ref, computed } from 'vue';

interface SubmissionDateLabel {
    id: number;
    name: string;
}

interface SubmissionDate {
    id: number;
    datetime: string;
    submission_date_label: SubmissionDateLabel;
}

interface SubmissionPeriod {
    id: number;
    name: string;
    description?: string;
    created_at: string;
    updated_at: string;
    submission_dates: SubmissionDate[];
}

interface FormType {
    id: number;
    name: string;
}

interface Form {
    id: number;
    title: string;
    form_type: FormType;
}

interface FormPhaseDetail {
    id: number;
    form_phase_id: number;
}

interface FormAccessControl {
    id: number;
    form: Form;
    form_phase_details: FormPhaseDetail;
}

interface FormPhase {
    id: number;
    title: string;
    form_phase_details: FormPhaseDetail[];
}

interface Faculty {
    id: number;
    name: string;
}

interface SubmittedBy {
    id: number;
    name: string;
    email: string;
}

interface FormSubmission {
    id: number;
    is_submitted: boolean;
    can_proceed: boolean;
    created_at: string;
    updated_at: string;
    form: Form;
    submitted_by: SubmittedBy;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSubmissions {
    data: FormSubmission[];
    current_page: number;
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}

interface Filters {
    form_phase_id?: number;
    status?: string;
    search?: string;
}

interface Props {
    submissionPeriod: SubmissionPeriod;
    formPhases: FormPhase[];
    submissions: PaginatedSubmissions;
    filters: Filters;
}

const props = defineProps<Props>();

// Reactive filter state
const formPhaseFilter = ref(props.filters.form_phase_id?.toString() || undefined);
const statusFilter = ref(props.filters.status || undefined);
const searchFilter = ref(props.filters.search || '');

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString("id-ID", {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getStatusBadge = (submission: FormSubmission) => {
    if (submission.can_proceed) {
        return {
            variant: 'default' as const,
            text: 'Approved',
            icon: CheckCircle
        };
    }
    return {
        variant: 'secondary' as const,
        text: 'Under Review',
        icon: AlertCircle
    };
};

const applyFilters = () => {
    const params = new URLSearchParams();

    if (formPhaseFilter.value) {
        params.append('form_phase_id', formPhaseFilter.value.toString());
    }
    if (statusFilter.value) {
        params.append('status', statusFilter.value);
    }
    if (searchFilter.value) {
        params.append('search', searchFilter.value);
    }

    router.get(window.location.pathname, Object.fromEntries(params), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Update the clearFilters function
const clearFilters = () => {
    formPhaseFilter.value = undefined;
    statusFilter.value = undefined;
    searchFilter.value = '';

    router.get(window.location.pathname, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const viewSubmission = (submissionId: number) => {
    router.visit(`/admin/submissions/${submissionId}`);
};

const goBack = () => {
    router.visit(window.history.length > 1 ? 'javascript:history.back()' : '/admin/submissions');
};

// Computed properties for period dates
const periodStartDate = computed(() => {
    if (!props.submissionPeriod.submission_dates || props.submissionPeriod.submission_dates.length === 0) {
        return 'N/A';
    }
    const earliestDate = props.submissionPeriod.submission_dates
        .reduce((earliest, current) => {
            const currentDate = new Date(current.datetime);
            const earliestDate = new Date(earliest.datetime);
            return currentDate < earliestDate ? current : earliest;
        });
    return earliestDate ? formatDate(earliestDate.datetime) : 'N/A';
});

const periodEndDate = computed(() => {
    if (!props.submissionPeriod.submission_dates || props.submissionPeriod.submission_dates.length === 0) {
        return 'N/A';
    }
    const latestDate = props.submissionPeriod.submission_dates
        .reduce((latest, current) => {
            const currentDate = new Date(current.datetime);
            const latestDate = new Date(latest.datetime);
            return currentDate > latestDate ? current : latest;
        });
    return latestDate ? formatDate(latestDate.datetime) : 'N/A';
});


// Computed properties
const totalSubmissions = computed(() => props.submissions.total);
const approvedSubmissions = computed(() =>
    props.submissions.data.filter(s => s.can_proceed).length
);
const pendingSubmissions = computed(() =>
    props.submissions.data.filter(s => !s.can_proceed).length
);
</script>

<template>

    <Head :title="`${submissionPeriod.name} - Submissions`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ submissionPeriod.name }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ periodStartDate }} - {{ periodEndDate }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Period Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Submission Period Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Description</Label>
                            <p class="font-medium">{{ submissionPeriod.description || 'No description available' }}</p>
                        </div>
                    </div>
                    <div v-if="submissionPeriod.submission_dates.length > 0" class="mt-4">
                        <Label class="text-sm font-medium text-muted-foreground">Important Dates</Label>
                        <div class="grid gap-2 mt-2 md:grid-cols-2 lg:grid-cols-3">
                            <div v-for="date in submissionPeriod.submission_dates" :key="date.id"
                                class="p-3 bg-muted/50 rounded-lg">
                                <p class="font-medium text-sm">{{ date.submission_date_label.name }}</p>
                                <p class="text-muted-foreground text-sm">{{ formatDate(date.datetime) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Statistics -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <FileText class="h-8 w-8 text-blue-600" />
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-foreground">Total Submissions</p>
                                <p class="text-2xl font-bold">{{ totalSubmissions }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <CheckCircle class="h-8 w-8 text-green-600" />
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-foreground">Approved</p>
                                <p class="text-2xl font-bold">{{ approvedSubmissions }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <AlertCircle class="h-8 w-8 text-yellow-600" />
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-foreground">Under Review</p>
                                <p class="text-2xl font-bold">{{ pendingSubmissions }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Filters
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <!-- Form Phase Select -->
                        <div>
                            <Label for="form_phase">Form Phase</Label>
                            <Select v-model="formPhaseFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All phases" />
                                </SelectTrigger>
                                <SelectContent>
                                    <!-- Remove the empty SelectItem, let the placeholder handle "All phases" -->
                                    <SelectItem v-for="phase in formPhases" :key="phase.id"
                                        :value="phase.id.toString()">
                                        {{ phase.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Status Select -->
                        <div>
                            <Label for="status">Status</Label>
                            <Select v-model="statusFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <!-- Remove the empty SelectItem, let the placeholder handle "All statuses" -->
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="pending">Under Review</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="search">Search</Label>
                            <div class="relative">
                                <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input id="search" v-model="searchFilter" placeholder="Search by name or email..."
                                    class="pl-8" />
                            </div>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button @click="applyFilters" class="flex-1">
                                Apply Filters
                            </Button>
                            <Button variant="outline" @click="clearFilters">
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Submissions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Submissions</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Submitter</TableHead>
                                    <TableHead>Form</TableHead>
                                    <TableHead>Submitted At</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="submissions.data.length === 0">
                                    <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
                                        No submissions found for the selected criteria.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="submission in submissions.data" :key="submission.id">
                                    <TableCell>
                                        <div class="space-y-1">
                                            <p class="font-medium flex items-center gap-2">
                                                <User class="h-4 w-4" />
                                                {{ submission.submitted_by.name }}
                                            </p>
                                            <p class="text-sm text-muted-foreground flex items-center gap-2">
                                                <Mail class="h-3 w-3" />
                                                {{ submission.submitted_by.email }}
                                            </p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="space-y-1">
                                            <p class="font-medium">{{ submission.form.title }}</p>
                                            <Badge variant="outline" class="text-xs">
                                                {{ submission.form.form_type.name }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <p class="font-medium">{{ formatDateTime(submission.created_at) }}</p>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadge(submission).variant" class="text-sm">
                                            <component :is="getStatusBadge(submission).icon" class="h-3 w-3 mr-1" />
                                            {{ getStatusBadge(submission).text }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Button size="sm" variant="outline" @click="viewSubmission(submission.id)">
                                            <Eye class="h-4 w-4 mr-1" />
                                            View
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-4" v-if="submissions.last_page > 1">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ submissions.from }} to {{ submissions.to }} of {{ submissions.total }} results
                        </div>
                        <div class="flex items-center gap-2">
                            <Button v-for="link in submissions.links" :key="link.label"
                                :variant="link.active ? 'default' : 'outline'" :disabled="!link.url" size="sm"
                                @click="router.visit(link.url!)" v-html="link.label" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
