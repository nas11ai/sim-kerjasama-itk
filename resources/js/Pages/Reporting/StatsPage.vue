<script setup lang="ts">
// Frame: OPERATOR-07-01
import { Head } from '@inertiajs/vue3'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import FollowUpSection from '@/Components/Reporting/FollowUpSection.vue'
import HistorySection from '@/Components/Reporting/HistorySection.vue'
import NowSection from '@/Components/Reporting/NowSection.vue'
import StatsFilterBar from '@/Components/Reporting/StatsFilterBar.vue'
import StatsOverviewSection from '@/Components/Reporting/StatsOverviewSection.vue'
import StatsTabs from '@/Components/Reporting/StatsTabs.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { StatsPageProps } from '@/types/reporting'

defineProps<StatsPageProps>()
</script>

<template>
    <Head title="Beranda Statistik" />
    <AppLayout>
        <PageHeader
            title="Beranda Statistik"
            subtitle="Statistik ditampilkan langsung dari data terkini dan bersifat hanya-baca."
        >
            <template #actions>
                <StatsFilterBar
                    :key="tab"
                    :tab="tab"
                    :filters="filters"
                    :filter-keys="filterKeys"
                    :year-options="yearOptions"
                    :period-options="periodOptions"
                    :scheme-options="schemeOptions"
                />
            </template>
        </PageHeader>
        <StatsTabs :tabs="tabs" :active="tab" />
        <StatsOverviewSection v-if="tab === 'overview' && overview" :overview="overview" />
        <NowSection v-else-if="tab === 'now' && now" :now="now" />
        <HistorySection v-else-if="tab === 'history' && history" :history="history" />
        <FollowUpSection v-else-if="tab === 'follow-up' && followUp" :lists="followUp" />
        <EmptyState v-else title="Tidak ada data" />
    </AppLayout>
</template>
