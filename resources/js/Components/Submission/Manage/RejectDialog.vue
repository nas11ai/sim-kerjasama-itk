<script setup lang="ts">
// Frame: OPERATOR-02-05
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import ActionDialog from '@/Components/Submission/Manage/ActionDialog.vue'
import ReasonField from '@/Components/Submission/Manage/ReasonField.vue'
import { useConfirmedReason } from '@/composables/useConfirmedReason'
import type { SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{ submission: SubmissionDetail; activeReviewerCount: number }>()
const open = defineModel<boolean>('open', { required: true })

const { form, confirming, send } = useConfirmedReason(
    () => route('admin.submissions.reject', String(props.submission.id)),
    open
)

const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Ketua', value: props.submission.leadName },
    { label: 'Status Saat Ini', value: props.submission.status.label },
    {
        label: 'Reviewer Aktif',
        value: `${props.activeReviewerCount} — evaluasi akan dihentikan`,
    },
])
</script>

<template>
    <!-- BR-REV-07 — penolakan hanya manual, beralasan tertulis -->
    <ActionDialog
        v-model:open="open"
        title="Tolak Manual"
        submit-label="Tolak Pengajuan"
        destructive
        :processing="form.processing"
        @submit="confirming = true"
    >
        <AppBanner tone="warning">
            <strong>Aksi ini tidak dapat dibatalkan.</strong> Gunakan setelah meninjau hasil
            evaluasi reviewer, atau untuk kondisi khusus seperti plagiarisme dan pelanggaran
            administratif.
        </AppBanner>
        <InfoGrid :items="summary" />
        <ReasonField
            id="reject-reason"
            v-model="form.reason"
            label="Penjelasan Detail"
            placeholder="Dikirimkan ke researcher via notifikasi — tulis dengan jelas."
            required
            :error="form.errors.reason"
        />
    </ActionDialog>
    <ConfirmDialog
        v-model:open="confirming"
        title="Tolak pengajuan ini?"
        description="Pengajuan yang ditolak tidak dapat diaktifkan kembali."
        confirm-label="Tolak"
        destructive
        :processing="form.processing"
        @confirm="send"
    />
</template>
