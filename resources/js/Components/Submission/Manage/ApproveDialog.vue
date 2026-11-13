<script setup lang="ts">
// BR-REV-05, ADR 0018 — persetujuan oleh Operator
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import { roundProgressLabel } from '@/lib/reviewRound'
import type { DecisionReadiness } from '@/types/submission-manage'
import type { SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{ submission: SubmissionDetail; readiness: DecisionReadiness }>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm({})
// Galat Action (`approve`, mis. catatan yang terbuka sesudah halaman dimuat) tampil sebagai banner.
const denials = computed(() =>
    Object.values(form.errors).filter((message): message is string => typeof message === 'string')
)

const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Ketua', value: props.submission.leadName },
    { label: 'Status Saat Ini', value: props.submission.status.label },
    {
        label: 'Putaran Penilaian',
        value:
            props.readiness.round === null
                ? 'Tanpa putaran penilaian.'
                : roundProgressLabel(props.readiness.round),
    },
])

const send = () =>
    form.post(route('admin.submissions.approve', String(props.submission.id)), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
        },
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Setujui Pengajuan"
        submit-label="Setujui Pengajuan"
        :processing="form.processing"
        @submit="send"
    >
        <AppBanner tone="warning">
            Pengajuan yang disetujui tidak dapat diubah lagi; peneliti dan anggota tim diberi tahu.
        </AppBanner>
        <InfoGrid :items="summary" />
        <AppBanner v-for="message in denials" :key="message" tone="denial">
            {{ message }}
        </AppBanner>
    </ActionDialog>
</template>
