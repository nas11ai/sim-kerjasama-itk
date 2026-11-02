<script setup lang="ts">
import { ClipboardCheck } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { formatDate } from '@/lib/format'
import type { UserReviewRowData } from '@/types/identity'

// BR-REV-10 — penugasan yang sudah diganti tetap riwayat, jadi ikut tampil.
defineProps<{ rows: UserReviewRowData[] }>()

const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'assignmentStatus', label: 'Penugasan' },
    { key: 'evaluationStatus', label: 'Evaluasi' },
    { key: 'assignedAt', label: 'Ditugaskan' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }"
            ><span class="font-medium">{{ row.title }}</span></template
        >
        <template #cell-assignmentStatus="{ row }">
            <Badge variant="outline">{{ row.assignmentStatus.label }}</Badge>
        </template>
        <template #cell-evaluationStatus="{ row }">
            <Badge variant="secondary">{{ row.evaluationStatus.label }}</Badge>
        </template>
        <template #cell-assignedAt="{ row }">{{ formatDate(row.assignedAt) }}</template>
        <template #empty>
            <EmptyState :icon="ClipboardCheck" title="Belum pernah ditugaskan sebagai reviewer." />
        </template>
    </DataTable>
</template>
