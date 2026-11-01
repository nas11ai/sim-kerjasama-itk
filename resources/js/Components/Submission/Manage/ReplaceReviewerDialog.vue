<script setup lang="ts">
// Frame: OPERATOR-03-02
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import ActionDialog from '@/Components/Submission/Manage/ActionDialog.vue'
import ReasonField from '@/Components/Submission/Manage/ReasonField.vue'
import ReviewerPicker from '@/Components/Submission/Manage/ReviewerPicker.vue'
import { useReviewerCandidates } from '@/composables/useReviewerCandidates'
import type { ManagedReviewer, ReviewerCandidate } from '@/types/submission-manage'

const props = defineProps<{
    submissionId: number
    outgoing: ManagedReviewer
    candidates?: ReviewerCandidate[]
}>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ incoming_reviewer_id: number | null; note: string }>({
    incoming_reviewer_id: null,
    note: '',
})
const { search, load } = useReviewerCandidates()
watch(open, (isOpen) => isOpen && load(), { immediate: true })

// Pengganti tunggal; yang pasti ditolak AssignReviewer tidak ditawarkan sama sekali.
const available = computed(() => (props.candidates ?? []).filter((c) => c.blockedReason === null))
const selected = computed(() =>
    form.incoming_reviewer_id === null ? [] : [form.incoming_reviewer_id]
)
const outgoingInfo = computed(() => [
    { label: 'Reviewer Lama', value: props.outgoing.name },
    { label: 'NIDN', value: props.outgoing.nidn ?? '—' },
    { label: 'Evaluasi', value: props.outgoing.evaluationLabel },
])
const errors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key !== 'note')
        .map(([, message]) => message)
)

const send = () =>
    form.post(
        route('admin.submissions.replace-reviewer', [
            String(props.submissionId),
            String(props.outgoing.reviewerId),
        ]),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false
                form.reset()
            },
        }
    )
</script>

<template>
    <!-- BR-REV-10, BR-REV-15 — yang lama ditandai replaced, ulir terbukanya diwarisi pengganti -->
    <ActionDialog
        v-model:open="open"
        title="Ganti Reviewer"
        submit-label="Ganti Reviewer"
        :processing="form.processing"
        :submit-disabled="form.incoming_reviewer_id === null"
        @submit="send"
    >
        <InfoGrid :items="outgoingInfo" />
        <p class="text-sm text-muted-foreground">
            Riwayat reviewer lama tetap tersimpan dan akan ditampilkan dengan label "Digantikan".
        </p>
        <ReviewerPicker
            v-model:search="search"
            :candidates="available"
            :selected="selected"
            :multiple="false"
            @toggle="form.incoming_reviewer_id = $event"
        />
        <ReasonField
            id="replace-note"
            v-model="form.note"
            label="Catatan Penggantian"
            placeholder="Opsional — tercatat di riwayat sistem."
            :error="form.errors.note"
        />
        <AppBanner v-for="message in errors" :key="message" tone="denial">{{ message }}</AppBanner>
    </ActionDialog>
</template>
