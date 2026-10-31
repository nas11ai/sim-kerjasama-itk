<script setup lang="ts">
import EmptyState from '@/Components/app/EmptyState.vue'
import TimelineItem from '@/Components/Submission/TimelineItem.vue'
import type { SubmissionHistoryEntry } from '@/types/submission-page'

defineProps<{ entries: SubmissionHistoryEntry[] }>()
</script>

<template>
    <ol v-if="entries.length > 0" class="flex flex-col gap-3 border-l pl-4 text-sm">
        <TimelineItem
            v-for="(entry, index) in entries"
            :key="index"
            :title="entry.description"
            :at="entry.at"
            :actor="entry.actor"
        />
    </ol>
    <EmptyState v-else title="Belum ada riwayat" />
</template>
