<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import BudgetTable from '@/Components/Submission/BudgetTable.vue'
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
        <BudgetTable v-if="budget.items.length > 0" :items="budget.items" />
        <EmptyState v-else title="Belum ada rencana anggaran" />
        <p class="text-xs text-muted-foreground">
            Anggaran terkunci otomatis setelah pengajuan disetujui, ditolak, atau ditarik.
        </p>
    </SectionCard>
</template>
