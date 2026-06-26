<!-- resources/js/Pages/Reviewer/Submissions/Index.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import {
    Search,
    FileText,
    User,
    Calendar,
    Star,
    Eye,
    CheckCircle,
    XCircle,
    Info,
    Filter,
    MessageSquare,
} from 'lucide-vue-next'

interface ReviewSummary {
    id: number
    status: string
}

interface Reviewer {
    id: number
    reviewer_role?: {
        id: number
        name: string
    }
}

interface Submission {
    id: number
    created_at: string
    updated_at: string
    status: string
    form: {
        id: number
        title: string
    }
    submitted_by: {
        id: number
        name: string
        email: string
    }
    review_summaries?: ReviewSummary[]
}

interface Props {
    submissions: {
        data: Submission[]
        links: {
            url: string | null
            label: string
            active: boolean
        }[]
        meta: {
            current_page: number
            from: number
            last_page: number
            per_page: number
            to: number
            total: number
        }
    }
    filters: {
        status?: string
        search?: string
    }
    reviewer: Reviewer
}

const props = defineProps<Props>()

const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')

const handleSearch = () => {
    router.get(
        route('reviewer.submissions.index'),
        {
            search: searchQuery.value,
            status: statusFilter.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const handleStatusChange = (value: unknown) => {
    statusFilter.value = (value ?? '').toString()
    handleSearch()
}

const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
    router.get(route('reviewer.submissions.index'))
}

const getStatusBadgeVariant = (
    status: string
): 'default' | 'destructive' | 'outline' | 'secondary' | 'success' | null | undefined => {
    const variants: Record<
        string,
        'default' | 'destructive' | 'outline' | 'secondary' | 'success'
    > = {
        open: 'default',
        resolved: 'secondary',
        closed: 'destructive',
        pending: 'outline',
        under_review: 'default',
        approved: 'secondary',
        rejected: 'destructive',
    }
    return variants[status] ?? 'outline'
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

// const applyFilters = () => {
//     const params: any = {}

//     if (searchQuery.value) params.search = searchQuery.value
//     if (statusFilter.value && statusFilter.value !== 'all') params.status = statusFilter.value

//     router.get(route('reviewer.submissions.index'), params, {
//         preserveState: true,
//         preserveScroll: true,
//     })
// }

const viewSubmission = (submissionId: number) => {
    router.visit(route('user.submissions.show', submissionId))
}

// Stats computation
// const submissionStats = computed(() => {
//     const data = props.submissions.data
//     return {
//         total: data.length,
//         // Gunakan submission.status, bukan review_summaries
//         pending: data.filter((s) => s.status === 'pending' || s.status === 'under_review').length,
//         approved: data.filter((s) => s.status === 'approved').length,
//         rejected: data.filter((s) => s.status === 'rejected').length,
//         // Jika tetap ingin menggunakan review_summaries (opsional)
//         open: data.filter((s) => s.review_summaries?.[0]?.status === 'open').length,
//         resolved: data.filter((s) => s.review_summaries?.[0]?.status === 'resolved').length,
//         closed: data.filter((s) => s.review_summaries?.[0]?.status === 'closed').length,
//     }
// })
</script>

<template>
    <Head title="Tugas Review – Pengajuan yang Ditugaskan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Tugas Review</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Pengajuan yang ditugaskan kepada Anda untuk ditinjau sebagai:
                        <span class="font-medium">
                            {{ reviewer.reviewer_role?.name || 'N/A' }}
                        </span>
                    </p>
                </div>
                <Badge variant="secondary" class="flex items-center gap-1">
                    <Star class="h-3 w-3" />
                    {{ reviewer.reviewer_role?.name }}
                </Badge>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Total Ditugaskan </CardTitle>
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ submissions.meta?.total ?? submissions.data.length }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Semua pengajuan yang ditugaskan kepada Anda
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Review Terbuka </CardTitle>
                        <Info class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{
                                props.submissions.data.filter(
                                    (s) => s.status === 'under_review' || s.status === 'pending'
                                ).length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">Membutuhkan review Anda</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Selesai </CardTitle>
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">
                            {{
                                props.submissions.data.filter((s) => s.status === 'approved').length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">Berhasil direview</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium"> Ditutup </CardTitle>
                        <XCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">
                            {{
                                props.submissions.data.filter((s) => s.status === 'rejected').length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">Pengajuan yang ditolak</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="mr-2 h-4 w-4" />
                        Filter Pengajuan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col gap-4 md:flex-row">
                        <div class="flex-1">
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Cari berdasarkan pengirim atau judul formulir..."
                                    class="pl-10"
                                    @keyup.enter="handleSearch"
                                />
                            </div>
                        </div>

                        <Select v-model="statusFilter" @update:model-value="handleStatusChange">
                            <SelectTrigger class="w-full md:w-[200px]">
                                <SelectValue placeholder="Filter by status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"> Semua status </SelectItem>
                                <SelectItem value="open"> Terbuka </SelectItem>
                                <SelectItem value="resolved"> Selesai </SelectItem>
                                <SelectItem value="closed"> Ditutup </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button class="md:w-auto" @click="handleSearch">
                            <Search class="mr-2 h-4 w-4" />
                            Cari
                        </Button>

                        <Button
                            v-if="searchQuery || statusFilter"
                            variant="outline"
                            @click="clearFilters"
                        >
                            Bersihkan
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Submissions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Pengajuan yang Ditugaskan</CardTitle>
                    <CardDescription>
                        Menampilkan {{ submissions.data.length }} dari
                        {{ submissions.meta?.total ?? submissions.data?.length ?? 0 }}
                        pengajuan
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="submissions.data.length > 0" class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Judul Formulir</TableHead>
                                    <TableHead>Pengirim</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tanggal Pengajuan</TableHead>
                                    <TableHead class="text-right"> Aksi </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="submission in submissions.data"
                                    :key="submission.id"
                                    class="hover:bg-muted/50"
                                >
                                    <TableCell>
                                        <div class="flex items-center">
                                            <FileText class="mr-2 h-4 w-4 text-gray-400" />
                                            {{ submission.form.title }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center">
                                            <User class="mr-2 h-4 w-4 text-gray-400" />
                                            <div>
                                                <div class="font-medium">
                                                    {{ submission.submitted_by.name }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ submission.submitted_by.email }}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadgeVariant(submission.status)">
                                            {{ submission.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center">
                                            <Calendar class="mr-2 h-4 w-4 text-gray-400" />
                                            {{ formatDate(submission.created_at) }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="viewSubmission(submission.id)"
                                        >
                                            <Eye class="mr-2 h-4 w-4" />
                                            Lihat Detail
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Menampilkan {{ submissions.meta?.from || 0 }} hingga
                            {{ submissions.meta?.to || 0 }} dari
                            {{ submissions.meta?.total || submissions.data?.length || 0 }}
                            hasil
                        </div>

                        <div class="flex gap-2">
                            <template v-for="link in submissions.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-2 text-sm border rounded-md"
                                    :class="[
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-background hover:bg-muted border-border',
                                    ]"
                                >
                                    {{ link.label }}
                                </Link>
                                <span
                                    v-else
                                    class="px-3 py-2 text-sm border rounded-md opacity-50 cursor-not-allowed"
                                >
                                    {{ link.label }}
                                </span>
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
