<script setup lang="ts">
// Frame: OPERATOR-02-06
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import ActionDialog from '@/Components/app/ActionDialog.vue'
import ReasonField from '@/Components/Submission/Manage/ReasonField.vue'
import { useConfirmedReason } from '@/composables/useConfirmedReason'
import type { SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{ submission: SubmissionDetail }>()
const open = defineModel<boolean>('open', { required: true })

const { form, confirming, denials, send } = useConfirmedReason(
    () => route('admin.submissions.withdraw', String(props.submission.id)),
    open
)

// W29 — "Siklus Monev Aktif" dan "Luaran Terdaftar" di frame tidak digambar.
const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Ketua', value: props.submission.leadName },
    { label: 'Skema', value: props.submission.scheme ?? '—' },
])
</script>

<template>
    <!-- BR-SM-11 — hanya dari APPROVED, final -->
    <ActionDialog
        v-model:open="open"
        title="Tarik Pengajuan"
        submit-label="Tarik Pengajuan"
        destructive
        :processing="form.processing"
        @submit="confirming = true"
    >
        <AppBanner tone="warning">
            <strong>Penarikan pengajuan yang sudah disetujui.</strong> Monev dan pelaporan luaran
            untuk penelitian ini akan dihentikan.
        </AppBanner>
        <InfoGrid :items="summary" />
        <AppBanner v-for="message in denials" :key="message" tone="denial">
            {{ message }}
        </AppBanner>
        <ReasonField
            id="withdraw-reason"
            v-model="form.reason"
            label="Alasan"
            placeholder="Misal: permintaan researcher, duplikasi pendanaan, force majeure."
            required
            :error="form.errors.reason"
        />
    </ActionDialog>
    <ConfirmDialog
        v-model:open="confirming"
        title="Tarik pengajuan ini?"
        description="Data penelitian tetap tersimpan sebagai arsip baca-saja."
        confirm-label="Tarik"
        destructive
        :processing="form.processing"
        @confirm="send"
    />
</template>
