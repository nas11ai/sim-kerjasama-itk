<script setup lang="ts">
import { computed, ref } from 'vue'

import WizardFieldList from '@/Components/Submission/Wizard/WizardFieldList.vue'
import type { EditableField } from '@/lib/formDefinition'
import type { FieldTypeOption } from '@/types/form-engine'
import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

// G3-W8 — Pratinjau Form = isian peneliti dari isi editor saat ini, dengan nilai lokal yang tidak
// pernah dikirim. Field baru belum punya id, jadi diberi id negatif sementara.
const props = defineProps<{ fields: EditableField[]; fieldTypeOptions: FieldTypeOption[] }>()

const values = ref<Record<number, WizardFieldValue>>({})

const wizardFields = computed<WizardField[]>(() =>
    props.fields.map((field, index) => ({
        id: field.id ?? -(index + 1),
        label: field.label,
        type:
            props.fieldTypeOptions.find((t) => t.value === String(field.fieldTypeId))?.name ??
            'text',
        required: field.isRequired,
        value: null,
        options: field.options
            .filter((option) => option.label.trim() !== '')
            .map((option, o) => ({ value: String(option.id ?? `new-${o}`), label: option.label })),
        fileName: null,
        downloadUrl: null,
    }))
)

const update = (fieldId: number, value: WizardFieldValue) =>
    (values.value = { ...values.value, [fieldId]: value })
</script>

<template>
    <div data-preview class="max-w-3xl space-y-4 rounded-md border p-4">
        <WizardFieldList :fields="wizardFields" :values="values" :errors="{}" @update="update" />
    </div>
</template>
