<script setup lang="ts">
// Frame: OPERATOR-02-02
import { Head, router } from '@inertiajs/vue3'
import { Printer } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import BudgetSection from '@/Components/Submission/BudgetSection.vue'
import DecisionSection from '@/Components/Submission/DecisionSection.vue'
import FilesSection from '@/Components/Submission/FilesSection.vue'
import HistoryTimeline from '@/Components/Submission/HistoryTimeline.vue'
import InfoSection from '@/Components/Submission/InfoSection.vue'
import ExtendDeadlineDialog from '@/Components/Submission/Manage/ExtendDeadlineDialog.vue'
import OperatorActions from '@/Components/Submission/Manage/OperatorActions.vue'
import RejectDialog from '@/Components/Submission/Manage/RejectDialog.vue'
import ReviewersSection from '@/Components/Submission/Manage/ReviewersSection.vue'
import ReviewTab from '@/Components/Submission/Manage/ReviewTab.vue'
import TransferOwnershipDialog from '@/Components/Submission/Manage/TransferOwnershipDialog.vue'
import WithdrawDialog from '@/Components/Submission/Manage/WithdrawDialog.vue'
import MembersSection from '@/Components/Submission/MembersSection.vue'
import { Button } from '@/Components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ManageShowPageProps, OperatorActionKey } from '@/types/submission-manage'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<ManageShowPageProps>()

const subtitle = computed(() =>
    [props.submission.scheme, `Ketua: ${props.submission.leadName}`, props.organization]
        .filter(Boolean)
        .join(' · ')
)

// REV-OUTCOME-10 — fase butuh review tanpa reviewer aktif tetap SUBMITTED.
const awaitingReviewers = computed(() => props.needsReview && props.activeReviewerCount === 0)
const recentHistory = computed(() => props.history.slice(0, 3))

const sections = computed(() => [
    { value: 'info', label: 'Informasi' },
    { value: 'members', label: 'Anggota' },
    { value: 'budget', label: 'Anggaran' },
    { value: 'files', label: 'Berkas' },
    { value: 'review', label: `Review (${props.activeReviewerCount})` },
    { value: 'history', label: 'Riwayat' },
])

// Satu dialog aksi terbuka pada satu waktu; menutupnya mengosongkan pilihan.
const openAction = ref<OperatorActionKey | null>(null)
const replacing = ref<number | null>(null)
const closeAction = (open: boolean) => {
    if (!open) {
        openAction.value = null
    }
}

// RPT-PRINT-01 — dokumen sudah disaring backend (BR-RPT-11).
const print = (document: FilterOption) =>
    router.post(
        route('submissions.print', [String(props.submission.id), document.value]),
        {},
        { preserveScroll: true }
    )
</script>

<template>
    <Head :title="submission.title" />
    <AppLayout>
        <PageHeader
            :title="submission.title"
            :subtitle="subtitle"
            :back-href="route('admin.submissions.index')"
            back-label="Semua Pengajuan"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
            <template v-if="printableDocuments.length > 0" #actions>
                <Button
                    v-for="document in printableDocuments"
                    :key="document.value"
                    variant="outline"
                    @click="print(document)"
                >
                    <Printer class="size-4" /> Cetak {{ document.label }}
                </Button>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-col gap-4">
            <AppBanner v-if="awaitingReviewers" tone="info">
                Pengajuan menunggu penugasan reviewer.
            </AppBanner>
            <!-- BR-SM-11 — WITHDRAWN final -->
            <AppBanner v-if="submission.status.key === 'withdrawn'" tone="lock">
                Penarikan bersifat final. Kalau keliru, jalurnya adalah pengajuan baru yang merujuk
                pengajuan ini.
            </AppBanner>
            <DecisionSection v-if="decision" :decision="decision" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <Tabs default-value="info" class="min-w-0">
                <TabsList class="w-full flex-wrap justify-start">
                    <TabsTrigger
                        v-for="section in sections"
                        :key="section.value"
                        :value="section.value"
                    >
                        {{ section.label }}
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="info"
                    ><InfoSection :submission="submission" :answers="answers"
                /></TabsContent>
                <TabsContent value="members"><MembersSection :members="members" /></TabsContent>
                <TabsContent value="budget"><BudgetSection :budget="budget" /></TabsContent>
                <TabsContent value="files"><FilesSection :answers="answers" /></TabsContent>
                <TabsContent value="review">
                    <ReviewTab :sheets="evaluationSheets" :threads="threads" />
                </TabsContent>
                <TabsContent value="history">
                    <SectionCard title="Riwayat"
                        ><HistoryTimeline :entries="history"
                    /></SectionCard>
                </TabsContent>
            </Tabs>

            <aside class="flex flex-col gap-4">
                <SectionCard title="Aksi Operator">
                    <OperatorActions
                        :can="operatorCan"
                        :has-ownership-candidates="ownershipCandidates.length > 0"
                        @select="openAction = $event"
                    />
                </SectionCard>
                <ReviewersSection
                    :reviewers="reviewers"
                    :active-count="activeReviewerCount"
                    :minimum="minimumReviewers"
                    :can-replace="operatorCan.replaceReviewer"
                    @replace="replacing = $event"
                />
                <SectionCard title="Timeline Status">
                    <HistoryTimeline :entries="recentHistory" />
                </SectionCard>
            </aside>
        </div>

        <ExtendDeadlineDialog
            :open="openAction === 'extend'"
            :submission-id="submission.id"
            :phase-access="phaseAccess"
            :target="overrideTarget"
            @update:open="closeAction"
        />
        <RejectDialog
            :open="openAction === 'reject'"
            :submission="submission"
            :active-reviewer-count="activeReviewerCount"
            @update:open="closeAction"
        />
        <WithdrawDialog
            :open="openAction === 'withdraw'"
            :submission="submission"
            @update:open="closeAction"
        />
        <TransferOwnershipDialog
            :open="openAction === 'transfer'"
            :submission="submission"
            :candidates="ownershipCandidates"
            @update:open="closeAction"
        />
    </AppLayout>
</template>
