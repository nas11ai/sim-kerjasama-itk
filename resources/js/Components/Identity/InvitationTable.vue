<script setup lang="ts">
import { MailPlus, RotateCcw, X } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { InvitationRowData } from '@/types/identity'

// BR-IAM-06 — status menyebut sebabnya; baris dicabut tetap terbaca (IAM-INVITE-06).
defineProps<{ rows: InvitationRowData[] }>()
const emit = defineEmits<{ revoke: [row: InvitationRowData]; refill: [row: InvitationRowData] }>()

const columns = [
    { key: 'organization', label: 'Organisasi' },
    { key: 'preset', label: 'Hak Akses' },
    { key: 'usage', label: 'Terpakai / Kuota' },
    { key: 'expiresAt', label: 'Berlaku s.d.' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-organization="{ row }">
            <span class="font-medium">{{ row.organization ?? '-' }}</span>
        </template>
        <template #cell-preset="{ row }">{{ row.preset?.label ?? '-' }}</template>
        <!-- BR-IAM-07 — token multi-pakai dengan kuota (IAM-REG-09) -->
        <template #cell-usage="{ row }">{{ row.usedCount }} / {{ row.maxUses ?? '∞' }}</template>
        <template #cell-expiresAt="{ row }">{{ formatDate(row.expiresAt) }}</template>
        <template #cell-status="{ row }">
            <Badge :variant="row.status.value === 'valid' ? 'success' : 'destructive'">{{
                row.status.label
            }}</Badge>
        </template>
        <template #cell-action="{ row }">
            <div class="flex justify-end">
                <Button
                    v-if="row.status.value === 'valid' && row.revocable"
                    size="sm"
                    variant="outline"
                    @click="emit('revoke', row)"
                    ><X class="size-4" />Cabut</Button
                >
                <Button v-else size="sm" variant="outline" @click="emit('refill', row)"
                    ><RotateCcw class="size-4" />Buat Ulang</Button
                >
            </div>
        </template>
        <template #empty>
            <EmptyState :icon="MailPlus" title="Belum ada undangan yang diterbitkan" />
        </template>
    </DataTable>
</template>
