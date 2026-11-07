<script setup lang="ts">
// Frame: RESEARCHER-05-03
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import AnswerList from '@/Components/Submission/AnswerList.vue'
import FilesSection from '@/Components/Submission/FilesSection.vue'
import ReviewThreadCard from '@/Components/Submission/Revision/ReviewThreadCard.vue'
import ScoreSection from '@/Components/Submission/ScoreSection.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDate } from '@/lib/format'
import type { MonevReportShowPageProps } from '@/types/monev'

const props = defineProps<MonevReportShowPageProps>()

const subtitle = computed(() =>
    [props.report.title, props.report.scheme].filter((part) => part !== null).join(' · ')
)
// W131 — tanpa "· tepat waktu": tidak ada satu sumber untuk menilainya.
const info = computed(() => [{ label: 'Disubmit', value: formatDate(props.cycle.submittedAt) }])
</script>

<template>
    <Head :title="`Detail Laporan ${report.cycleName}`" />
    <AppLayout>
        <PageHeader
            :title="`Detail Laporan ${report.cycleName}`"
            :subtitle="subtitle"
            :back-href="route('monev.index')"
            back-label="Daftar Monev"
        >
            <template #badge><StatusBadge :status="status" /></template>
        </PageHeader>
        <div class="flex flex-col gap-4">
            <!-- BR-MON-08 -->
            <AppBanner v-if="parentWithdrawn" tone="lock">
                Pengajuan ini sudah ditarik. Laporan yang sudah dikirim tetap tersimpan sebagai
                arsip.
            </AppBanner>
            <AppBanner v-if="can.fill" tone="info">
                Laporan ini masih draft.
                <Link
                    :href="route('monev.reports.edit', report.id)"
                    class="font-medium text-primary underline-offset-4 hover:underline"
                >
                    Lanjutkan Laporan
                </Link>
            </AppBanner>
            <div class="grid gap-4 lg:grid-cols-2">
                <SectionCard title="Laporan (Read-only)">
                    <InfoGrid :items="info" />
                    <AnswerList :answers="answers" />
                </SectionCard>
                <div class="flex flex-col gap-4">
                    <FilesSection :answers="answers" />
                    <!-- BR-MON-09 -->
                    <ScoreSection
                        :scores="scores"
                        note="Evaluasi tampil setelah reviewer submit."
                    />
                </div>
            </div>
            <SectionCard title="Diskusi">
                <ReviewThreadCard
                    v-for="thread in threads"
                    :key="thread.id"
                    :thread="thread"
                    :can-reply="can.reply"
                    :can-resolve="can.resolveThreads"
                />
                <EmptyState v-if="threads.length === 0" title="Belum ada diskusi." />
            </SectionCard>
        </div>
    </AppLayout>
</template>
