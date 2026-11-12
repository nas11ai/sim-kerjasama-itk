<script setup lang="ts">
// K-3 (b) — tautan ke halaman pengajuan, tempat Operator memberi perpanjangan (BR-FE-27).
import { Link } from '@inertiajs/vue3'

import type { PastDeadlineSubmission } from '@/types/form-engine'

defineProps<{ submissions: PastDeadlineSubmission[] }>()
</script>

<template>
    <template v-for="(submission, index) in submissions" :key="submission.submissionId">
        <template v-if="index > 0">, </template>
        <Link
            :href="route('admin.submissions.show', submission.submissionId)"
            class="font-medium text-foreground underline underline-offset-2"
            >{{ submission.researcherName }}</Link
        >
    </template>
</template>
