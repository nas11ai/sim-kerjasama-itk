<script setup lang="ts">
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import type { OutputFieldSpec, OutputMetadataValue } from '@/types/research-output'

// BR-RO-09 — kontrol dirender dari spesifikasi tipe, bukan ditulis tangan per tipe.
defineProps<{ fields: OutputFieldSpec[]; errors: Record<string, string> }>()
const metadata = defineModel<Record<string, OutputMetadataValue>>({ required: true })

const textOf = (key: string): string => {
    const value = metadata.value[key]
    return value === null || value === undefined ? '' : String(value)
}
const assign = (key: string, value: unknown) => {
    metadata.value[key] = typeof value === 'string' || typeof value === 'number' ? value : null
}
</script>

<template>
    <FormField
        v-for="spec in fields"
        :id="`metadata-${spec.key}`"
        :key="spec.key"
        :label="spec.label"
        :required="spec.required"
        :hint="spec.hint ?? undefined"
        :error="errors[`metadata.${spec.key}`]"
    >
        <template #default="{ describedBy }">
            <Textarea
                v-if="spec.type === 'textarea'"
                :id="`metadata-${spec.key}`"
                :model-value="textOf(spec.key)"
                :aria-describedby="describedBy"
                @update:model-value="assign(spec.key, $event)"
            />
            <OptionSelect
                v-else-if="spec.type === 'select'"
                :id="`metadata-${spec.key}`"
                :model-value="textOf(spec.key) || null"
                :options="spec.options"
                :described-by="describedBy"
                @update:model-value="assign(spec.key, $event)"
            />
            <Input
                v-else
                :id="`metadata-${spec.key}`"
                :type="spec.type"
                :model-value="textOf(spec.key)"
                :aria-describedby="describedBy"
                @update:model-value="assign(spec.key, $event)"
            />
        </template>
    </FormField>
</template>
