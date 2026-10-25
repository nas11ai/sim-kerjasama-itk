<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { formatRupiah } from '@/lib/format'
import type { SubmissionBudget } from '@/types/submission-page'

const props = defineProps<{ budget: SubmissionBudget }>()

// BR-BUD-02 — terkunci bagi siapa pun; BR-BUD-09 — anggota biasa hanya membaca.
const lockLabels = { terminal: 'Terkunci — hanya baca', read_only: 'Hanya baca' }

const summary = computed(() => [
    { label: 'Total Anggaran', value: formatRupiah(props.budget.total) },
    {
        label: 'Status Anggaran',
        value: props.budget.lockReason ? lockLabels[props.budget.lockReason] : 'Dapat diubah',
    },
])
</script>

<template>
    <SectionCard title="Rencana Anggaran">
        <InfoGrid :items="summary" />
        <div v-if="budget.items.length > 0" class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Komponen</TableHead>
                        <TableHead>Item</TableHead>
                        <TableHead class="text-right">Volume</TableHead>
                        <TableHead>Satuan</TableHead>
                        <TableHead class="text-right">Harga Satuan</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(line, index) in budget.items" :key="index">
                        <TableCell>{{ line.component }}</TableCell>
                        <TableCell>{{ line.item }}</TableCell>
                        <TableCell class="text-right">{{ line.volume }}</TableCell>
                        <TableCell>{{ line.unit }}</TableCell>
                        <TableCell class="text-right">{{ formatRupiah(line.unitPrice) }}</TableCell>
                        <TableCell class="text-right">{{ formatRupiah(line.total) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <EmptyState v-else title="Belum ada rencana anggaran" />
        <p class="text-xs text-muted-foreground">
            Anggaran terkunci otomatis setelah pengajuan disetujui, ditolak, atau ditarik.
        </p>
    </SectionCard>
</template>
