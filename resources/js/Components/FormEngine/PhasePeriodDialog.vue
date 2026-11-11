<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import type { PhaseCardData } from '@/types/form-engine'

// K9b — penautan longgar: semua periode layak dari backend; tenggat detail dipindah sesudahnya.
const props = defineProps<{ phase: PhaseCardData | null }>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ submission_period_id: string | null }>({ submission_period_id: null })

watch(open, (isOpen) => {
    if (!isOpen) return
    form.reset()
    form.clearErrors()
})

const send = () => {
    if (!props.phase) return
    form.post(route('admin.phases.periods.store', props.phase.id), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    })
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Hubungkan ke Periode"
        :description="phase?.title"
        submit-label="Hubungkan"
        :processing="form.processing"
        :submit-disabled="form.submission_period_id === null"
        @submit="send"
    >
        <FormField
            id="phase-period"
            label="Periode"
            required
            hint="Detail tahap baru terbuka di periode ini setelah tenggatnya dipindah ke tanggal periode ini."
            :error="form.errors.submission_period_id"
        >
            <template #default="{ describedBy }">
                <OptionSelect
                    id="phase-period"
                    v-model="form.submission_period_id"
                    :options="phase?.linkablePeriods ?? []"
                    placeholder="Pilih periode"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
    </ActionDialog>
</template>
