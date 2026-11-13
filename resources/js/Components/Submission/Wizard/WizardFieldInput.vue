<script setup lang="ts">
import { computed } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import { Textarea } from '@/Components/ui/textarea'
import type { WizardField, WizardFieldType } from '@/types/submission-wizard'

const props = defineProps<{ field: WizardField; error?: string }>()
const model = defineModel<string | File | null>({ required: true })

// BR-SM-22 — submission_title diisi seperti teks biasa; perannya ada di server.
const INPUT_TYPES: Partial<Record<WizardFieldType, string>> = {
    phone: 'tel',
    submission_title: 'text',
}
const NOT_INPUT: WizardFieldType[] = [
    'textarea',
    'select',
    'scheme_selector',
    'trl_selector',
    'radio',
    'checkbox',
    'file',
]
const SELECT_TYPES: WizardFieldType[] = ['select', 'scheme_selector', 'trl_selector']

const id = computed(() => `field-${props.field.id}`)
const inputType = computed(() =>
    NOT_INPUT.includes(props.field.type)
        ? null
        : (INPUT_TYPES[props.field.type] ?? props.field.type)
)
// BR-SCH-05 — TRL baru bisa dipilih setelah skema; opsinya dari izin skema itu.
const awaitingScheme = computed(
    () => props.field.type === 'trl_selector' && props.field.options.length === 0
)
const text = computed({
    get: () => (typeof model.value === 'string' ? model.value : ''),
    set: (value: string | number) => (model.value = String(value)),
})
const choice = computed(() => (typeof model.value === 'string' ? model.value : null))

const choose = (value: unknown) => (model.value = typeof value === 'string' ? value : null)
const check = (value: boolean | 'indeterminate') => (model.value = value === true ? '1' : '0')
const pickFile = (event: Event) => {
    if (event.target instanceof HTMLInputElement) model.value = event.target.files?.[0] ?? null
}
</script>

<template>
    <FormField :id="id" :label="field.label" :required="field.required" :error="error">
        <template #default="{ describedBy }">
            <Input
                v-if="inputType"
                :id="id"
                v-model="text"
                :type="inputType"
                :aria-describedby="describedBy"
            />
            <Textarea
                v-else-if="field.type === 'textarea'"
                :id="id"
                v-model="text"
                :aria-describedby="describedBy"
            />
            <OptionSelect
                v-else-if="SELECT_TYPES.includes(field.type)"
                :id="id"
                :model-value="choice"
                :options="field.options"
                :disabled="awaitingScheme"
                :placeholder="awaitingScheme ? 'Pilih skema terlebih dahulu' : undefined"
                :described-by="describedBy"
                @update:model-value="choose"
            />
            <RadioGroup
                v-else-if="field.type === 'radio'"
                :id="id"
                :model-value="choice ?? undefined"
                @update:model-value="choose"
            >
                <label
                    v-for="option in field.options"
                    :key="option.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <RadioGroupItem :value="option.value" /> {{ option.label }}
                </label>
            </RadioGroup>
            <Checkbox
                v-else-if="field.type === 'checkbox'"
                :id="id"
                :model-value="model === '1'"
                @update:model-value="check"
            />
            <div v-else class="flex flex-col gap-1">
                <input
                    :id="id"
                    type="file"
                    class="text-sm file:mr-3 file:rounded-md file:border file:bg-transparent file:px-3 file:py-1"
                    :aria-describedby="describedBy"
                    @change="pickFile"
                />
                <a
                    v-if="field.fileName && field.downloadUrl"
                    :href="field.downloadUrl"
                    class="text-sm text-primary underline-offset-4 hover:underline"
                >
                    Berkas tersimpan: {{ field.fileName }}
                </a>
            </div>
        </template>
    </FormField>
</template>
