<script setup lang="ts">
import ReviewAttachmentLinks from '@/Components/Submission/Revision/ReviewAttachmentLinks.vue'
import { formatDate } from '@/lib/format'
import type { ReviewComment } from '@/types/submission-revision'

// Isi komentar teks biasa (tanpa v-html); balasan dirender oleh komponen yang sama.
defineProps<{ comment: ReviewComment }>()
</script>

<template>
    <li class="flex flex-col gap-1">
        <p class="text-xs text-muted-foreground">
            <span class="font-medium text-foreground">{{ comment.author }}</span>
            · {{ formatDate(comment.at) }}
        </p>
        <p class="text-sm whitespace-pre-line">{{ comment.body }}</p>
        <ReviewAttachmentLinks :attachments="comment.attachments" />
        <ul v-if="comment.replies.length > 0" class="mt-2 flex flex-col gap-3 border-l pl-4">
            <ReviewCommentItem v-for="reply in comment.replies" :key="reply.id" :comment="reply" />
        </ul>
    </li>
</template>
