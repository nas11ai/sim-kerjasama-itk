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
    review_summaries?: Array<{
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
        meta: {
            current_page: number;
            from: number | null;
            to: number | null;
            total: number;
            links: Array<{
                url: string | null;
                label: string;
                active: boolean;
            }>;
        };
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
            return { label: 'Open', color: 'bg-green-100 text-green-800', icon: AlertCircle };
        case 'resolved':
            return { label: 'Resolved', color: 'bg-blue-100 text-blue-800', icon: CheckCircle };
        case 'closed':
            return { label: 'Closed', color: 'bg-red-100 text-red-800', icon: XCircle };
        default:
            return { label: 'Unknown', color: 'bg-gray-100 text-gray-800', icon: Clock };
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
    const params: Record<string, string> = {};

    if (searchTerm.value) params.search = searchTerm.value;
    if (statusFilter.value) params.status = statusFilter.value;

    router.get(route('reviewer.assignments.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchTerm.value = '';
    statusFilter.value = '';
    router.get(route('reviewer.assignments.index'));
};

// Statistik (hitung total berdasarkan status)
const submissionStats = computed(() => {
    const data = props.submissions.data;
    return {
        total: data.length,
        open: data.filter(s => s.review_summaries?.[0]?.status === 'open').length,
        resolved: data.filter(s => s.review_summaries?.[0]?.status === 'resolved').length,
        closed: data.filter(s => s.review_summaries?.[0]?.status === 'closed').length
    };
});
</script>

<template>
    <Head title="Reviewer - Assigned Submissions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Assigned Reviews
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
            <!-- Stats -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card v-for="(label, key) in { total: 'Total Assigned', open: 'Open Reviews', resolved: 'Completed', closed: 'Closed' }"
                    :key="key">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ label }}</CardTitle>
                        <component :is="key === 'open' ? AlertCircle : key === 'resolved' ? CheckCircle : key === 'closed' ? XCircle : MessageSquare"
                            class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div
                            :class="['text-2xl font-bold', key === 'open' ? 'text-green-600' : key === 'resolved' ? 'text-blue-600' : key === 'closed' ? 'text-red-600' : '']">
                            {{ submissionStats[key] }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ key === 'total' ? 'All assigned submissions' : key === 'open' ? 'Need your review' : key === 'resolved' ? 'Successfully reviewed' : 'Rejected submissions' }}
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
                    <div class="flex gap-4 items-end flex-wrap">
                        <div class="flex-1 min-w-[250px]">
                            <label class="text-sm font-medium mb-2 block">Search</label>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
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
                    <div v-if="props.submissions.data.length === 0" class="text-center py-12">
                        <MessageSquare class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">No Submissions Found</h3>
                        <p class="text-muted-foreground">No assigned submissions match your current filters.</p>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Form Title</TableHead>
                                    <TableHead>Submitter</TableHead>
                                    <TableHead>Review Status</TableHead>
                                    <TableHead>Submitted At</TableHead>
                                    <TableHead>Last Updated</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="submission in props.submissions.data" :key="submission.id">
                                    <TableCell class="font-medium">
                                        {{ submission.form.title }}
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ submission.submitted_by.name }}</div>
                                            <div class="text-sm text-muted-foreground">{{ submission.submitted_by.email }}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge v-if="submission.review_summaries?.[0]"
                                            :class="getStatusInfo(submission.review_summaries[0].status).color">
                                            <component :is="getStatusInfo(submission.review_summaries[0].status).icon"
                                                class="h-3 w-3 mr-1" />
                                            {{ getStatusInfo(submission.review_summaries[0].status).label }}
                                        </Badge>
                                        <Badge v-else variant="outline">No Review</Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ formatDate(submission.created_at) }}
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ submission.review_summaries?.[0]?.updated_at
                                            ? formatDate(submission.review_summaries[0].updated_at)
                                            : '-' }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('reviewer.submissions.show', submission.id)">
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
                                Showing {{ props.submissions.meta.from || 0 }} to {{ props.submissions.meta.to || 0 }}
                                of {{ props.submissions.meta.total }} results
                            </div>

                            <div class="flex gap-2">
                                <Link
                                    v-for="link in props.submissions.meta.links"
                                    :key="link.label"
                                    :href="link.url ?? ''"
                                    v-html="link.label"
                                    :class="[
                                        'px-3 py-1.5 text-sm border rounded-md',
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-background hover:bg-muted border-border',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
