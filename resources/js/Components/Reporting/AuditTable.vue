<script setup lang="ts">
import { History } from 'lucide-vue-next'
import { computed } from 'vue'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Button } from '@/Components/ui/button'
import { formatDateTime } from '@/lib/format'
import type { AuditEntryRow } from '@/types/reporting'

// RPT-AUDIT-02/03 — baris hanya punya "Lihat": append-only berarti aksi Hapus/Sunting tidak ada.
const props = defineProps<{ rows: AuditEntryRow[]; seesEverything: boolean }>()
const emit = defineEmits<{ view: [entry: AuditEntryRow] }>()

// UX pattern 07 — kolom IP hanya untuk Operator/Admin.
const columns = computed(() => [
    { key: 'createdAt', label: 'Waktu' },
    { key: 'subject', label: 'Subjek' },
    { key: 'action', label: 'Aksi' },
    { key: 'actor', label: 'Pelaku' },
    ...(props.seesEverything ? [{ key: 'ipAddress', label: 'IP Address' }] : []),
    { key: 'view', label: '' },
])
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-createdAt="{ row }">
            <span class="whitespace-nowrap">{{ formatDateTime(row.createdAt) }}</span>
        </template>
        <template #cell-subject="{ row }">
            <span class="font-medium">{{ row.subject }}</span>
        </template>
        <template #cell-action="{ row }">{{ row.action }}</template>
        <template #cell-actor="{ row }">{{ row.actor }}</template>
        <template #cell-ipAddress="{ row }">
            <span class="font-mono text-xs">{{ row.ipAddress ?? '-' }}</span>
        </template>
        <template #cell-view="{ row }">
            <Button variant="ghost" size="sm" @click="emit('view', row)">Lihat</Button>
        </template>
        <template #empty>
            <EmptyState :icon="History" title="Tidak ada aktivitas di periode ini" />
        </template>
    </DataTable>
</template>
