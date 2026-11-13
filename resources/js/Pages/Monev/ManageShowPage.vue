<script setup lang="ts">
// Frame: OPERATOR-05-02
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import ManagedCycleCard from '@/Components/Monev/ManagedCycleCard.vue'
import PhaseProgression from '@/Components/Monev/PhaseProgression.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { phaseNodes } from '@/lib/monev'
import type { MonevManageShowPageProps } from '@/types/monev'

const props = defineProps<MonevManageShowPageProps>()

const nodes = computed(() => phaseNodes(props.cycles.map((managed) => managed.cycle)))
</script>

<template>
    <Head title="Aktivasi Siklus & Penugasan Reviewer" />
    <AppLayout>
        <PageHeader
            title="Aktivasi Siklus & Penugasan Reviewer"
            :subtitle="`${submission.title} · ${submission.leader}`"
            :back-href="route('monev.manage.index')"
            back-label="Manajemen Siklus Monev"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
        </PageHeader>
        <PhaseProgression :nodes="nodes" />
        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-4">
                <ManagedCycleCard
                    v-for="(managed, index) in cycles"
                    :key="managed.cycle.detailId"
                    :cycle="managed"
                    :parent-id="submission.id"
                    :suggested="suggestedReviewerIds"
                    :first="index === 0"
                    :candidates="reviewerCandidates"
                />
            </div>
            <div class="flex flex-col gap-4">
                <AppBanner tone="info">
                    Penugasan reviewer monev dilakukan manual per siklus. Reviewer yang masa
                    tugasnya berakhir tidak muncul di saran, namun tetap dapat dicari manual.
                </AppBanner>
                <!-- BR-MON-10 -->
                <AppBanner tone="info">
                    Mengaktifkan siklus memberi tahu peneliti dan reviewer yang ditugaskan pada
                    siklus itu. Selama siklus belum diaktifkan, tidak ada pemberitahuan yang
                    terkirim, jadwal monev berjarak bulanan, dan tenggatnya bisa terlanjur berjalan
                    tanpa ada yang tahu.
                </AppBanner>
            </div>
        </div>
    </AppLayout>
</template>
