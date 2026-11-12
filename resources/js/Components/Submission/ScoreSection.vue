<script setup lang="ts">
import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Badge } from '@/Components/ui/badge'
import type { ReviewerScore, ReviewerScores } from '@/types/submission-page'

// `note` menjelaskan kapan skor terbuka bagi pembaca layar; bawaannya untuk peneliti (BR-REV-12).
withDefaults(defineProps<{ scores: ReviewerScores; note?: string }>(), {
    note: 'Skor evaluasi baru terbuka setelah pengajuan dinyatakan disetujui atau ditolak.',
})

const answersOf = (score: ReviewerScore) =>
    score.answers.map((answer) => ({ label: answer.label, value: answer.value ?? '—' }))
</script>

<template>
    <!-- BR-REV-12 — skor ditahan sampai keputusan final; alasannya dari backend -->
    <AppBanner v-if="!scores.visible" tone="info">
        {{ scores.reason }}
    </AppBanner>
    <SectionCard v-else title="Hasil Evaluasi Reviewer">
        <!-- BR-REV-19 — lembar yang digantikan versi baru tetap terbaca, diredam sebagai riwayat -->
        <div
            v-for="(score, index) in scores.scores"
            :key="`${score.reviewer}-${index}`"
            data-score
            :class="['flex flex-col gap-2', { 'opacity-60': score.superseded }]"
        >
            <p class="flex flex-wrap items-center gap-2 text-sm font-medium">
                {{ score.reviewer }}
                <!-- BR-REV-18 — versi lembar yang diisi reviewer -->
                <Badge v-if="score.superseded" variant="outline"
                    >Riwayat · {{ score.version_label }}</Badge
                >
                <Badge v-else variant="secondary">{{ score.version_label }}</Badge>
                <!-- BR-REV-10 — lembar reviewer yang digantikan tetap terbaca -->
                <Badge v-if="score.replaced" variant="outline">Digantikan</Badge>
            </p>
            <InfoGrid :items="answersOf(score)" />
        </div>
        <EmptyState v-if="scores.scores.length === 0" title="Belum ada evaluasi yang disubmit." />
        <p class="text-xs text-muted-foreground">{{ note }}</p>
    </SectionCard>
</template>
