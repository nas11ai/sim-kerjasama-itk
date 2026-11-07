<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import ReviewerPicker from '@/Components/Submission/Manage/ReviewerPicker.vue'
import { useReviewerCandidates } from '@/composables/useReviewerCandidates'
import type { ReviewerCandidate } from '@/types/submission-manage'

const props = defineProps<{
    parentId: number
    detailId: number
    suggested: number[]
    candidates?: ReviewerCandidate[]
}>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ reviewer_id: number | null }>({ reviewer_id: null })
const { search, load } = useReviewerCandidates({ reviewer_cycle: props.detailId })
watch(open, (isOpen) => isOpen && load(), { immediate: true })

// BR-MON-07 — saran naik ke atas (≤ 20 baris dari server), tetapi tidak menyaring.
const sorted = computed(() =>
    [...(props.candidates ?? [])].sort(
        (a, b) =>
            Number(props.suggested.includes(b.reviewerId)) -
            Number(props.suggested.includes(a.reviewerId))
    )
)
const selected = computed(() => (form.reviewer_id === null ? [] : [form.reviewer_id]))
const errors = computed(() => Object.values(form.errors))

const close = () => {
    open.value = false
    form.reset()
}

// Galat penuh tak membawa prop opsional reviewerCandidates, jadi daftarnya dimuat ulang.
const target = () =>
    route('monev.manage.reviewers.store', { submission: props.parentId, detail: props.detailId })
const send = () => form.post(target(), { preserveScroll: true, onSuccess: close, onError: load })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Tugaskan Reviewer Monev"
        submit-label="Tugaskan"
        :processing="form.processing"
        :submit-disabled="form.reviewer_id === null"
        @submit="send"
    >
        <ReviewerPicker
            v-model:search="search"
            :candidates="sorted"
            :selected="selected"
            :multiple="false"
            :suggested="suggested"
            @toggle="(id) => (form.reviewer_id = id)"
        />
        <p class="text-xs text-muted-foreground">
            Reviewer pengajuan awal yang masih aktif disarankan lebih dulu.
        </p>
        <AppBanner v-for="message in errors" :key="message" tone="denial">{{ message }}</AppBanner>
    </ActionDialog>
</template>
