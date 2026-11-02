<script setup lang="ts">
// Frame: OPERATOR-03-01
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ActionDialog from '@/Components/app/ActionDialog.vue'
import ReviewerPicker from '@/Components/Submission/Manage/ReviewerPicker.vue'
import { useReviewerCandidates } from '@/composables/useReviewerCandidates'
import type { ReviewerCandidate } from '@/types/submission-manage'

const props = defineProps<{
    submissionId: number
    activeCount: number
    minimum: number
    candidates?: ReviewerCandidate[]
}>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ reviewer_ids: number[]; auto_assign_forms: boolean }>({
    reviewer_ids: [],
    auto_assign_forms: true,
})
const { search, load } = useReviewerCandidates()
watch(open, (isOpen) => isOpen && load(), { immediate: true })

// Galat guard datang di beberapa kunci (reviewer_id, user_id, reviewer_ids.N) — semuanya ditampilkan.
const errors = computed(() => Object.values(form.errors))

const toggle = (reviewerId: number) => {
    form.reviewer_ids = form.reviewer_ids.includes(reviewerId)
        ? form.reviewer_ids.filter((id) => id !== reviewerId)
        : [...form.reviewer_ids, reviewerId]
}

// REV-ASSIGN-01 — penugasan bertahap: satu pilihan sudah boleh dikirim (W27).
const send = () =>
    form.post(route('admin.submissions.assign-reviewers', String(props.submissionId)), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            form.reset()
        },
        // Respons galat penuh tak membawa prop opsional reviewerCandidates; muat ulang daftarnya.
        onError: load,
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Tugaskan Reviewer"
        submit-label="Tugaskan"
        :processing="form.processing"
        :submit-disabled="form.reviewer_ids.length === 0"
        @submit="send"
    >
        <ReviewerPicker
            v-model:search="search"
            :candidates="candidates ?? []"
            :selected="form.reviewer_ids"
            multiple
            @toggle="toggle"
        />
        <!-- BR-REV-01 — status pindah ke UNDER_REVIEW saat minimum tercapai -->
        <div class="rounded-md border p-3 text-sm">
            <p class="font-medium">
                {{ activeCount + form.reviewer_ids.length }} dari minimal {{ minimum }} reviewer
            </p>
            <p class="text-muted-foreground">
                Status pengajuan menjadi Direview setelah jumlah minimum terpenuhi.
            </p>
        </div>
        <AppBanner v-for="message in errors" :key="message" tone="denial">{{ message }}</AppBanner>
    </ActionDialog>
</template>
