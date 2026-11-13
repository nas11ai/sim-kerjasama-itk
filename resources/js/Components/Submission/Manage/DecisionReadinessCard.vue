<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { roundProgressLabel } from '@/lib/reviewRound'
import type { DecisionReadiness } from '@/types/submission-manage'

const props = defineProps<{ readiness: DecisionReadiness }>()

const items = computed(() => [
    {
        label: 'Putaran',
        value:
            props.readiness.round === null
                ? 'Tanpa putaran penilaian.'
                : roundProgressLabel(props.readiness.round),
    },
    { label: 'Catatan', value: `${props.readiness.openThreadCount} catatan terbuka` },
])
</script>

<template>
    <!-- BR-REV-05, BR-REV-07 — Setujui dan Tolak menunggu putaran selesai (ADR 0018) -->
    <SectionCard title="Kesiapan Keputusan">
        <InfoGrid :items="items" />
        <AppBanner v-for="blocker in readiness.blockers" :key="blocker" tone="info">
            {{ blocker }}
        </AppBanner>
        <AppBanner v-if="readiness.ready && readiness.blockers.length === 0" tone="success">
            Siap disetujui atau ditolak.
        </AppBanner>
    </SectionCard>
</template>
