<script setup lang="ts">
import { computed } from 'vue'

import type { SubmissionAnswer } from '@/types/submission-page'

const props = defineProps<{ answers: SubmissionAnswer[] }>()

// Berkas dibaca di FilesSection.
const textAnswers = computed(() => props.answers.filter((answer) => !answer.isFile))
</script>

<template>
    <dl v-if="textAnswers.length > 0" class="flex flex-col gap-3 text-sm">
        <div v-for="answer in textAnswers" :key="answer.label">
            <dt class="text-xs text-muted-foreground">{{ answer.label }}</dt>
            <dd class="whitespace-pre-line break-words">{{ answer.value ?? '—' }}</dd>
        </div>
    </dl>
</template>
