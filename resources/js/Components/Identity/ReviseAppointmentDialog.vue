<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import TenureFields from '@/Components/Identity/TenureFields.vue'
import type { Option, ReviewerRowData } from '@/types/identity'

// BR-REV-09/10 — masa tugas diubah atau diperpanjang; mengakhirinya menggantikan "Hapus" (W50).
const props = defineProps<{ reviewer: ReviewerRowData; typeOptions: Option[] }>()
const open = defineModel<boolean>('open', { required: true })

const ended = computed(() => props.reviewer.standing.value === 'ended')

type TenureForm = { reviewer_type: string | null; start_date: string; end_date: string }

const form = useForm<TenureForm>({
    reviewer_type: props.reviewer.type.value,
    start_date: props.reviewer.tenureStart,
    end_date: props.reviewer.tenureEnd ?? '',
})

const send = () =>
    form.put(route('admin.reviewers.update', props.reviewer.id), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="ended ? 'Perpanjang Masa Tugas' : 'Ubah Periode'"
        :description="reviewer.name"
        submit-label="Simpan"
        :processing="form.processing"
        @submit="send"
    >
        <!-- REV-ASSIGN-08 — masa tugas ditegakkan saat penugasan -->
        <AppBanner v-if="ended" tone="warning">
            Masa tugas {{ reviewer.name }} sudah berakhir. Perpanjang lebih dulu sebelum
            menugaskannya.
        </AppBanner>
        <TenureFields
            v-model:type="form.reviewer_type"
            v-model:start="form.start_date"
            v-model:end="form.end_date"
            :type-options="typeOptions"
            :previous-type="reviewer.type.value"
            :errors="form.errors"
        />
    </ActionDialog>
</template>
