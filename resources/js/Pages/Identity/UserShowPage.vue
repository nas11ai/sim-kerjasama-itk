<script setup lang="ts">
// Frame: OPERATOR-08-03
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import AccountStatusBadges from '@/Components/Identity/AccountStatusBadges.vue'
import DeactivationPanel from '@/Components/Identity/DeactivationPanel.vue'
import UserAccessSection from '@/Components/Identity/UserAccessSection.vue'
import UserProfileSection from '@/Components/Identity/UserProfileSection.vue'
import UserReviewHistory from '@/Components/Identity/UserReviewHistory.vue'
import UserSubmissionHistory from '@/Components/Identity/UserSubmissionHistory.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDate } from '@/lib/format'
import type { UserShowPageProps } from '@/types/identity'

const props = defineProps<UserShowPageProps>()

const subtitle = computed(() =>
    props.user.nidn ? `${props.user.email} · NIDN ${props.user.nidn}` : props.user.email
)

// W55 — tab "Log Aktivitas" menunggu Reporting/AuditPage (R1).
const tabs = computed(() => [
    { value: 'profile', label: 'Profil' },
    { value: 'submissions', label: `Riwayat Submission (${props.submissions.length})` },
    { value: 'reviews', label: `Riwayat Reviewer (${props.reviews.length})` },
])

const summaryItems = computed(() => [
    { label: 'Submission Aktif', value: `${props.summary.activeLeadSubmissions} (lead)` },
    { label: 'Review Berjalan', value: String(props.summary.runningReviews) },
    { label: 'Terdaftar', value: formatDate(props.summary.registeredAt) },
])
</script>

<template>
    <Head :title="user.name" />
    <AppLayout>
        <PageHeader
            :title="user.name"
            :subtitle="subtitle"
            :back-href="route('admin.users.index')"
            back-label="Daftar User"
        >
            <template #badge><AccountStatusBadges :status="user.status" /></template>
        </PageHeader>
        <!-- IAM-DEACT-03 — tampil di setiap tab: riwayat di bawahnya milik akun nonaktif. -->
        <AppBanner v-if="!user.status.active" tone="lock" class="mb-6">
            Akun nonaktif. Data dan seluruh jejaknya tetap tersimpan; yang berhenti hanya kemampuan
            masuk dan menerima penugasan baru.
        </AppBanner>

        <Tabs :default-value="tab">
            <TabsList class="w-full flex-wrap justify-start">
                <TabsTrigger v-for="item in tabs" :key="item.value" :value="item.value">
                    {{ item.label }}
                </TabsTrigger>
            </TabsList>
            <TabsContent value="profile">
                <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div class="flex min-w-0 flex-col gap-6">
                        <UserProfileSection :user="user" />
                        <UserAccessSection
                            :user-id="user.id"
                            :access="access"
                            :can-manage="can.managePermissions"
                        />
                    </div>
                    <div class="flex flex-col gap-6">
                        <SectionCard title="Ringkasan"
                            ><InfoGrid :items="summaryItems"
                        /></SectionCard>
                        <DeactivationPanel
                            :user="user"
                            :blockers="deactivationBlockers"
                            :can-deactivate="can.deactivate"
                        />
                    </div>
                </div>
            </TabsContent>
            <TabsContent value="submissions"
                ><UserSubmissionHistory :rows="submissions"
            /></TabsContent>
            <TabsContent value="reviews"><UserReviewHistory :rows="reviews" /></TabsContent>
        </Tabs>
    </AppLayout>
</template>
