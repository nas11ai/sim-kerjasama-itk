<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { formatRupiah } from '@/lib/format'
import type { BudgetItem } from '@/types/submission-page'

// Tabel rencana anggaran untuk layar detail dan langkah 5; kolom aksi hanya bila slotnya diisi.
defineProps<{ items: BudgetItem[] }>()

const slots = defineSlots<{ actions?: (props: { item: BudgetItem }) => unknown }>()
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Komponen</TableHead>
                    <TableHead>Item</TableHead>
                    <TableHead class="text-right">Volume</TableHead>
                    <TableHead>Satuan</TableHead>
                    <TableHead class="text-right">Harga Satuan</TableHead>
                    <TableHead class="text-right">Total</TableHead>
                    <TableHead v-if="slots.actions"><span class="sr-only">Aksi</span></TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="line in items" :key="line.id">
                    <TableCell>{{ line.component }}</TableCell>
                    <TableCell>{{ line.item }}</TableCell>
                    <TableCell class="text-right">{{ line.volume }}</TableCell>
                    <TableCell>{{ line.unit }}</TableCell>
                    <TableCell class="text-right">{{ formatRupiah(line.unitPrice) }}</TableCell>
                    <TableCell class="text-right">{{ formatRupiah(line.total) }}</TableCell>
                    <TableCell v-if="slots.actions" class="text-right">
                        <slot name="actions" :item="line" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
