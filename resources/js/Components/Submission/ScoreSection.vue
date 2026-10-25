<script setup lang="ts">
import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { ReviewerScore, ReviewerScores } from '@/types/submission-page'

defineProps<{ scores: ReviewerScores }>()

const answersOf = (score: ReviewerScore) =>
    score.answers.map((answer) => ({ label: answer.label, value: answer.value ?? '—' }))
</script>

<template>
    <!-- BR-REV-12 — skor ditahan sampai keputusan final; alasannya dari backend -->
    <AppBanner v-if="!scores.visible" tone="info">
        {{ scores.reason }}
    </AppBanner>
    <SectionCard v-else title="Hasil Evaluasi Reviewer">
        <div v-for="score in scores.scores" :key="score.reviewer" class="flex flex-col gap-2">
            <p class="text-sm font-medium">{{ score.reviewer }}</p>
            <InfoGrid :items="answersOf(score)" />
        </div>
        <EmptyState v-if="scores.scores.length === 0" title="Belum ada evaluasi yang disubmit." />
        <p class="text-xs text-muted-foreground">
            Skor evaluasi baru terbuka setelah pengajuan dinyatakan disetujui atau ditolak.
        </p>
    </SectionCard>
</template>
