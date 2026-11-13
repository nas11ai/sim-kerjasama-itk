<script setup lang="ts">
// Frame: OPERATOR-09-01 — BR-REV-18, BR-REV-19, G2-W5
import { Head, router } from '@inertiajs/vue3'
import { ClipboardList } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PastDeadlineLinks from '@/Components/FormEngine/PastDeadlineLinks.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type {
    CohortEvaluationVersion,
    PeriodEvaluationVersionsPageProps,
} from '@/types/form-engine'

const props = defineProps<PeriodEvaluationVersionsPageProps>()

// DataTable butuh `id`; baris tidak punya kunci alami selain urutannya.
const rows = computed(() => props.rows.map((row, index) => ({ ...row, id: index })))

const columns = [
    { key: 'step', label: 'Tahap & Langkah' },
    { key: 'form', label: 'Formulir Evaluasi' },
    { key: 'version', label: 'Versi Angkatan' },
    { key: 'action', label: '' },
]

const target = ref<CohortEvaluationVersion | null>(null)
const confirming = computed({
    get: () => target.value !== null,
    set: (open: boolean) => {
        if (!open) target.value = null
    },
})
const processing = ref(false)
const refusals = ref<string[]>([])

const confirmText = (row: CohortEvaluationVersion) =>
    `Terapkan ${row.latestLabel} ke angkatan ini? ${row.responseCount} penilaian akan dibuka ulang dan penilainya diberi tahu.`

const apply = () => {
    const pinId = target.value?.pinId
    if (pinId === null || pinId === undefined) return
    router.post(
        route('admin.evaluation-form-pins.apply', pinId),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true
                refusals.value = []
            },
            onSuccess: () => (target.value = null),
            onError: (errors: Record<string, string>) => {
                refusals.value = Object.values(errors)
                target.value = null
            },
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <Head title="Versi Formulir Evaluasi" />
    <AppLayout>
        <PageHeader
            title="Versi Formulir Evaluasi"
            :subtitle="`Periode ${period.name}, versi lembar penilaian yang dipakai tiap angkatan.`"
            :back-href="route('admin.submission-periods.index')"
            back-label="Periode Pengajuan"
        />
        <div class="flex flex-col gap-4">
            <AppBanner v-for="refusal in refusals" :key="refusal" tone="warning">
                {{ refusal }}
            </AppBanner>
            <DataTable :rows="rows" :columns="columns">
                <template #cell-step="{ row }">
                    <span class="font-medium">{{ row.phaseTitle }}</span>
                    <span class="block text-sm text-muted-foreground">{{ row.stepLabel }}</span>
                </template>
                <template #cell-form="{ row }">{{ row.formTitle }}</template>
                <template #cell-version="{ row }">
                    <!-- BR-REV-18 — belum ada draf penilaian, jadi belum ada yang dipaku -->
                    <span v-if="row.pinnedLabel === null" class="text-sm text-muted-foreground"
                        >Belum mulai dinilai, memakai {{ row.latestLabel }}</span
                    >
                    <div v-else class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{{ row.pinnedLabel }}</Badge>
                        <span
                            v-if="row.pinnedLabel === row.latestLabel"
                            class="text-sm text-muted-foreground"
                            >Memakai versi terbaru</span
                        >
                        <span v-else class="text-sm text-muted-foreground"
                            >Terbaru: {{ row.latestLabel }} ·
                            {{ row.responseCount }} penilaian</span
                        >
                    </div>
                    <!-- K-3 (b) — tenggat tidak digeser; perpanjangan diberi per pengajuan -->
                    <p
                        v-if="row.pinnedLabel !== null && row.pastDeadline.length > 0"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Lewat Batas Evaluasi tanpa perpanjangan:
                        <PastDeadlineLinks :submissions="row.pastDeadline" />
                    </p>
                </template>
                <template #cell-action="{ row }">
                    <div class="flex justify-end">
                        <!-- BR-REV-19 — keputusan yang sudah diambil tidak dibatalkan -->
                        <p
                            v-if="
                                row.pinnedLabel !== null &&
                                row.decidedCount > 0 &&
                                row.pinnedLabel !== row.latestLabel
                            "
                            class="max-w-xs text-right text-sm text-muted-foreground"
                        >
                            Tidak bisa diterapkan: {{ row.decidedCount }} pengajuan di langkah ini
                            sudah diputuskan.
                        </p>
                        <Button
                            v-else-if="row.canApply"
                            size="sm"
                            variant="outline"
                            @click="target = row"
                            >Terapkan {{ row.latestLabel }}</Button
                        >
                    </div>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="ClipboardList"
                        title="Belum ada Formulir Evaluasi di tahap periode ini"
                    />
                </template>
            </DataTable>
        </div>
    </AppLayout>
    <ConfirmDialog
        v-model:open="confirming"
        title="Terapkan versi baru?"
        :description="target === null ? '' : confirmText(target)"
        :confirm-label="`Terapkan ${target?.latestLabel ?? ''}`"
        :processing="processing"
        @confirm="apply"
    >
        <AppBanner v-if="target !== null && target.pastDeadline.length > 0" tone="warning">
            {{ target.pastDeadline.length }} pengajuan sudah lewat Batas Evaluasi. Penilainya baru
            bisa mengisi ulang setelah diberi perpanjangan di halaman pengajuan:
            <PastDeadlineLinks :submissions="target.pastDeadline" />
        </AppBanner>
    </ConfirmDialog>
</template>
