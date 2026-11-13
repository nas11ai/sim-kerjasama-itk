<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/Components/app/EmptyState.vue'
import ReviewThreadCard from '@/Components/Submission/Revision/ReviewThreadCard.vue'
import ScoreSection from '@/Components/Submission/ScoreSection.vue'
import type { ReviewerScore } from '@/types/submission-page'
import type { ReviewThread } from '@/types/submission-revision'

const props = defineProps<{ sheets: ReviewerScore[]; threads: ReviewThread[] }>()

// W22 — Operator membaca semua lembar yang sudah disubmit, termasuk milik reviewer yang digantikan.
const scores = computed(() => ({ visible: true, reason: null, scores: props.sheets }))
</script>

<template>
    <div class="flex flex-col gap-4">
        <ScoreSection
            :scores="scores"
            note="Lembar penilai yang digantikan tetap tersimpan dan ditandai “Digantikan”."
        />
        <section class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">Diskusi Penilai</h2>
            <!-- BR-REV-15 — Operator membaca ulir, tidak membalas -->
            <ReviewThreadCard
                v-for="thread in threads"
                :key="thread.id"
                :thread="thread"
                :can-reply="false"
            />
            <EmptyState v-if="threads.length === 0" title="Belum ada catatan ringkasan." />
        </section>
    </div>
</template>
