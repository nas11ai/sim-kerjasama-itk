<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Check, Layers, Pencil, X } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatRupiah } from '@/lib/format'
import type { SchemeRowData } from '@/types/scheme'

defineProps<{ rows: SchemeRowData[]; filtered: boolean }>()
const emit = defineEmits<{
    deactivate: [row: SchemeRowData]
    activate: [row: SchemeRowData]
}>()

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'code', label: 'Kode' },
    { key: 'type', label: 'Tipe' },
    { key: 'budget', label: 'Anggaran Maks.' },
    { key: 'members', label: 'Anggota Maks.' },
    { key: 'duration', label: 'Durasi' },
    { key: 'submissions', label: 'Pengajuan Aktif' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-code="{ row }">{{ row.code }}</template>
        <template #cell-type="{ row }">{{ row.submissionType ?? '-' }}</template>
        <template #cell-budget="{ row }">{{ formatRupiah(row.maxBudget) }}</template>
        <template #cell-members="{ row }">{{ row.maxMembers }}</template>
        <template #cell-duration="{ row }">{{ row.durationMonths }} bln</template>
        <template #cell-submissions="{ row }">{{ row.activeSubmissionCount }}</template>
        <template #cell-status="{ row }">
            <Badge :variant="row.isActive ? 'success' : 'outline'">{{
                row.isActive ? 'Aktif' : 'Nonaktif'
            }}</Badge>
        </template>
        <template #cell-action="{ row }">
            <div class="flex flex-col items-end gap-1">
                <div class="flex justify-end gap-2">
                    <Button as-child size="sm" variant="outline">
                        <Link :href="route('admin.schemes.edit', row.id)"
                            ><Pencil class="size-4" />Edit</Link
                        >
                    </Button>
                    <Button
                        v-if="row.isActive"
                        size="sm"
                        variant="outline"
                        :disabled="row.activeSubmissionCount > 0"
                        :aria-describedby="
                            row.activeSubmissionCount > 0 ? `scheme-lock-${row.id}` : undefined
                        "
                        @click="emit('deactivate', row)"
                        ><X class="size-4" />Nonaktifkan</Button
                    >
                    <Button v-else size="sm" variant="outline" @click="emit('activate', row)"
                        ><Check class="size-4" />Aktifkan</Button
                    >
                </div>
                <!-- BR-SCH-01 — angka penghalang dibaca dari backend, sebaris dengan tombolnya -->
                <p
                    v-if="row.isActive && row.activeSubmissionCount > 0"
                    :id="`scheme-lock-${row.id}`"
                    class="max-w-64 text-right text-xs text-muted-foreground"
                >
                    Masih ada {{ row.activeSubmissionCount }} pengajuan aktif yang memakai skema
                    ini. Selesaikan atau tarik pengajuan itu lebih dulu.
                </p>
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="Layers"
                :title="filtered ? 'Tidak ada skema yang cocok' : 'Belum ada skema'"
            />
        </template>
    </DataTable>
</template>
