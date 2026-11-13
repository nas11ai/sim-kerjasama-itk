<script setup lang="ts">
// Frame: RESEARCHER-02-03, RESEARCHER-02-11, RESEARCHER-02-12, RESEARCHER-07-01
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import BudgetSection from '@/Components/Submission/BudgetSection.vue'
import DecisionSection from '@/Components/Submission/DecisionSection.vue'
import FilesSection from '@/Components/Submission/FilesSection.vue'
import HistoryTimeline from '@/Components/Submission/HistoryTimeline.vue'
import InfoSection from '@/Components/Submission/InfoSection.vue'
import MembersSection from '@/Components/Submission/MembersSection.vue'
import ScoreSection from '@/Components/Submission/ScoreSection.vue'
import SubmissionActions from '@/Components/Submission/SubmissionActions.vue'
import TargetOutputsSection from '@/Components/Submission/TargetOutputsSection.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDate } from '@/lib/format'
import type { SubmissionShowPageProps } from '@/types/submission-page'

const props = defineProps<SubmissionShowPageProps>()

const subtitle = computed(() =>
    [props.submission.scheme, `Ketua: ${props.submission.leadName}`].filter(Boolean).join(' · ')
)

const activeOverride = computed(() =>
    props.phaseAccess.override?.status === 'active' ? props.phaseAccess.override : null
)

const tabs = [
    { value: 'info', label: 'Informasi' },
    { value: 'members', label: 'Anggota' },
    { value: 'targets', label: 'Target Luaran' },
    { value: 'budget', label: 'Anggaran' },
    { value: 'files', label: 'Berkas' },
    { value: 'history', label: 'Riwayat' },
]
</script>

<template>
    <Head :title="submission.title" />
    <AppLayout>
        <PageHeader
            :title="submission.title"
            :subtitle="subtitle"
            :back-href="route('user.submissions.index')"
            back-label="Daftar Pengajuan"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
        </PageHeader>

        <!-- BR-FE-08 — peneliti melihat perpanjangan, alasannya hanya untuk Operator/Admin -->
        <AppBanner v-if="activeOverride" tone="info" class="mb-4">
            Akses tahap ini diperpanjang Operator sampai
            {{ formatDate(activeOverride.expires_at) }}.
        </AppBanner>

        <DecisionSection v-if="decision" :decision="decision" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <Tabs default-value="info" class="min-w-0">
                <TabsList class="flex-wrap">
                    <TabsTrigger v-for="tab in tabs" :key="tab.value" :value="tab.value">
                        {{ tab.label }}
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="info">
                    <InfoSection :submission="submission" :answers="answers" />
                </TabsContent>
                <TabsContent value="members"><MembersSection :members="members" /></TabsContent>
                <TabsContent value="targets">
                    <TargetOutputsSection
                        :submission="submission"
                        :target-outputs="targetOutputs"
                        :types="targetOutputTypes"
                        :history="targetHistory"
                        :can-manage="can.manageTargetOutput"
                    />
                </TabsContent>
                <TabsContent value="budget"><BudgetSection :budget="budget" /></TabsContent>
                <TabsContent value="files"><FilesSection :answers="answers" /></TabsContent>
                <TabsContent value="history" class="flex flex-col gap-4">
                    <!-- BR-RPT-05 — jejak pengajuan hanya untuk tim pengajuan -->
                    <AppBanner v-if="submission.viewerRole !== 'viewer'" tone="lock">
                        Riwayat ini mencatat seluruh aktivitas pada pengajuan Anda. Hanya Anda dan
                        tim pengajuan yang dapat melihatnya.
                    </AppBanner>
                    <SectionCard title="Riwayat">
                        <HistoryTimeline :entries="history" />
                    </SectionCard>
                </TabsContent>
            </Tabs>

            <aside class="flex flex-col gap-4">
                <SectionCard title="Aksi">
                    <SubmissionActions
                        :submission="submission"
                        :can="can"
                        :printable-documents="printableDocuments"
                    />
                </SectionCard>
                <ScoreSection :scores="scores" />
                <SectionCard title="Timeline Status">
                    <HistoryTimeline :entries="history.slice(0, 3)" />
                </SectionCard>
            </aside>
        </div>
    </AppLayout>
</template>
