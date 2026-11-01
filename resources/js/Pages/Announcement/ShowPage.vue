<script setup lang="ts">
// Frame: — (fe-v2-design §4.7)
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import { computed, onMounted } from 'vue'

import AnnouncementArticle from '@/Components/Announcement/AnnouncementArticle.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { PageProps } from '@/types'
import type { AnnouncementShowPageProps } from '@/types/announcement'

const props = defineProps<AnnouncementShowPageProps>()

// Tamu membuka pengumuman publik tanpa sesi; `auth.user` null baginya.
const page = usePage<PageProps>()
const signedIn = computed(() => page.props.auth.user !== null)

// Penanda baca sekali saat dibuka; tamu (`isRead` null) dan yang sudah dibaca dilewati.
onMounted(() => {
    if (props.announcement.isRead !== false) {
        return
    }
    router.post(
        route('user.announcements.markRead', props.announcement.id),
        {},
        { preserveScroll: true, preserveState: true }
    )
})
</script>

<template>
    <Head :title="announcement.title" />
    <AppLayout v-if="signedIn">
        <Link
            :href="route('user.announcements.index')"
            class="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Pengumuman
        </Link>
        <div class="max-w-3xl">
            <AnnouncementArticle :announcement="announcement" />
        </div>
    </AppLayout>
    <AuthLayout v-else wide>
        <AnnouncementArticle :announcement="announcement" />
        <Link
            :href="route('login')"
            class="mt-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Kembali ke halaman masuk
        </Link>
    </AuthLayout>
</template>
