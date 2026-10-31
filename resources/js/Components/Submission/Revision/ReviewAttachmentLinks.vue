<script setup lang="ts">
import { Paperclip } from 'lucide-vue-next'

import type { ReviewAttachment } from '@/types/submission-revision'

// Unduhan lewat route ber-scope BR-SM-07; tautan biasa, bukan Inertia.
defineProps<{ attachments: ReviewAttachment[] }>()
</script>

<template>
    <ul v-if="attachments.length > 0" class="flex flex-wrap gap-2 text-sm">
        <li v-for="file in attachments" :key="file.url">
            <a :href="file.url" class="inline-flex items-center gap-1 text-primary hover:underline">
                <Paperclip class="size-3.5" /> {{ file.name }}
            </a>
        </li>
    </ul>
</template>
