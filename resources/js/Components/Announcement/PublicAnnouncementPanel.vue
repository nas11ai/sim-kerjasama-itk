<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import { formatDate } from '@/lib/format'
import type { AnnouncementSummary } from '@/types/announcement'

// fe-v2-design §4.7, W6 — umpan publik di samping form login; tidak dirender bila kosong.
defineProps<{ announcements: AnnouncementSummary[] }>()
</script>

<template>
    <section
        v-if="announcements.length > 0"
        aria-labelledby="public-announcements"
        class="w-full rounded-lg border bg-card p-6 shadow-sm"
    >
        <h2 id="public-announcements" class="mb-3 text-base font-semibold">Pengumuman</h2>
        <ul class="divide-y">
            <li v-for="announcement in announcements" :key="announcement.id" class="py-2.5">
                <Link
                    :href="route('announcements.detail', announcement.id)"
                    class="text-sm font-medium hover:underline"
                >
                    {{ announcement.title }}
                </Link>
                <p class="text-xs text-muted-foreground">
                    {{ formatDate(announcement.publishedAt) }}
                </p>
            </li>
        </ul>
    </section>
</template>
