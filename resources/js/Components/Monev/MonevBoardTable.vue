<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowRight, Inbox } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { BOARD_STATUS } from '@/lib/monev'
import type { MonevBoardRow } from '@/types/monev'

defineProps<{ rows: MonevBoardRow[]; filtered: boolean }>()

const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'leader', label: 'Ketua' },
    { key: 'scheme', label: 'Skema' },
    { key: 'currentCycle', label: 'Siklus Berjalan' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }">
            <span class="font-medium">{{ row.title }}</span>
        </template>
        <template #cell-leader="{ row }">{{ row.leader }}</template>
        <template #cell-scheme="{ row }">{{ row.scheme ?? '—' }}</template>
        <template #cell-currentCycle="{ row }">{{ row.currentCycle ?? '—' }}</template>
        <template #cell-status="{ row }">
            <Badge :variant="BOARD_STATUS[row.status].variant" class="gap-1.5">
                <span
                    v-if="BOARD_STATUS[row.status].dot"
                    :class="['size-1.5 rounded-full', BOARD_STATUS[row.status].dot]"
                    aria-hidden="true"
                />
                {{ BOARD_STATUS[row.status].label }}
            </Badge>
        </template>
        <template #cell-action="{ row }">
            <Button as-child variant="ghost" size="sm">
                <Link :href="route('monev.manage.show', row.id)">
                    Kelola <ArrowRight class="size-4" />
                </Link>
            </Button>
        </template>
        <template #empty>
            <EmptyState
                :icon="Inbox"
                :title="
                    filtered
                        ? 'Tidak ada pengajuan yang cocok'
                        : 'Belum ada pengajuan dengan siklus monev'
                "
            />
        </template>
    </DataTable>
</template>
