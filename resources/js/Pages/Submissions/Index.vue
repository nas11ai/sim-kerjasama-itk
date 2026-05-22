<!-- resources/js/Pages/Admin/Submissions/Index.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Search,
    Eye,
    Calendar,
    Users,
    CheckCircle,
    Clock,
    FileText,
    TrendingUp,
} from 'lucide-vue-next'

interface SubmissionDate {
    id: number
    datetime: string
    submission_date_label: {
        name: string
    }
}

interface SubmissionPeriod {
    id: number
    name: string
    created_at: string
    submission_dates: SubmissionDate[]
    total_submissions: number
    approved_submissions: number
    pending_review: number
}

interface Props {
    submissionPeriods: {
        data: SubmissionPeriod[]
        links: any[]
        meta: any
    }
    filters: {
        search?: string
    }
}

const props = defineProps<Props>()

const searchQuery = ref(props.filters.search || '')

const applySearch = () => {
    const params: Record<string, any> = {}

    if (searchQuery.value) {
        params.search = searchQuery.value
    }

    router.get(route('admin.submissions.index'), params, {
        preserveState: true,
        replace: true,
    })
}

const clearSearch = () => {
    searchQuery.value = ''

    router.get(
        route('admin.submissions.index'),
        {},
        {
            preserveState: true,
            replace: true,
        }
    )
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const getApprovalRate = (approved: number, total: number) => {
    if (total === 0) return 0
    return Math.round((approved / total) * 100)
}

const getStatusColor = (approved: number, pending: number, total: number) => {
    if (total === 0) return 'text-muted-foreground'
    const rate = (approved / total) * 100
    if (rate >= 80) return 'text-green-600'
    if (rate >= 60) return 'text-yellow-600'
    return 'text-red-600'
}
</script>

<template>
    <Head title="Semua Pengajuan - Admin" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Manajemen Pengajuan Formulir
                </h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Search -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Search class="h-5 w-5" />
                        Cari Periode Pengajuan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <Input
                                v-model="searchQuery"
                                placeholder="Cari berdasarkan nama periode..."
                                @keyup.enter="applySearch"
                            />
                        </div>
                        <Button @click="applySearch">
                            <Search class="h-4 w-4 mr-2" />
                            Cari
                        </Button>
                        <Button v-if="searchQuery" variant="outline" @click="clearSearch">
                            Bersihkan
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Periods -->
            <div v-if="props.submissionPeriods.data.length === 0" class="text-center py-12">
                <Card>
                    <CardContent class="pt-6">
                        <Calendar class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            Tidak Ada Periode Pengajuan Ditemukan
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{
                                searchQuery
                                    ? 'Tidak ada periode yang cocok dengan kriteria pencarian anda'
                                    : 'Belum ada periode pengajuan yang dibuat.'
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-2">
                <Card
                    v-for="period in props.submissionPeriods.data"
                    :key="period.id"
                    class="hover:shadow-lg transition-shadow"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg flex items-center gap-2">
                                    <Calendar class="h-5 w-5" />
                                    {{ period.name }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    Dibuat Pada {{ formatDate(period.created_at) }}
                                </CardDescription>
                            </div>
                            <Badge
                                :class="
                                    getStatusColor(
                                        period.approved_submissions,
                                        period.pending_review,
                                        period.total_submissions
                                    )
                                "
                                variant="outline"
                            >
                                {{
                                    getApprovalRate(
                                        period.approved_submissions,
                                        period.total_submissions
                                    )
                                }}% Disetujui
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div class="space-y-4">
                            <!-- Important Dates -->
                            <div v-if="period.submission_dates.length > 0">
                                <h4 class="font-medium text-sm mb-2 flex items-center gap-2">
                                    <Clock class="h-4 w-4" />
                                    Tanggal Penting
                                </h4>
                                <div class="grid gap-2">
                                    <div
                                        v-for="date in period.submission_dates.slice(0, 2)"
                                        :key="date.id"
                                        class="text-xs p-2 bg-muted/50 rounded flex items-center justify-between"
                                    >
                                        <span class="font-medium">{{
                                            date.submission_date_label.name
                                        }}</span>
                                        <span class="text-muted-foreground">{{
                                            formatDateTime(date.datetime)
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="period.submission_dates.length > 2"
                                        class="text-xs text-center text-muted-foreground"
                                    >
                                        +{{ period.submission_dates.length - 2 }} tanggal lainnya
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Grid -->
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center p-3 bg-blue-50 rounded-lg">
                                    <div
                                        class="text-xl font-bold text-blue-600 flex items-center justify-center gap-1"
                                    >
                                        {{ period.total_submissions }}
                                        <FileText class="h-4 w-4" />
                                    </div>
                                    <div class="text-xs text-blue-600">Total</div>
                                </div>

                                <div class="text-center p-3 bg-green-50 rounded-lg">
                                    <div
                                        class="text-xl font-bold text-green-600 flex items-center justify-center gap-1"
                                    >
                                        {{ period.approved_submissions }}
                                        <CheckCircle class="h-4 w-4" />
                                    </div>
                                    <div class="text-xs text-green-600">Disetujui</div>
                                </div>

                                <div class="text-center p-3 bg-yellow-50 rounded-lg">
                                    <div
                                        class="text-xl font-bold text-yellow-600 flex items-center justify-center gap-1"
                                    >
                                        {{ period.pending_review }}
                                        <Clock class="h-4 w-4" />
                                    </div>
                                    <div class="text-xs text-yellow-600">Pending</div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Progres Review</span>
                                    <span class="font-medium"
                                        >{{ period.approved_submissions }}/{{
                                            period.total_submissions
                                        }}</span
                                    >
                                </div>
                                <div class="w-full bg-muted rounded-full h-2">
                                    <div
                                        class="bg-green-600 h-2 rounded-full transition-all duration-300"
                                        :style="{
                                            width: `${getApprovalRate(period.approved_submissions, period.total_submissions)}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="flex justify-end pt-2">
                                <Link :href="route('admin.submissions.period', period.id)">
                                    <Button size="sm" :disabled="period.total_submissions === 0">
                                        <Eye class="h-4 w-4 mr-2" />
                                        Lihat Pengajuan
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="props.submissionPeriods.links.length > 3" class="flex justify-center">
                <nav class="flex items-center space-x-1">
                    <Link
                        v-for="link in props.submissionPeriods.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-2 text-sm font-medium rounded-md',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                            !link.url && 'opacity-50 cursor-not-allowed',
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
/>
