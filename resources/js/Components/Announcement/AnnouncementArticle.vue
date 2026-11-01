<script setup lang="ts">
import AttachmentList from '@/Components/Announcement/AttachmentList.vue'
import RichTextContent from '@/Components/Announcement/RichTextContent.vue'
import { formatDate } from '@/lib/format'
import type { AnnouncementDetail } from '@/types/announcement'

// Isi satu pengumuman, sama untuk pengguna login (AppLayout) dan tamu (AuthLayout).
defineProps<{ announcement: AnnouncementDetail }>()
</script>

<template>
    <article class="space-y-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ announcement.title }}</h1>
            <p class="text-sm text-muted-foreground">
                Diterbitkan {{ formatDate(announcement.publishedAt) }}
                <template v-if="announcement.author">oleh {{ announcement.author }}</template>
            </p>
            <p v-if="announcement.expiresAt" class="text-sm text-muted-foreground">
                Berlaku sampai {{ formatDate(announcement.expiresAt) }}
            </p>
        </header>
        <RichTextContent :html="announcement.contentHtml" />
        <AttachmentList :files="announcement.files" />
    </article>
</template>
