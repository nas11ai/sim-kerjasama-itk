<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import DataTable from '@/Components/app/DataTable.vue'
import ExportJobActions from '@/Components/Reporting/ExportJobActions.vue'
import ExportJobStatus from '@/Components/Reporting/ExportJobStatus.vue'
import { hoursLeft, jobState } from '@/lib/exportJob'
import { formatDateTime } from '@/lib/format'
import type { ExportJobRow } from '@/types/reporting'

const props = defineProps<{ jobs: ExportJobRow[]; maxAttempts: number }>()
const emit = defineEmits<{ rerun: [job: ExportJobRow]; refreshLinks: [] }>()

const columns = [
    { key: 'type', label: 'Jenis' },
    { key: 'parameters', label: 'Parameter' },
    { key: 'status', label: 'Status' },
    { key: 'time', label: 'Waktu' },
    { key: 'actions', label: 'Aksi' },
]

// [RPT-EXPORT-04] — jam berjalan, supaya tautan yang basi saat layar dibiarkan terbuka
// langsung digambar ulang, tanpa menunggu klik yang berakhir 403.
const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => (timer = setInterval(() => (now.value = new Date()), 30_000)))
onBeforeUnmount(() => clearInterval(timer))

const rows = computed(() =>
    props.jobs.map((job) => ({
        id: job.id,
        job,
        state: jobState(job, now.value),
        hoursLeft: hoursLeft(job, now.value),
    }))
)
// Banner frame per keadaan yang perlu dijelaskan; hanya baris ekspor tabular (cetak punya halamannya).
const has = (...states: string[]) =>
    rows.value.some((r) => r.job.kind === 'export' && states.includes(r.state))
</script>

<template>
    <div class="flex flex-col gap-3">
        <AppBanner v-if="has('queued', 'processing')" tone="info">
            Ekspor dijadwalkan. Anda akan diberi tahu saat berkasnya siap, layar ini boleh ditutup.
        </AppBanner>
        <AppBanner v-if="has('linkExpired')" tone="warning">
            Tautan unduhan sudah kedaluwarsa. Minta tautan baru, berkasnya belum terhapus.
        </AppBanner>
        <AppBanner v-if="has('gone')" tone="denial">
            Berkas ekspor sudah dihapus setelah 24 jam. Jalankan ekspor ulang untuk mendapatkannya
            kembali.
        </AppBanner>
        <AppBanner v-if="has('failed')" tone="denial">
            Export gagal, coba lagi. Sistem tidak akan mengulang sendiri; jalankan ulang secara
            manual.
        </AppBanner>
        <DataTable :rows="rows" :columns="columns">
            <template #cell-type="{ row }">{{ row.job.type }}</template>
            <template #cell-parameters="{ row }">{{ row.job.parameters }}</template>
            <template #cell-status="{ row }"><ExportJobStatus :state="row.state" /></template>
            <template #cell-time="{ row }">{{
                row.job.requested_at ? formatDateTime(row.job.requested_at) : '-'
            }}</template>
            <template #cell-actions="{ row }">
                <ExportJobActions
                    :job="row.job"
                    :state="row.state"
                    :hours-left="row.hoursLeft"
                    :max-attempts="maxAttempts"
                    @rerun="emit('rerun', $event)"
                    @refresh-links="emit('refreshLinks')"
                />
            </template>
            <template #empty>Belum ada ekspor.</template>
        </DataTable>
    </div>
</template>
