<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CalendarClock, ClipboardList, ListChecks, Pencil, Trash2, X } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDateTime } from '@/lib/format'
import type { PeriodRowData } from '@/types/form-engine'

defineProps<{ rows: PeriodRowData[]; filtered: boolean; canViewSubmissions: boolean }>()
const emit = defineEmits<{
    edit: [row: PeriodRowData]
    close: [row: PeriodRowData]
    delete: [row: PeriodRowData]
}>()

const columns = [
    { key: 'name', label: 'Nama Periode' },
    { key: 'dates', label: 'Tanggal Penting' },
    { key: 'submitted', label: 'Pengajuan Masuk' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <!-- BR-FE-01 — setiap tanggal adalah batas akhir; yang sudah lewat diredupkan -->
        <template #cell-dates="{ row }">
            <ul v-if="row.dates.length" class="flex flex-col gap-0.5 text-sm">
                <li
                    v-for="date in row.dates"
                    :key="date.id"
                    :class="{ 'text-muted-foreground': date.passed }"
                >
                    {{ date.label }}: {{ formatDateTime(date.at) }}
                </li>
            </ul>
            <span v-else class="text-muted-foreground">-</span>
        </template>
        <!-- W69 — induk yang terkirim, sama dengan Semua Pengajuan -->
        <template #cell-submitted="{ row }">
            <Link
                v-if="canViewSubmissions"
                :href="route('admin.submissions.index', { period: row.id })"
                class="underline-offset-4 hover:underline"
                >{{ row.submittedCount }}</Link
            >
            <span v-else>{{ row.submittedCount }}</span>
        </template>
        <template #cell-status="{ row }">
            <div class="flex flex-col items-start gap-1">
                <Badge :variant="row.status.value === 'active' ? 'success' : 'outline'">{{
                    row.status.label
                }}</Badge>
                <!-- BR-FE-36 — angkatan yang punya isu memblokir; `pending` sudah menerima pengajuan -->
                <Badge v-if="row.unreadyCohortCount > 0" variant="destructive"
                    >{{ row.unreadyCohortCount }} angkatan belum siap</Badge
                >
            </div>
        </template>
        <template #cell-action="{ row }">
            <div class="flex justify-end gap-2">
                <!-- BR-REV-19, G2-W5 — versi Form Evaluasi tiap angkatan periode ini -->
                <Button as-child size="sm" variant="ghost">
                    <Link :href="route('admin.submission-periods.evaluation-versions', row.id)"
                        ><ClipboardList class="size-4" />Versi Form Evaluasi</Link
                    >
                </Button>
                <!-- BR-FE-36 — daftar cek kesiapan tiap angkatan periode ini -->
                <Button as-child size="sm" variant="ghost">
                    <Link :href="route('admin.submission-periods.readiness', row.id)"
                        ><ListChecks class="size-4" />Kesiapan</Link
                    >
                </Button>
                <Button size="sm" variant="outline" @click="emit('edit', row)"
                    ><Pencil class="size-4" />Edit</Button
                >
                <!-- Close Early — hanya periode yang masih terbuka (W68) -->
                <Button v-if="row.closable" size="sm" variant="outline" @click="emit('close', row)"
                    ><X class="size-4" />Tutup</Button
                >
                <Button size="sm" variant="ghost" @click="emit('delete', row)"
                    ><Trash2 class="size-4" />Hapus</Button
                >
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="CalendarClock"
                :title="filtered ? 'Tidak ada periode yang cocok' : 'Belum ada periode pengajuan'"
            />
        </template>
    </DataTable>
</template>
