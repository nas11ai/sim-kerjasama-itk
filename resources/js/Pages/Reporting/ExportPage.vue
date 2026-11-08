<script setup lang="ts">
// Frame: OPERATOR-07-02
import { Head, router, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import ExportHistoryTable from '@/Components/Reporting/ExportHistoryTable.vue'
import ExportRequestCard from '@/Components/Reporting/ExportRequestCard.vue'
import { toast } from '@/Components/ui/toast'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ExportJobRow, ExportPageProps } from '@/types/reporting'

const props = defineProps<ExportPageProps>()

// UX pattern 07 — polling riwayat hanya selama ada ekspor yang masih berjalan.
const poll = usePoll(10_000, { only: ['jobs'] }, { autoStart: false })
const running = computed(() =>
    props.jobs.some((job) => job.status === 'queued' || job.status === 'processing')
)
watch(running, (on) => (on ? poll.start() : poll.stop()), { immediate: true })

// Coba Lagi / Ekspor Ulang = permintaan baru dengan jenis, format, dan filter yang sama.
// E2E-02 — baris ini tak punya field untuk galat validasi (mis. skemanya sudah dihapus),
// jadi galatnya ditampilkan lewat toast, kanal yang sama dengan flash error.
const rerun = (job: ExportJobRow) =>
    router.post(
        route('reporting.exports.store'),
        { type: job.type_value, format: job.format, ...job.filters },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast({
                    title: 'Ekspor tidak dapat dijalankan ulang',
                    description: Object.values(errors)[0],
                    variant: 'destructive',
                }),
        }
    )
const refreshLinks = () => router.reload({ only: ['jobs'] })
</script>

<template>
    <Head title="Export Data" />
    <AppLayout>
        <PageHeader
            title="Export Data"
            subtitle="Ekspor diproses di latar belakang — Anda diberi tahu saat file siap diunduh."
        />
        <div class="flex flex-col gap-6">
            <ExportRequestCard
                v-bind="options"
                :threshold="threshold"
                :initial-type="initialType"
            />
            <SectionCard title="Riwayat Export">
                <ExportHistoryTable
                    :jobs="jobs"
                    :max-attempts="maxAttempts"
                    @rerun="rerun"
                    @refresh-links="refreshLinks"
                />
            </SectionCard>
            <p class="text-xs text-muted-foreground">
                Link unduhan berlaku {{ retentionHours }} jam sejak file siap. Ekspor yang gagal
                dapat dijalankan ulang lewat tombol "Coba Lagi".
            </p>
        </div>
    </AppLayout>
</template>
