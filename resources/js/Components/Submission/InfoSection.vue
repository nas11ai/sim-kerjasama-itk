<script setup lang="ts">
import { computed } from 'vue'

import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { formatDate } from '@/lib/format'
import type { SubmissionAnswer, SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{ submission: SubmissionDetail; answers: SubmissionAnswer[] }>()

const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Skema', value: props.submission.scheme ?? '—' },
    { label: 'Periode', value: props.submission.period ?? '—' },
    { label: 'TRL', value: props.submission.trl ?? '—' },
    { label: 'Tanggal Submit', value: formatDate(props.submission.submittedAt) },
    { label: 'Peran Anda', value: props.submission.viewerRoleLabel },
])

// Berkas punya tabnya sendiri.
const textAnswers = computed(() => props.answers.filter((answer) => !answer.isFile))
</script>

<template>
    <SectionCard title="Informasi Pengajuan">
        <InfoGrid :items="summary" />
        <dl v-if="textAnswers.length > 0" class="flex flex-col gap-3 text-sm">
            <div v-for="answer in textAnswers" :key="answer.label">
                <dt class="text-xs text-muted-foreground">{{ answer.label }}</dt>
                <dd class="whitespace-pre-line break-words">{{ answer.value ?? '—' }}</dd>
            </div>
        </dl>
    </SectionCard>
</template>
