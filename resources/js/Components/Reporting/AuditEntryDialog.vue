<script setup lang="ts">
import { computed } from 'vue'

import InfoGrid from '@/Components/app/InfoGrid.vue'
import AuditChangeList from '@/Components/Reporting/AuditChangeList.vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { formatDateTime } from '@/lib/format'
import type { AuditEntryRow } from '@/types/reporting'

// BUD-AUDIT-02, RPT-AUDIT-06 — pelaku, waktu, dan nilai lama → baru. Hanya baca (BR-RPT-04).
// UX pattern 07 — IP hanya untuk Operator/Admin; entri tanpa request tetap "—" seperti tabel.
const props = defineProps<{ entry: AuditEntryRow | null; seesEverything: boolean }>()
const open = defineModel<boolean>('open', { required: true })

const items = computed(() => {
    const entry = props.entry
    if (entry === null) return []
    return [
        { label: 'Aksi', value: entry.action },
        { label: 'Subjek', value: entry.subject },
        { label: 'Pelaku', value: entry.actor },
        { label: 'Waktu', value: formatDateTime(entry.createdAt) },
        ...(props.seesEverything ? [{ label: 'IP Address', value: entry.ipAddress ?? '-' }] : []),
    ]
})
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="entry" class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Detail Aktivitas</DialogTitle>
                <DialogDescription>Entri audit bersifat permanen.</DialogDescription>
            </DialogHeader>
            <InfoGrid :items="items" />
            <h3 class="text-sm font-medium">Nilai lama → baru</h3>
            <AuditChangeList :old="entry.old" :new="entry.new" />
        </DialogContent>
    </Dialog>
</template>
