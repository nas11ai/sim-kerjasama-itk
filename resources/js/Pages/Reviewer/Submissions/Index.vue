<!-- resources/js/Pages/Reviewer/Submissions/Index.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    MessageSquare,
    Clock,
    CheckCircle,
    XCircle,
    Eye,
    Search,
    Filter,
    Star,
    AlertCircle
} from 'lucide-vue-next';

interface Submission {
    id: number;
    created_at: string;
    form: {
        id: number;
        title: string;
    };
    submitted_by: {
        id: number;
        name: string;
        email: string;
    };
    review_summaries: Array<{
        id: number;
        status: 'open' | 'resolved' | 'closed';
        created_at: string;
        updated_at: string;
    }>;
}

interface Reviewer {
    id: number;
    reviewer_role: {
        name: string;
    };
}

interface Props {
    submissions: {
        data: Submission[];
        links: any[];
        meta: any;
    };
    filters: {
        status?: string;
        search?: string;
    };
    reviewer: Reviewer;
}

const props = defineProps<Props>();

const searchTerm = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

const getStatusInfo = (status: string) => {
    switch (status) {
        case 'open':
            return {
                label: 'Open',
                color: 'bg-green-100 text-green-800',
                icon: AlertCircle
            };
        case 'resolved':
            return {
                label: 'Resolved',
                color: 'bg-blue-100 text-blue-800',
                icon: CheckCircle
            };
        case 'closed':
            return {
                label: 'Closed',
                color: 'bg-red-100 text-red-800',
                icon: XCircle
            };
        default:
            return {
                label: 'Unknown',
                color: 'bg-gray-100 text-gray-800',
                icon: Clock
            };
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const applyFilters = () => {
    const params: any = {};

    if (searchTerm.value) {
        params.search = searchTerm.value;
    }

    if (statusFilter.value) {
        params.status = statusFilter.value;
    }

    router.get(route('reviewer.submissions.index'), params, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    searchTerm.value = '';
    statusFilter.value = '';
    router.get(route('reviewer.submissions.index'));
};

// Stats computation
const submissionStats = computed(() => {
    const data = props.submissions.data;
    return {
        total: data.length,
        open: data.filter(s => s.review_summaries[0]?.status === 'open').length,
        resolved: data.filter(s => s.review_summaries[0]?.status === 'resolved').length,
        closed: data.filter(s => s.review_summaries[0]?.status === 'closed').length
    };
});
</script>

<template>

    <Head title="Review Tasks - Assigned Submissions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Review Tasks
                    </h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        Submissions assigned to you for review as {{ reviewer.reviewer_role.name }}
                    </p>
                </div>
                <Badge variant="secondary" class="flex items-center gap-1">
                    <Star class="h-3 w-3" />
                    {{ reviewer.reviewer_role.name }}
                </Badge>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Assigned</CardTitle>
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ submissions.meta.total }}</div>
                        <p class="text-xs text-muted-foreground">
                            All submissions assigned to you
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Open Reviews</CardTitle>
                        <AlertCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ submissionStats.open }}</div>
                        <p class="text-xs text-muted-foreground">
                            Need your review
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Completed</CardTitle>
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ submissionStats.resolved }}</div>
                        <p class="text-xs text-muted-foreground">
                            Successfully reviewed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Closed</CardTitle>
                        <XCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">{{ submissionStats.closed }}</div>
                        <p class="text-xs text-muted-foreground">
                            Rejected submissions
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Filter Submissions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label class="text-sm font-medium mb-2 block">Search</label>
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input v-model="searchTerm" placeholder="Search by submitter name or form title..."
                                    class="pl-10" @keyup.enter="applyFilters" />
                            </div>
                        </div>

                        <div class="w-48">
                            <label class="text-sm font-medium mb-2 block">Status</label>
                            <Select v-model="statusFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All statuses</SelectItem>
                                    <SelectItem value="open">Open</SelectItem>
                                    <SelectItem value="resolved">Resolved</SelectItem>
                                    <SelectItem value="closed">Closed</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex gap-2">
                            <Button @click="applyFilters">
                                <Search class="h-4 w-4 mr-2" />
                                Filter
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
                    <CardTitle>Assigned Submissions</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="submissions.data.length === 0" class="text-center py-12">
                        <MessageSquare class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">No Submissions Found</h3>
                        <p class="text-muted-foreground">
                            No submissions match your current filters or you haven't been assigned any submissions yet.
                        </p>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Form Title</TableHead>
                                    <TableHead>Submitter</TableHead>
                                    <TableHead>Review Status</TableHead>
                                    <TableHead>Submitted Date</TableHead>
                                    <TableHead>Last Updated</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="submission in submissions.data" :key="submission.id">
                                    <TableCell class="font-medium">
                                        {{ submission.form.title }}
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ submission.submitted_by.name }}</div>
                                            <div class="text-sm text-muted-foreground">{{ submission.submitted_by.email
                                                }}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge v-if="submission.review_summaries[0]"
                                            :class="getStatusInfo(submission.review_summaries[0].status).color">
                                            <component :is="getStatusInfo(submission.review_summaries[0].status).icon"
                                                class="h-3 w-3 mr-1" />
                                            {{ getStatusInfo(submission.review_summaries[0].status).label }}
                                        </Badge>
                                        <Badge v-else variant="outline">
                                            No Review
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ formatDate(submission.created_at) }}
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ submission.review_summaries[0] ?
                                            formatDate(submission.review_summaries[0].updated_at) : '-' }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('user.submissions.show', submission.id)">
                                        <Button size="sm" variant="outline">
                                            <Eye class="h-4 w-4 mr-2" />
                                            Review
                                        </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-muted-foreground">
                                Showing {{ submissions.meta.from || 0 }} to {{ submissions.meta.to || 0 }}
                                of {{ submissions.meta.total }} results
                            </div>

                            <div class="flex gap-2">
                                <Link v-for="link in submissions.links" :key="link.label" :href="link.url"
                                    v-html="link.label" :class="[
                                        'px-3 py-2 text-sm border rounded-md',
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-background hover:bg-muted border-border',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
