<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import FormFieldOptions from '@/Components/FormEngine/FormFieldOptions.vue'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
import {
    blankEvaluationField,
    LIMIT_KEYS,
    type EditableEvaluationField,
} from '@/lib/evaluationDefinition'
import { keptOptions, optionError, type EditableOption } from '@/lib/formDefinition'
import type { EvaluationLimits, FieldTypeOption } from '@/types/form-engine'

const props = defineProps<{
    field: EditableEvaluationField | null
    fieldTypeOptions: FieldTypeOption[]
}>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [EditableEvaluationField] }>()

const draft = ref<EditableEvaluationField>(blankEvaluationField(0))
const errors = ref<{ label?: string; options?: string }>({})

watch(
    open,
    (isOpen) => {
        if (!isOpen) return
        errors.value = {}
        const source = props.field
        draft.value = source
            ? {
                  ...source,
                  limits: { ...source.limits },
                  options: source.options.map((o) => ({ ...o })),
              }
            : blankEvaluationField(Number(props.fieldTypeOptions[0]?.value ?? 0))
    },
    { immediate: true }
)

const selected = computed(() =>
    props.fieldTypeOptions.find((t) => t.value === String(draft.value.fieldTypeId))
)
// Cermin BR-FE-23 / W103 — field yang sudah dijawab reviewer tidak berganti tipe.
const locked = computed(() => draft.value.answerCount > 0)
const type = computed({
    get: () => String(draft.value.fieldTypeId),
    set: (value: string | null) => value !== null && (draft.value.fieldTypeId = Number(value)),
})
const description = computed({
    get: () => draft.value.description ?? '',
    set: (value: string | number) => (draft.value.description = String(value)),
})

// W103 — batas panjang untuk tipe teks, batas nilai untuk angka; jenisnya dari backend.
const LIMIT_LABELS: Record<keyof EvaluationLimits, string> = {
    minLength: 'Panjang minimum',
    maxLength: 'Panjang maksimum',
    minValue: 'Nilai minimum',
    maxValue: 'Nilai maksimum',
}
const limitKeys = computed(() => {
    const kind = selected.value?.limitKind ?? null
    return kind === null ? [] : LIMIT_KEYS[kind].map(([key]) => key)
})
const setLimit = (key: keyof EvaluationLimits, value: string | number) =>
    (draft.value.limits = { ...draft.value.limits, [key]: value === '' ? null : Number(value) })

// FormFieldOptions membuat baris baru tanpa `value`; baris lama dipertahankan apa adanya.
const options = computed({
    get: (): EditableOption[] => draft.value.options,
    set: (next: EditableOption[]) =>
        (draft.value.options = next.map(
            (o) => draft.value.options.find((old) => old === o) ?? { ...o, value: null }
        )),
})

function save() {
    const label = draft.value.label.trim()
    const hasOptions = selected.value?.hasOptions === true
    errors.value = {
        label: label === '' ? 'Label wajib diisi' : undefined,
        options: optionError(draft.value.options, hasOptions),
    }
    if (errors.value.label !== undefined || errors.value.options !== undefined) return
    emit('save', { ...draft.value, label, options: keptOptions(draft.value.options) })
    open.value = false
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="field ? 'Edit Field Evaluasi' : 'Tambah Field Evaluasi'"
        submit-label="Simpan Field"
        @submit="save"
    >
        <FormField id="evaluation-field-label" label="Label" required :error="errors.label">
            <Input id="evaluation-field-label" v-model="draft.label" maxlength="255" />
        </FormField>
        <FormField id="evaluation-field-description" label="Deskripsi">
            <Textarea id="evaluation-field-description" v-model="description" rows="2" />
        </FormField>
        <FormField
            id="evaluation-field-type"
            label="Tipe"
            :hint="
                locked ? 'Field ini sudah dijawab reviewer; buat field baru untuk tipe lain.' : ''
            "
        >
            <template #default="{ describedBy }">
                <OptionSelect
                    id="evaluation-field-type"
                    v-model="type"
                    :options="fieldTypeOptions"
                    :disabled="locked"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <div class="flex items-center gap-2">
            <Switch id="evaluation-field-required" v-model="draft.isRequired" />
            <Label for="evaluation-field-required" class="font-normal">Wajib diisi</Label>
        </div>
        <div v-if="limitKeys.length > 0" class="grid gap-4 sm:grid-cols-2">
            <FormField
                v-for="key in limitKeys"
                :id="`evaluation-limit-${key}`"
                :key="key"
                :label="LIMIT_LABELS[key]"
            >
                <Input
                    :id="`evaluation-limit-${key}`"
                    type="number"
                    :model-value="draft.limits[key] ?? ''"
                    @update:model-value="(value) => setLimit(key, value)"
                />
            </FormField>
        </div>
        <FormFieldOptions v-if="selected?.hasOptions" v-model="options" :error="errors.options" />
    </ActionDialog>
</template>
