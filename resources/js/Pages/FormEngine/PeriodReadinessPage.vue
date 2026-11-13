<script setup lang="ts">
// Frame: OPERATOR-09-01 — BR-FE-36, G4-W8
import { Head } from '@inertiajs/vue3'
import { CircleAlert, CircleCheck, ListChecks, TriangleAlert } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import { Badge } from '@/Components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { CohortReadiness, PeriodReadinessPageProps, ReadinessCheck } from '@/types/form-engine'

defineProps<PeriodReadinessPageProps>()

type Status = CohortReadiness['status']

const statuses: Record<
    Status,
    {
        label: string
        note: string | null
        variant: 'success' | 'secondary' | 'destructive' | 'outline'
    }
> = {
    ready: { label: 'Siap', note: null, variant: 'success' },
    pending: {
        label: 'Siap, ada yang menyusul',
        note: 'Pengajuan baru diterima. Lengkapi sebelum langkahnya dimulai.',
        variant: 'secondary',
    },
    blocked: {
        label: 'Belum siap',
        note: 'Pengajuan baru di tahap ini ditolak sampai semua cek lolos.',
        variant: 'destructive',
    },
    needs_fix: {
        label: 'Perlu perbaikan',
        note: 'Angkatan ini sudah punya pengajuan, jadi tidak diblokir.',
        variant: 'outline',
    },
}

// Keadaan baris untuk pembaca layar; ikon dan warna saja tidak terbaca.
const stateLabels: Record<ReadinessCheck['state'], string> = {
    passed: 'Lolos',
    warning: 'Peringatan',
    failed: 'Gagal',
}

const icons: Record<ReadinessCheck['state'], { icon: typeof CircleCheck; tone: string }> = {
    passed: { icon: CircleCheck, tone: 'text-green-600' },
    warning: { icon: TriangleAlert, tone: 'text-amber-600' },
    failed: { icon: CircleAlert, tone: 'text-destructive' },
}
</script>

<template>
    <Head title="Kesiapan Angkatan" />
    <AppLayout>
        <PageHeader
            title="Kesiapan Angkatan"
            :subtitle="`Periode ${period.name}, daftar cek tiap tahap sebelum menerima pengajuan baru.`"
            :back-href="route('admin.submission-periods.index')"
            back-label="Periode Pengajuan"
        />
        <EmptyState
            v-if="cohorts.length === 0"
            :icon="ListChecks"
            title="Belum ada tahap di periode ini"
        />
        <div v-else class="flex flex-col gap-4">
            <Card v-for="cohort in cohorts" :key="cohort.phaseId" data-cohort>
                <CardHeader class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle>{{ cohort.phaseTitle }}</CardTitle>
                        <span v-if="cohort.versionLabel" class="text-sm text-muted-foreground">{{
                            cohort.versionLabel
                        }}</span>
                        <Badge :variant="statuses[cohort.status].variant" data-status-badge>{{
                            statuses[cohort.status].label
                        }}</Badge>
                    </div>
                    <p
                        v-if="statuses[cohort.status].note"
                        class="text-sm text-muted-foreground"
                        data-status-note
                    >
                        {{ statuses[cohort.status].note }}
                    </p>
                </CardHeader>
                <CardContent>
                    <ul class="flex flex-col gap-3">
                        <li
                            v-for="row in cohort.checks"
                            :key="row.check"
                            class="flex items-start gap-2"
                            data-check
                        >
                            <component
                                :is="icons[row.state].icon"
                                :class="['mt-0.5 size-4 shrink-0', icons[row.state].tone]"
                                :data-check-icon="row.state"
                                aria-hidden="true"
                            />
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-medium"
                                    >{{ row.title
                                    }}<span class="sr-only">{{
                                        ` ${stateLabels[row.state]}`
                                    }}</span></span
                                >
                                <!-- G4-W8 — yang dibutuhkan sekarang memblokir; yang nanti hanya diingatkan -->
                                <p
                                    v-for="issue in row.issues"
                                    :key="issue.message"
                                    :class="[
                                        'text-sm',
                                        issue.blocking
                                            ? 'text-destructive'
                                            : 'text-muted-foreground',
                                    ]"
                                    :data-issue="issue.blocking ? 'blocking' : 'warning'"
                                >
                                    {{ issue.message
                                    }}<span v-if="issue.blocking" class="sr-only">{{
                                        ' (memblokir)'
                                    }}</span>
                                </p>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
