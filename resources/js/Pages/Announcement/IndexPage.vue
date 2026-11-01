<script setup lang="ts">
// Frame: — (fe-v2-design §4.7)
import { Head } from '@inertiajs/vue3'

import AnnouncementList from '@/Components/Announcement/AnnouncementList.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { AnnouncementIndexPageProps } from '@/types/announcement'

defineProps<AnnouncementIndexPageProps>()
</script>

<template>
    <Head title="Pengumuman" />
    <AppLayout>
        <PageHeader title="Pengumuman" subtitle="Informasi dari LPPM ITK untuk Anda." />
        <div class="max-w-3xl">
            <AnnouncementList :announcements="announcements.data" />
            <PaginationBar :page="announcements" />
        </div>
    </AppLayout>
</template>
