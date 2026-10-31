<script setup lang="ts">
import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import TargetOutputEditor from '@/Components/Submission/Wizard/TargetOutputEditor.vue'
import WizardFieldList from '@/Components/Submission/Wizard/WizardFieldList.vue'
import type { FilterOption, SubmissionTargetOutput } from '@/types/submission-page'
import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

const props = defineProps<{
    fields: WizardField[]
    values: Record<number, WizardFieldValue>
    errors: Record<string, string>
    submissionId: number
    targetOutputs: SubmissionTargetOutput[]
    targetOutputTypes: FilterOption[]
}>()

const emit = defineEmits<{ update: [fieldId: number, value: WizardFieldValue] }>()

// BR-SCH-05 — TRL lama belum tentu diizinkan skema baru; dikosongkan supaya dipilih ulang.
const update = (fieldId: number, value: WizardFieldValue) => {
    emit('update', fieldId, value)
    const changed = props.fields.find((f) => f.id === fieldId)
    if (changed?.type !== 'scheme_selector' || props.values[fieldId] === value) return
    props.fields
        .filter((f) => f.type === 'trl_selector')
        .forEach((trl) => emit('update', trl.id, null))
}
</script>

<template>
    <SectionCard title="Step 3 — Skema & TRL">
        <!-- BR-SCH-04 -->
        <AppBanner v-if="fields.length === 0" tone="info">
            Form ini tidak memakai skema — batas anggaran tidak berlaku.
        </AppBanner>
        <template v-else>
            <WizardFieldList :fields="fields" :values="values" :errors="errors" @update="update" />
            <AppBanner tone="info">
                Pilihan TRL disaring oleh skema yang dipilih. Batas anggaran dan pilihan target
                luaran juga mengikuti skema ini.
            </AppBanner>
        </template>
    </SectionCard>
    <TargetOutputEditor
        class="mt-6"
        :submission-id="submissionId"
        :target-outputs="targetOutputs"
        :types="targetOutputTypes"
    />
</template>
