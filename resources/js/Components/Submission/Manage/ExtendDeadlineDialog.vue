<script setup lang="ts">
// Frame: OPERATOR-02-03
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import ActionDialog from '@/Components/app/ActionDialog.vue'
import OverrideStatusCard from '@/Components/Submission/Manage/OverrideStatusCard.vue'
import ReasonField from '@/Components/Submission/Manage/ReasonField.vue'
import { Input } from '@/Components/ui/input'
import { formatDate } from '@/lib/format'
import type { OverrideTarget } from '@/types/submission-manage'
import type { PhaseAccess } from '@/types/submission-page'

const props = defineProps<{
    submissionId: number
    phaseAccess: PhaseAccess
    target: OverrideTarget
}>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm({
    form_phase_detail_id: props.phaseAccess.form_phase_detail_id,
    expires_at: '',
    reason: '',
})
const date = ref('')

const tomorrow = () => {
    const day = new Date()
    day.setDate(day.getDate() + 1)
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}`
}

const current = computed(() => {
    const deadline = formatDate(props.phaseAccess.deadline)
    const items = [
        { label: 'Tahap', value: props.target.phaseLabel ?? '-' },
        {
            label: 'Tenggat Awal',
            value: props.phaseAccess.is_open ? deadline : `${deadline}, terlewat`,
        },
    ]
    if (props.target.affectedReviewers.length > 0) {
        items.push({
            label: 'Penilai Terdampak',
            value: props.target.affectedReviewers.join(', '),
        })
    }
    return items
})

// MON-CYCLE-09, RO-OUTPUT-16 — perpanjangan berlaku sampai akhir hari yang dipilih.
const send = () => {
    form.expires_at = date.value === '' ? '' : `${date.value} 23:59:59`
    form.post(route('admin.submissions.overrides.store', String(props.submissionId)), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            form.reset()
            date.value = ''
        },
    })
}
</script>

<template>
    <!-- BR-FE-08, BR-FE-09, BR-FE-10 — override per pengajuan dan per detail, wewenang Operator -->
    <ActionDialog
        v-model:open="open"
        title="Perpanjang Tenggat"
        submit-label="Perpanjang Tenggat"
        :processing="form.processing"
        :submit-disabled="phaseAccess.form_phase_detail_id === null"
        @submit="send"
    >
        <InfoGrid :items="current" />
        <OverrideStatusCard
            v-if="phaseAccess.override"
            :submission-id="submissionId"
            :override="phaseAccess.override"
        />
        <AppBanner v-if="phaseAccess.form_phase_detail_id === null" tone="lock">
            Pengajuan ini tidak tertaut ke tahap mana pun, jadi tidak ada tenggat untuk
            diperpanjang.
        </AppBanner>
        <template v-else>
            <FormField
                id="extend-date"
                label="Tanggal Tenggat Baru"
                required
                :error="form.errors.expires_at"
            >
                <template #default="{ describedBy }">
                    <Input
                        id="extend-date"
                        v-model="date"
                        type="date"
                        :min="tomorrow()"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>
            <ReasonField
                id="extend-reason"
                v-model="form.reason"
                label="Alasan Perpanjangan"
                placeholder="Wajib diisi, tercatat di riwayat sistem."
                required
                :error="form.errors.reason"
            />
            <AppBanner tone="info">
                Perpanjangan tercatat di riwayat sistem beserta alasan dan operator yang
                melakukannya.
            </AppBanner>
        </template>
    </ActionDialog>
</template>
