<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowRight, FileText } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { SubmissionRow } from '@/types/submission-page'

defineProps<{ rows: SubmissionRow[]; filtered: boolean }>()

const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'scheme', label: 'Skema' },
    { key: 'status', label: 'Status' },
    { key: 'submittedAt', label: 'Tanggal Kirim' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }">
            <Link
                :href="route('user.submissions.show', row.id)"
                class="font-medium hover:underline"
            >
                {{ row.title }}
            </Link>
            <!-- BR-SM-04 — hanya ketua yang mengubah dan mengirim -->
            <Badge
                v-if="!row.isLead"
                variant="outline"
                class="ml-2"
                title="Anda anggota tim pada pengajuan ini, hanya ketua yang dapat mengubah."
            >
                Anggota Tim
            </Badge>
        </template>
        <template #cell-scheme="{ row }">{{ row.scheme ?? '-' }}</template>
        <template #cell-status="{ row }">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="row.status" />
                <!-- BR-SM-10 — draft periode tertutup menjadi read-only -->
                <Badge
                    v-if="row.isArchived"
                    variant="outline"
                    title="Draf dari periode yang sudah ditutup, tidak dapat diubah lagi."
                >
                    Diarsipkan
                </Badge>
            </div>
        </template>
        <template #cell-submittedAt="{ row }">{{ formatDate(row.submittedAt) }}</template>
        <template #cell-action="{ row }">
            <Button as-child variant="ghost" size="sm">
                <Link :href="route('user.submissions.show', row.id)">
                    Detail
                    <ArrowRight class="size-4" />
                </Link>
            </Button>
        </template>
        <template #empty>
            <EmptyState
                :icon="FileText"
                :title="
                    filtered
                        ? 'Tidak ada pengajuan yang cocok dengan filter'
                        : 'Belum ada pengajuan'
                "
                :description="
                    filtered
                        ? undefined
                        : 'Pengajuan yang Anda ketuai atau ikuti sebagai anggota tim tampil di sini.'
                "
            />
        </template>
    </DataTable>
</template>
