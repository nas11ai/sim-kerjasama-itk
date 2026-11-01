<script setup lang="ts">
// Frame: OPERATOR-02-04
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import ActionDialog from '@/Components/Submission/Manage/ActionDialog.vue'
import ReasonField from '@/Components/Submission/Manage/ReasonField.vue'
import type { OwnershipCandidate } from '@/types/submission-manage'
import type { SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{ submission: SubmissionDetail; candidates: OwnershipCandidate[] }>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ successor_id: string | null; reason: string }>({
    successor_id: null,
    reason: '',
})

const current = computed(() => [
    { label: 'Ketua', value: props.submission.leadName },
    {
        label: 'Anggota Inti Aktif',
        value: props.candidates.map((candidate) => candidate.name).join(', ') || '—',
    },
])

const options = computed(() =>
    props.candidates.map((candidate) => ({
        value: String(candidate.userId),
        label: [candidate.name, candidate.organization].filter(Boolean).join(' — '),
    }))
)

const send = () =>
    form.post(route('admin.submissions.transfer-ownership', String(props.submission.id)), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            form.reset()
        },
    })
</script>

<template>
    <!-- BR-SM-09, BR-IAM-10 — penerima hanya co-investigator aktif -->
    <ActionDialog
        v-model:open="open"
        title="Alihkan Kepemilikan"
        submit-label="Alihkan Kepemilikan"
        :processing="form.processing"
        @submit="send"
    >
        <InfoGrid :items="current" />
        <FormField
            id="transfer-successor"
            label="Ketua Baru"
            required
            hint="Hanya anggota inti yang aktif yang dapat dipilih."
            :error="form.errors.successor_id"
        >
            <template #default="{ describedBy }">
                <OptionSelect
                    id="transfer-successor"
                    v-model="form.successor_id"
                    :options="options"
                    placeholder="Pilih anggota inti"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <ReasonField
            id="transfer-reason"
            v-model="form.reason"
            label="Alasan Pengalihan"
            placeholder="Wajib diisi — tercatat di riwayat sistem."
            required
            :error="form.errors.reason"
        />
        <AppBanner tone="info">
            Setelah pengalihan, peran ketua pengaju berpindah — ketua lama tetap menjadi anggota
            tim. Aksi ini tercatat di riwayat sistem.
        </AppBanner>
    </ActionDialog>
</template>
