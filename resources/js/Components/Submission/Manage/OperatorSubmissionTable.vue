<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowRight, Inbox } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { OperatorSubmissionRow } from '@/types/submission-manage'

defineProps<{ rows: OperatorSubmissionRow[]; filtered: boolean }>()

const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'leadName', label: 'Ketua Peneliti' },
    { key: 'organization', label: 'Organisasi' },
    { key: 'scheme', label: 'Skema' },
    { key: 'status', label: 'Status' },
    { key: 'submittedAt', label: 'Dikirim' },
    { key: 'reviewerCount', label: 'Penilai' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }">
            <Link
                :href="route('admin.submissions.show', row.id)"
                class="font-medium hover:underline"
            >
                {{ row.title }}
            </Link>
        </template>
        <template #cell-leadName="{ row }">{{ row.leadName }}</template>
        <template #cell-organization="{ row }">{{ row.organization ?? '-' }}</template>
        <template #cell-scheme="{ row }">{{ row.scheme ?? '-' }}</template>
        <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell-submittedAt="{ row }">{{ formatDate(row.submittedAt) }}</template>
        <template #cell-reviewerCount="{ row }">{{ row.reviewerCount }}</template>
        <template #cell-action="{ row }">
            <Button as-child variant="ghost" size="sm">
                <Link :href="route('admin.submissions.show', row.id)">
                    Detail <ArrowRight class="size-4" />
                </Link>
            </Button>
        </template>
        <template #empty>
            <EmptyState
                :icon="Inbox"
                :title="
                    filtered
                        ? 'Tidak ada pengajuan yang cocok dengan filter.'
                        : 'Belum ada pengajuan terkirim.'
                "
            />
        </template>
    </DataTable>
</template>
