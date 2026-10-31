<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { OutcomeConditions } from '@/types/review'
import type { SubmissionStatusView } from '@/types/submission-status'

const props = defineProps<{ outcome: OutcomeConditions; status: SubmissionStatusView }>()

// BR-REV-05/06 — keduanya harus terpenuhi bersamaan; yang belum disebut sebagai penahan.
const holds = computed(() => {
    const reasons: string[] = []
    if (props.outcome.unsettledCount > 0) {
        reasons.push(`${props.outcome.unsettledCount} evaluasi belum masuk`)
    }
    if (props.outcome.openThreadCount > 0) {
        reasons.push(`${props.outcome.openThreadCount} ringkasan masih terbuka`)
    }
    return reasons
})

const items = computed(() => [
    {
        label: 'Evaluasi',
        value: `${props.outcome.reviewerCount - props.outcome.unsettledCount}/${props.outcome.reviewerCount} evaluasi selesai`,
    },
    { label: 'Ringkasan', value: `${props.outcome.openThreadCount} ulir terbuka` },
])
</script>

<template>
    <SectionCard
        title="Syarat Keputusan"
        description="Reviewer yang tidak diwajibkan menilai dihitung selesai."
    >
        <InfoGrid :items="items" />
        <AppBanner v-if="holds.length > 0" tone="info">
            Status bertahan di {{ status.label }}: {{ holds.join(', dan ') }}.
        </AppBanner>
        <!-- BR-REV-07, BR-REV-14 — tidak ada kombinasi skor yang menolak otomatis -->
        <AppBanner tone="lock">
            Penolakan tidak tersedia di layar reviewer. Hanya Operator yang dapat menolak, lewat
            aksi tertulis.
        </AppBanner>
    </SectionCard>
</template>
