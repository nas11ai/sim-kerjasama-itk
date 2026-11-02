<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { FileText } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import type { UserSubmissionRowData } from '@/types/identity'

// IAM-DEACT-03 — riwayat tetap terbaca meski akunnya nonaktif; `url` null = belum terkirim
// atau penonton tak berwenang membuka detail operator.
defineProps<{ rows: UserSubmissionRowData[] }>()

const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'role', label: 'Peran' },
    { key: 'status', label: 'Status' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }">
            <Link v-if="row.url" :href="row.url" class="font-medium hover:underline">{{
                row.title
            }}</Link>
            <span v-else class="font-medium">{{ row.title }}</span>
        </template>
        <template #cell-role="{ row }">{{ row.role }}</template>
        <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #empty>
            <EmptyState :icon="FileText" title="Belum ada pengajuan." />
        </template>
    </DataTable>
</template>
