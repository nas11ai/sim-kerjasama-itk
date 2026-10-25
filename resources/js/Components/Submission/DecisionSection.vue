<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { formatDate } from '@/lib/format'
import type { SubmissionDecision } from '@/types/submission-page'

const props = defineProps<{ decision: SubmissionDecision }>()

const opening = computed(() =>
    props.decision.decidedAt
        ? `Pengajuan ini ditolak Operator LPPM pada ${formatDate(props.decision.decidedAt)}.`
        : 'Pengajuan ini ditolak Operator LPPM.'
)

const details = computed(() => [
    { label: 'Diputuskan oleh', value: props.decision.decidedBy ?? '—' },
    { label: 'Tanggal', value: formatDate(props.decision.decidedAt) },
])
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- BR-REV-07 — penolakan selalu keputusan manual, tidak pernah otomatis dari skor -->
        <AppBanner tone="denial">
            {{ opening }} Penolakan selalu merupakan keputusan manual — tidak pernah terjadi
            otomatis dari hasil penilaian reviewer.
        </AppBanner>
        <SectionCard title="Alasan Penolakan">
            <InfoGrid :items="details" />
            <p class="text-sm whitespace-pre-line text-muted-foreground">
                {{ decision.reason ?? '—' }}
            </p>
        </SectionCard>
        <SectionCard title="Yang Dapat Anda Lakukan">
            <AppBanner tone="info">
                Pengajuan yang ditolak tidak dapat diubah atau dikirim ulang. Anda tetap dapat
                mengajukan proposal baru ke skema yang sama pada periode ini.
            </AppBanner>
        </SectionCard>
    </div>
</template>
