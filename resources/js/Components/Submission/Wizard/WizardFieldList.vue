<script setup lang="ts">
import EmptyState from '@/Components/app/EmptyState.vue'
import WizardFieldInput from '@/Components/Submission/Wizard/WizardFieldInput.vue'
import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

defineProps<{
    fields: WizardField[]
    values: Record<number, WizardFieldValue>
    errors: Record<string, string>
}>()

const emit = defineEmits<{ update: [fieldId: number, value: WizardFieldValue] }>()
</script>

<template>
    <EmptyState v-if="fields.length === 0" title="Formulir ini tidak punya isian di langkah ini." />
    <WizardFieldInput
        v-for="field in fields"
        v-else
        :key="field.id"
        :field="field"
        :model-value="values[field.id] ?? null"
        :error="errors[`field_${field.id}`]"
        @update:model-value="(value) => emit('update', field.id, value)"
    />
</template>
