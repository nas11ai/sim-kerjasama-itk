<script setup lang="ts">
import { Paperclip } from 'lucide-vue-next'

import type { AnnouncementFile } from '@/types/announcement'

defineProps<{ files: AnnouncementFile[] }>()

const kilobytes = (size: number) => `${Math.max(1, Math.round(size / 1024))} KB`
</script>

<template>
    <section v-if="files.length > 0" aria-labelledby="announcement-attachments" class="space-y-2">
        <h2 id="announcement-attachments" class="text-sm font-medium">Lampiran</h2>
        <ul class="space-y-1">
            <li v-for="file in files" :key="file.id">
                <a
                    :href="file.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 text-sm hover:underline"
                >
                    <Paperclip class="size-4 text-muted-foreground" />
                    {{ file.name }}
                    <span class="text-xs text-muted-foreground">{{ kilobytes(file.size) }}</span>
                </a>
            </li>
        </ul>
    </section>
</template>
