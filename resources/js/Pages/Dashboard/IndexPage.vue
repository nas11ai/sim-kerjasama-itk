<script setup lang="ts">
// Frame: SHARED-02-01..04
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ActivityList from '@/Components/Dashboard/ActivityList.vue'
import DeadlineList from '@/Components/Dashboard/DeadlineList.vue'
import KpiGrid from '@/Components/Dashboard/KpiGrid.vue'
import LatestAnnouncements from '@/Components/Dashboard/LatestAnnouncements.vue'
import QuickActions from '@/Components/Dashboard/QuickActions.vue'
import WelcomeBanner from '@/Components/Dashboard/WelcomeBanner.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { quickActions } from '@/lib/dashboardActions'
import type { PageProps } from '@/types'
import type { BiodataStatusFlags, DashboardIndexPageProps } from '@/types/dashboard'

defineProps<DashboardIndexPageProps>()

const page = usePage<PageProps<{ biodataStatus: BiodataStatusFlags | null }>>()

// §4.4 — section dari backend, aksi cepat dari permissions; nama peran tak dibaca.
const actions = computed(() => quickActions(page.props.auth.user.permissions))
const biodata = computed(() => {
    const status = page.props.biodataStatus

    return status?.required && !status.completed ? status : null
})
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <WelcomeBanner :name="page.props.auth.user.name" :subtitle="subtitle" />
        <div class="flex flex-col gap-6">
            <AppBanner v-if="biodata" tone="warning" title="Biodata belum lengkap">
                {{ biodata.message ?? 'Silakan lengkapi biodata Anda terlebih dahulu.' }}
                <Link :href="route('user.biodata.index')" class="font-medium underline">
                    Buka biodata
                </Link>
            </AppBanner>
            <AppBanner v-if="pending" tone="warning">
                <strong>{{ pending.awaitingReviewer }} pengajuan</strong> butuh reviewer ·
                <strong>{{ pending.awaitingVerification }} user</strong> menunggu verifikasi NIDN.
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
