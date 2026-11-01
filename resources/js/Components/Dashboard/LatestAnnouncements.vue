<script setup lang="ts">
// Frame: SHARED-02-01..04 (+ "Pengumuman terbaru", carry-over A1)
import { Link } from '@inertiajs/vue3'

import AnnouncementList from '@/Components/Announcement/AnnouncementList.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import type { AnnouncementSummary } from '@/types/announcement'

defineProps<{ announcements: AnnouncementSummary[] }>()
</script>

<template>
    <SectionCard title="Pengumuman terbaru">
        <template #actions>
            <Button v-if="announcements.length > 0" size="sm" variant="ghost" as-child>
                <Link :href="route('user.announcements.index')">Lihat semua</Link>
            </Button>
        </template>
        <AnnouncementList :announcements="announcements" />
    </SectionCard>
</template>
