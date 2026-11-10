<script setup lang="ts">
// Frame: REVIEWER-03-04
import { Head } from '@inertiajs/vue3'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import NewThreadForm from '@/Components/Review/NewThreadForm.vue'
import ReviewRoundCard from '@/Components/Review/ReviewRoundCard.vue'
import ReviewThreadCard from '@/Components/Submission/Revision/ReviewThreadCard.vue'
import ScoreSection from '@/Components/Submission/ScoreSection.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SummaryPageProps } from '@/types/review'

defineProps<SummaryPageProps>()
</script>

<template>
    <Head title="Review Summary & Diskusi" />
    <AppLayout>
        <PageHeader
            title="Review Summary & Diskusi"
            :subtitle="submission.title"
            :back-href="route('reviewer.assignments.index')"
            back-label="Penugasan Review"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
        </PageHeader>

        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <ReviewRoundCard :round="round" :submission-id="submission.id" />
            <NewThreadForm :submission-id="submission.id" :can-create="canCreateThread" />

            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold">Diskusi</h2>
                <!-- Ulir ini milik penugasan Anda, termasuk warisan reviewer sebelumnya (BR-REV-15). -->
                <ReviewThreadCard
                    v-for="thread in threads"
                    :key="thread.id"
                    :thread="thread"
                    :can-reply="true"
                    :can-resolve="true"
                />
                <EmptyState v-if="threads.length === 0" title="Belum ada catatan ringkasan." />
            </section>

            <!-- BR-REV-11 — skor rekan dan alasannya tertutup dibaca dari payload -->
            <ScoreSection
                :scores="scores"
                note="Skor rekan terbuka bagi reviewer internal setelah seluruh evaluasi selesai."
            />
            <AppBanner tone="info">
                Catatan yang masih terbuka saat putaran selesai dikirim ke peneliti sebagai
                permintaan revisi. Persetujuan dan penolakan diputuskan Operator.
            </AppBanner>
        </div>
    </AppLayout>
</template>
