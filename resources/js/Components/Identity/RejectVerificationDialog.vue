<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import ReasonField from '@/Components/Submission/Manage/ReasonField.vue'
import type { VerificationCardData } from '@/types/identity'

// BR-IAM-13, IAM-VERIFY-07/08 — penolakan wajib beralasan; tombolnya mati selama alasan kosong.
const props = defineProps<{ card: VerificationCardData }>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm({ reason: '' })

const summary = computed(() => [
    { label: 'Pendaftar', value: props.card.name },
    { label: 'NIDN diajukan', value: props.card.nidn ?? '—' },
])

const send = () =>
    form.post(route('admin.verifications.reject', props.card.profileId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            form.reset()
        },
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Tolak Pendaftaran"
        submit-label="Tolak Pendaftaran"
        destructive
        :processing="form.processing"
        :submit-disabled="form.reason.trim() === ''"
        @submit="send"
    >
        <InfoGrid :items="summary" />
        <ReasonField
            id="verification-reject-reason"
            v-model="form.reason"
            label="Alasan penolakan"
            placeholder="NIDN tidak ditemukan di pangkalan data PDDikti atas nama yang sama."
            hint="Dikirimkan ke pendaftar dan tersimpan sebagai jejak keputusan."
            required
            :error="form.errors.reason"
        />
        <AppBanner tone="info">
            Status akun menjadi Ditolak. Pendaftar tetap dapat masuk dan melihat alasan ini, tapi
            belum dapat mengirim pengajuan.
        </AppBanner>
    </ActionDialog>
</template>
