<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import AnswerList from '@/Components/Submission/AnswerList.vue'
import FilesSection from '@/Components/Submission/FilesSection.vue'
import type { EvaluationSubmission } from '@/types/review'
import type { SubmissionAnswer } from '@/types/submission-page'

const props = defineProps<{ submission: EvaluationSubmission; answers: SubmissionAnswer[] }>()

const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Ketua', value: props.submission.leadName },
    { label: 'Skema', value: props.submission.scheme ?? '-' },
    { label: 'Periode', value: props.submission.period ?? '-' },
    { label: 'Anggota', value: `${props.submission.memberCount} orang` },
])
</script>

<template>
    <section class="flex min-w-0 flex-col gap-4">
        <SectionCard title="Pengajuan" description="Baca-saja">
            <template #actions>
                <!-- BR-SM-07 — penugasan aktif juga membuka detail lengkap (SM-ACCESS-05) -->
                <Link
                    :href="route('user.submissions.show', String(submission.id))"
                    class="text-sm text-primary underline-offset-4 hover:underline"
                >
                    Buka detail lengkap
                </Link>
            </template>
            <InfoGrid :items="summary" />
            <AnswerList :answers="answers" />
        </SectionCard>
        <FilesSection :answers="answers" />
    </section>
</template>
