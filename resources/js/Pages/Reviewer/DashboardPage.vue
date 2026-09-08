<script setup lang="ts">
import { computed } from 'vue'
import { route } from 'ziggy-js'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { ArrowRight, CheckCircle2, ClipboardList, Clock, Inbox, UserCheck } from 'lucide-vue-next'

interface Assignment {
    id: number
    evaluation_status: string | null
    form_submission: {
        id: number
        created_at: string | null
        form: { id: number; title: string } | null
        submitted_by: { id: number; name: string; email: string } | null
        form_phase_detail: { id: number; phase_type: { id: number; name: string } | null } | null
    } | null
}

const props = defineProps<{
    reviewer: {
        id: number
        reviewer_type: string | null
        user: { name: string | null; email: string | null }
    }
    stats: {
        total_assigned: number
        pending_reviews: number
        completed_reviews: number
    }
    assignments: Assignment[]
}>()

// Sejajar dengan SubmissionReviewer::SETTLED_EVALUATION di backend.
const SELESAI = ['completed', 'not_required']

const isSettled = (assignment: Assignment) =>
    assignment.evaluation_status !== null && SELESAI.includes(assignment.evaluation_status)

const pending = computed(() => props.assignments.filter((a) => !isSettled(a)))

const summaryCards = computed(() => [
    {
        label: 'Total penugasan',
        value: props.stats.total_assigned,
        icon: ClipboardList,
        tone: 'text-foreground',
    },
    {
        label: 'Menunggu penilaian',
        value: props.stats.pending_reviews,
        icon: Clock,
        tone: 'text-amber-600',
    },
    {
        label: 'Sudah dinilai',
        value: props.stats.completed_reviews,
        icon: CheckCircle2,
        tone: 'text-emerald-600',
    },
])

const phaseLabel = (assignment: Assignment) =>
    assignment.form_submission?.form_phase_detail?.phase_type?.name ?? 'Tahap tidak diketahui'

const formatDate = (value: string | null | undefined) => {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}
</script>

<template>
    <Head title="Dasbor Reviewer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dasbor Reviewer</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Card>
                    <CardHeader class="flex flex-row items-center gap-3">
                        <UserCheck class="h-5 w-5 text-muted-foreground" />
                        <div>
                            <CardTitle>{{ reviewer.user.name ?? 'Reviewer' }}</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ reviewer.user.email ?? '—' }}
                                <span v-if="reviewer.reviewer_type">
                                    · {{ reviewer.reviewer_type }}
                                </span>
                            </p>
                        </div>
                    </CardHeader>
                </Card>

                <div class="grid gap-4 sm:grid-cols-3">
                    <Card v-for="card in summaryCards" :key="card.label">
                        <CardContent class="flex items-center justify-between pt-6">
                            <div>
                                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                                <p class="mt-1 text-2xl font-semibold" :class="card.tone">
                                    {{ card.value }}
                                </p>
                            </div>
                            <component :is="card.icon" class="h-8 w-8 text-muted-foreground/40" />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Penugasan Aktif
                            <Badge v-if="pending.length > 0" variant="secondary" class="ml-2">
                                {{ pending.length }} menunggu
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="assignments.length === 0"
                            class="flex flex-col items-center gap-2 rounded-md border border-dashed p-10 text-center"
                        >
                            <Inbox class="h-8 w-8 text-muted-foreground/50" />
                            <p class="text-sm text-muted-foreground">
                                Belum ada pengajuan yang ditugaskan kepada Anda.
                            </p>
                        </div>

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pengajuan</TableHead>
                                    <TableHead>Pengaju</TableHead>
                                    <TableHead>Tahap</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="w-28 text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="assignment in assignments" :key="assignment.id">
                                    <TableCell>
                                        <div class="font-medium">
                                            {{
                                                assignment.form_submission?.form?.title ??
                                                'Tanpa judul'
                                            }}
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ formatDate(assignment.form_submission?.created_at) }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            {{
                                                assignment.form_submission?.submitted_by?.name ??
                                                '—'
                                            }}
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{
                                                assignment.form_submission?.submitted_by?.email ??
                                                '—'
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ phaseLabel(assignment) }}</TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                isSettled(assignment) ? 'secondary' : 'default'
                                            "
                                        >
                                            {{ isSettled(assignment) ? 'Selesai' : 'Menunggu' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link
                                            v-if="assignment.form_submission"
                                            :href="
                                                route(
                                                    'reviewer.submissions.show',
                                                    assignment.form_submission.id
                                                )
                                            "
                                        >
                                            <Button variant="ghost" size="sm">
                                                Buka
                                                <ArrowRight class="ml-1 h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
