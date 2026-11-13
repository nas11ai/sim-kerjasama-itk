<script setup lang="ts">
// Frame: REVIEWER-03-02
import { Head, Link } from '@inertiajs/vue3'
import { ArrowRight, History } from 'lucide-vue-next'
import { computed } from 'vue'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import FilterSelect from '@/Components/app/FilterSelect.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDate } from '@/lib/format'
import type { BadgeVariant } from '@/types/BadgeVariant'
import type { HistoryPageProps, ReviewHistoryRow } from '@/types/review'

const props = defineProps<HistoryPageProps>()

const { filters } = useFilters('reviewer.history.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

// Skor dan tipe konteks tidak digambar: keduanya tidak tersimpan (W22).
const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'scheme', label: 'Skema' },
    { key: 'context', label: 'Konteks' },
    { key: 'completedAt', label: 'Selesai' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]

const variants: Record<ReviewHistoryRow['status'], BadgeVariant> = {
    completed: 'secondary',
    pending: 'default',
    replaced: 'outline',
}
</script>

<template>
    <Head title="Riwayat Penilaian" />
    <AppLayout>
        <PageHeader
            title="Riwayat Penilaian"
            subtitle="Semua evaluasi yang pernah Anda kerjakan, read-only, termasuk siklus monev sebelumnya."
        />
        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <Input
                v-model="filters.search"
                type="search"
                placeholder="Cari judul submission..."
                aria-label="Cari judul submission"
                class="sm:max-w-xs"
            />
            <FilterSelect
                v-model="filters.year"
                :options="yearOptions"
                all-label="Semua tahun"
                label="Tahun"
            />
        </div>
        <DataTable :rows="history.data" :columns="columns">
            <template #cell-title="{ row }">
                <span class="font-medium">{{ row.title }}</span>
            </template>
            <template #cell-scheme="{ row }">{{ row.scheme ?? '-' }}</template>
            <template #cell-context="{ row }">{{ row.context ?? '-' }}</template>
            <template #cell-completedAt="{ row }">{{ formatDate(row.completedAt) }}</template>
            <template #cell-status="{ row }">
                <Badge :variant="variants[row.status]">{{ row.statusLabel }}</Badge>
            </template>
            <template #cell-action="{ row }">
                <!-- BR-REV-10 — penugasan yang digantikan tidak membuka ringkasan lagi -->
                <Button v-if="row.summaryUrl" as-child variant="ghost" size="sm">
                    <Link :href="row.summaryUrl">Lihat <ArrowRight class="size-4" /></Link>
                </Button>
            </template>
            <template #empty>
                <EmptyState
                    :icon="History"
                    :title="
                        filtered
                            ? 'Tidak ada riwayat yang cocok dengan filter'
                            : 'Belum ada riwayat review'
                    "
                />
            </template>
        </DataTable>
        <PaginationBar :page="history" />
    </AppLayout>
</template>
