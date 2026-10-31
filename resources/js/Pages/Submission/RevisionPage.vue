<script setup lang="ts">
// Frame: RESEARCHER-02-05
import { Head, router } from '@inertiajs/vue3'
import { Send } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import ReviewThreadCard from '@/Components/Submission/Revision/ReviewThreadCard.vue'
import RevisionEditPanel from '@/Components/Submission/Revision/RevisionEditPanel.vue'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SubmissionRevisionPageProps } from '@/types/submission-revision'

const props = defineProps<SubmissionRevisionPageProps>()

const confirming = ref(false)
const sending = ref(false)

// BR-SM-02, BR-IAM-04 — penghalang dari backend; layar hanya menonaktifkan tombolnya.
const blocked = computed(() => props.missingFields.length > 0 || props.accountBlock !== null)

// BR-SM-03 — sesudahnya controller mengalihkan halaman revisi ke detail.
const resubmit = () => {
    sending.value = true
    router.post(
        route('user.submissions.resubmit', String(props.submission.id)),
        {},
        { onFinish: () => (sending.value = confirming.value = false) }
    )
}
</script>

<template>
    <Head title="Revisi Pengajuan" />
    <AppLayout>
        <PageHeader
            title="Revisi Pengajuan"
            :subtitle="submission.title"
            :back-href="route('user.submissions.show', String(submission.id))"
            back-label="Detail Pengajuan"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
            <template v-if="can.resubmit" #actions>
                <Button data-action="resubmit" :disabled="blocked" @click="confirming = true">
                    <Send class="size-4" /> Kirim Ulang
                </Button>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-col gap-3">
            <!-- BR-SM-04 — kirim ulang tindakan ketua pengaju -->
            <AppBanner v-if="!can.resubmit" tone="lock">
                Hanya ketua pengaju yang dapat mengirim ulang pengajuan ini.
            </AppBanner>
            <AppBanner v-if="accountBlock" tone="denial">{{ accountBlock }}</AppBanner>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="flex min-w-0 flex-col gap-4">
                <h2 class="text-lg font-semibold">Catatan Reviewer</h2>
                <ReviewThreadCard
                    v-for="thread in threads"
                    :key="thread.id"
                    :thread="thread"
                    :can-reply="can.discussReview"
                />
                <EmptyState v-if="threads.length === 0" title="Belum ada catatan reviewer" />
            </section>
            <section class="flex min-w-0 flex-col gap-4">
                <h2 class="text-lg font-semibold">Edit Pengajuan</h2>
                <RevisionEditPanel
                    :submission-id="submission.id"
                    :missing-fields="missingFields"
                    :can-edit="can.update"
                />
            </section>
        </div>

        <ConfirmDialog
            v-model:open="confirming"
            title="Kirim ulang revisi?"
            description="Pengajuan kembali ke antrean review dan tidak dapat diubah selama direview."
            confirm-label="Kirim Ulang"
            :processing="sending"
            @confirm="resubmit"
        />
    </AppLayout>
</template>
