<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { FileText, Pencil, Power, Trash2 } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { FormRowData } from '@/types/form-engine'

defineProps<{ rows: FormRowData[]; filtered: boolean }>()
const emit = defineEmits<{
    'toggle-active': [row: FormRowData]
    delete: [row: FormRowData]
}>()

const columns = [
    { key: 'title', label: 'Nama Form' },
    { key: 'type', label: 'Tipe' },
    { key: 'fields', label: 'Field Aktif' },
    { key: 'submissions', label: 'Pengajuan Aktif' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }">
            <span class="font-medium">{{ row.title }}</span>
            <!-- BR-FE-34 — Form yang belum pernah terbit belum bisa dipakai langkah tahap -->
            <Badge v-if="!row.isPublished" variant="outline" class="ml-2">Belum terbit</Badge>
        </template>
        <template #cell-type="{ row }">{{ row.typeName }}</template>
        <template #cell-fields="{ row }">{{ row.fieldCount }}</template>
        <!-- W81 — tanpa REJECTED/WITHDRAWN; penghalang hapus (semua status) dibaca dialog -->
        <template #cell-submissions="{ row }">{{ row.activeSubmissionCount }}</template>
        <template #cell-status="{ row }">
            <Badge :variant="row.isActive ? 'success' : 'outline'">{{
                row.isActive ? 'Aktif' : 'Nonaktif'
            }}</Badge>
        </template>
        <template #cell-action="{ row }">
            <div class="flex justify-end gap-2">
                <Button as-child size="sm" variant="outline">
                    <Link :href="route('admin.forms.edit', row.id)"
                        ><Pencil class="size-4" />Edit</Link
                    >
                </Button>
                <Button size="sm" variant="outline" @click="emit('toggle-active', row)"
                    ><Power class="size-4" />{{ row.isActive ? 'Nonaktifkan' : 'Aktifkan' }}</Button
                >
                <Button size="sm" variant="ghost" @click="emit('delete', row)"
                    ><Trash2 class="size-4" />Hapus</Button
                >
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="FileText"
                :title="filtered ? 'Tidak ada form yang cocok' : 'Belum ada form'"
            />
        </template>
    </DataTable>
</template>
