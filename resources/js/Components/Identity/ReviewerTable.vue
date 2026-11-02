<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { BookOpen, Clock, Pencil, UserSearch } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { ReviewerRowData, ReviewerStanding } from '@/types/identity'

// BR-IAM-05 — akun nonaktif tak bisa diubah masa tugasnya; penugasannya diganti Operator.
defineProps<{ rows: ReviewerRowData[]; filtered: boolean }>()
const emit = defineEmits<{ revise: [row: ReviewerRowData] }>()

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'type', label: 'Tipe' },
    { key: 'tenure', label: 'Periode Aktif' },
    { key: 'load', label: 'Beban Tugas' },
    { key: 'standing', label: 'Status' },
    { key: 'action', label: '' },
]

const variants: Record<ReviewerStanding, 'success' | 'secondary' | 'outline' | 'destructive'> = {
    active: 'success',
    not_started: 'secondary',
    ended: 'outline',
    account_inactive: 'destructive',
}
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-type="{ row }">{{ row.type.label }}</template>
        <template #cell-tenure="{ row }"
            >{{ formatDate(row.tenureStart) }} – {{ formatDate(row.tenureEnd) }}</template
        >
        <!-- W54 — penugasan aktif yang evaluasinya belum beres -->
        <template #cell-load="{ row }">{{
            row.runningAssignments > 0 ? `${row.runningAssignments} aktif` : '0'
        }}</template>
        <template #cell-standing="{ row }">
            <Badge :variant="variants[row.standing.value]">{{ row.standing.label }}</Badge>
        </template>
        <template #cell-action="{ row }">
            <div class="flex justify-end gap-2">
                <Button
                    v-if="row.standing.value === 'ended'"
                    size="sm"
                    variant="outline"
                    @click="emit('revise', row)"
                    ><Clock class="size-4" />Perpanjang</Button
                >
                <Button
                    v-else-if="row.standing.value !== 'account_inactive'"
                    size="sm"
                    variant="outline"
                    @click="emit('revise', row)"
                    ><Pencil class="size-4" />Edit Periode</Button
                >
                <Button v-if="row.historyUrl" as-child size="sm" variant="ghost">
                    <Link :href="row.historyUrl"><BookOpen class="size-4" />Riwayat</Link>
                </Button>
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="UserSearch"
                :title="filtered ? 'Tidak ada reviewer yang cocok' : 'Belum ada reviewer'"
            />
        </template>
    </DataTable>
</template>
