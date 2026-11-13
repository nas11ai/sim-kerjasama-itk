<script setup lang="ts">
import { computed } from 'vue'

import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import AnswerList from '@/Components/Submission/AnswerList.vue'
import { formatDate } from '@/lib/format'
import type { SubmissionAnswer, SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{ submission: SubmissionDetail; answers: SubmissionAnswer[] }>()

const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Skema', value: props.submission.scheme ?? '-' },
    { label: 'Periode', value: props.submission.period ?? '-' },
    { label: 'TKT', value: props.submission.trl ?? '-' },
    { label: 'Tanggal Submit', value: formatDate(props.submission.submittedAt) },
    { label: 'Peran Anda', value: props.submission.viewerRoleLabel },
])
</script>

<template>
    <SectionCard title="Informasi Pengajuan">
        <InfoGrid :items="summary" />
        <AnswerList :answers="answers" />
    </SectionCard>
</template>
