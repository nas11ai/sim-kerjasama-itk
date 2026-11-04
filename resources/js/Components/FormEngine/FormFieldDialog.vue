<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import FormFieldOptions from '@/Components/FormEngine/FormFieldOptions.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { blankField, keptOptions, optionError, type EditableField } from '@/lib/formDefinition'
import type { FieldTypeOption } from '@/types/form-engine'

const props = defineProps<{ field: EditableField | null; fieldTypeOptions: FieldTypeOption[] }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [EditableField] }>()

const draft = ref<EditableField>(blankField(0))
const wasRequired = ref(false)
const errors = ref<{ label?: string; options?: string }>({})

const fill = () => {
    const first = Number(props.fieldTypeOptions[0]?.value ?? 0)
    const source = props.field
    draft.value = source
        ? { ...source, options: source.options.map((option) => ({ ...option })) }
        : blankField(first)
    wasRequired.value = source?.isRequired ?? false
    errors.value = {}
}
watch(open, (isOpen) => isOpen && fill(), { immediate: true })

// BR-FE-23 / FE-CHANGE-07, 09, 10 — satu jawaban pun mengunci tipe.
const locked = computed(() => draft.value.answerCount > 0)
const lockHint = computed(() =>
    locked.value
        ? `Tipe terkunci: sudah ada ${draft.value.answerCount} jawaban. Tambahkan field baru untuk tipe lain; field ini tetap terbaca apa adanya.`
        : undefined
)
const hasOptions = computed(
    () =>
        props.fieldTypeOptions.find((t) => t.value === String(draft.value.fieldTypeId))
            ?.hasOptions === true
)
// BR-FE-18 / FE-CHANGE-06 — hanya field tersimpan yang berubah dari opsional menjadi wajib.
const becomesRequired = computed(
    () => draft.value.id !== null && !wasRequired.value && draft.value.isRequired
)

const type = computed({
    get: () => String(draft.value.fieldTypeId),
    set: (value: string | null) => {
        if (value !== null) draft.value.fieldTypeId = Number(value)
    },
})

function save() {
    const label = draft.value.label.trim()
    errors.value = {
        label: label === '' ? 'Label wajib diisi' : undefined,
        options: optionError(draft.value.options, hasOptions.value),
    }
    if (errors.value.label || errors.value.options) return
    emit('save', { ...draft.value, label, options: keptOptions(draft.value.options) })
    open.value = false
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="field ? 'Edit Field' : 'Tambah Field'"
        submit-label="Simpan Field"
        @submit="save"
    >
        <FormField id="field-label" label="Label" required :error="errors.label">
            <Input id="field-label" v-model="draft.label" maxlength="255" />
        </FormField>
        <FormField id="field-type" label="Tipe" :hint="lockHint">
            <template #default="{ describedBy }">
                <OptionSelect
                    id="field-type"
                    v-model="type"
                    :options="fieldTypeOptions"
                    :disabled="locked"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <div class="space-y-1.5">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="field-required"
                    :model-value="draft.isRequired"
                    @update:model-value="(checked) => (draft.isRequired = checked === true)"
                />
                <Label for="field-required" class="font-normal">Wajib diisi</Label>
            </div>
            <!-- BR-FE-18 / FE-CHANGE-06 -->
            <p v-if="becomesRequired" class="text-xs text-muted-foreground">
                Berlaku untuk pengajuan yang dimulai setelah disimpan; draft berjalan tidak dipaksa
                mengisi ulang.
            </p>
        </div>
        <FormFieldOptions v-if="hasOptions" v-model="draft.options" :error="errors.options" />
    </ActionDialog>
</template>
