<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Megaphone } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { formatDate } from '@/lib/format'
import type { AnnouncementSummary } from '@/types/announcement'

defineProps<{ announcements: AnnouncementSummary[] }>()
</script>

<template>
    <EmptyState
        v-if="announcements.length === 0"
        :icon="Megaphone"
        title="Belum ada pengumuman untuk Anda."
    />
    <div v-else class="space-y-3">
        <article
            v-for="announcement in announcements"
            :key="announcement.id"
            class="rounded-lg border bg-card p-4"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="route('announcements.detail', announcement.id)"
                    class="font-medium hover:underline"
                >
                    {{ announcement.title }}
                </Link>
                <Badge v-if="announcement.isRead === false">Baru</Badge>
            </div>
            <p v-if="announcement.excerpt" class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                {{ announcement.excerpt }}
            </p>
            <p class="mt-2 text-xs text-muted-foreground">
                {{ formatDate(announcement.publishedAt) }}
                <template v-if="announcement.attachmentCount > 0">
                    · {{ announcement.attachmentCount }} lampiran
                </template>
            </p>
        </article>
    </div>
</template>
