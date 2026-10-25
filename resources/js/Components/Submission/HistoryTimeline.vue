<script setup lang="ts">
import EmptyState from '@/Components/app/EmptyState.vue'
import { formatDate } from '@/lib/format'
import type { SubmissionHistoryEntry } from '@/types/submission-page'

defineProps<{ entries: SubmissionHistoryEntry[] }>()
</script>

<template>
    <ol v-if="entries.length > 0" class="flex flex-col gap-3 border-l pl-4 text-sm">
        <li v-for="(entry, index) in entries" :key="index" class="relative">
            <span class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-primary" />
            <p class="font-medium">{{ entry.description }}</p>
            <p class="text-xs text-muted-foreground">
                {{ [formatDate(entry.at), entry.actor].filter(Boolean).join(' · ') }}
            </p>
        </li>
    </ol>
    <EmptyState v-else title="Belum ada riwayat" />
</template>
