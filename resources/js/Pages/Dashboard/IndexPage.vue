<script setup lang="ts">
// Frame: SHARED-02-01..04
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ActivityList from '@/Components/Dashboard/ActivityList.vue'
import DeadlineList from '@/Components/Dashboard/DeadlineList.vue'
import KpiGrid from '@/Components/app/KpiGrid.vue'
import LatestAnnouncements from '@/Components/Dashboard/LatestAnnouncements.vue'
import QuickActions from '@/Components/Dashboard/QuickActions.vue'
import WelcomeBanner from '@/Components/Dashboard/WelcomeBanner.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { quickActions } from '@/lib/dashboardActions'
import type { PageProps } from '@/types'
import type { DashboardIndexPageProps } from '@/types/dashboard'

defineProps<DashboardIndexPageProps>()

const page = usePage<PageProps>()

// §4.4 — section dari backend, aksi cepat dari permissions; nama peran tak dibaca.
const actions = computed(() => quickActions(page.props.auth.user?.permissions ?? []))
</script>

<template>
    <Head title="Beranda" />
    <AppLayout>
        <WelcomeBanner :name="page.props.auth.user?.name ?? ''" :subtitle="subtitle" />
        <div class="flex flex-col gap-6">
            <!-- BR-IAM-03 — profil belum lengkap menahan pembuatan pengajuan (IAM-VERIFY-04) -->
            <AppBanner v-if="profileCompletion" tone="warning" title="Profil belum lengkap">
                Lengkapi profil Anda sebelum membuat pengajuan.
                <template v-if="profileCompletion.missing.length < 3">
                    {{ profileCompletion.missing.length }} dari 3 data tambahan belum terisi.
                </template>
                <Link
                    :href="route('profile.complete', { next: 'submission' })"
                    class="font-medium underline"
                >
                    Lengkapi Profil
                </Link>
            </AppBanner>
            <AppBanner v-if="pending" tone="warning">
                <strong>{{ pending.awaitingReviewer }} pengajuan</strong> butuh penilai ·
                <strong>{{ pending.awaitingVerification }} pengguna</strong> menunggu verifikasi
                NIDN.
            </AppBanner>
            <section
                v-for="section in sections"
                :key="section.key"
                class="flex flex-col gap-3"
                :aria-label="section.title"
            >
                <h2 class="text-sm font-semibold text-muted-foreground">{{ section.title }}</h2>
                <KpiGrid :cards="section.cards" />
            </section>
            <div class="grid gap-6 lg:grid-cols-2">
                <ActivityList :activities="activities" />
                <DeadlineList :deadlines="deadlines" />
            </div>
            <LatestAnnouncements :announcements="announcements" />
            <QuickActions :actions="actions" />
        </div>
    </AppLayout>
</template>
