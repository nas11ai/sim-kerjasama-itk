<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import FormField from '@/Components/app/FormField.vue'
import FieldTypeSelect from '@/Components/FormEngine/FieldTypeSelect.vue'
import FormFieldOptions from '@/Components/FormEngine/FormFieldOptions.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { useFieldDraftSave } from '@/composables/useFieldDraftSave'
import AppBanner from '@/Components/app/AppBanner.vue'
import {
    blankField,
    RELABEL_WARNING,
    relabelsAnsweredField,
    type EditableField,
} from '@/lib/formDefinition'
import type { FieldTypeOption, FormFieldEditData } from '@/types/form-engine'

const props = defineProps<{
    field: EditableField | null
    fieldTypeOptions: FieldTypeOption[]
    // G3-W9 — versi terbit field ini, pembanding peringatan label; tanpa = field baru.
    published?: FormFieldEditData
}>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [EditableField] }>()

const draft = ref<EditableField>(blankField(0))
const wasRequired = ref(false)
const { errors, save } = useFieldDraftSave(draft, props, open, emit)

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
// FE-FORMPUB-04 — label field/opsi terjawab yang berbeda dari terbitan diperingatkan, tidak diblokir.
const relabels = computed(() => relabelsAnsweredField(draft.value, props.published))
// BR-FE-18 / FE-CHANGE-06 — hanya field tersimpan yang berubah dari opsional menjadi wajib.
const becomesRequired = computed(
    () => draft.value.id !== null && !wasRequired.value && draft.value.isRequired
)
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
        <AppBanner v-if="relabels" tone="warning">{{ RELABEL_WARNING }}</AppBanner>
        <FieldTypeSelect
            id="field-type"
            v-model="draft.fieldTypeId"
            :options="fieldTypeOptions"
            :locked="locked"
            :hint="lockHint"
        />
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
                Berlaku untuk pengajuan yang dimulai setelah diterbitkan; draft berjalan tidak
                dipaksa mengisi ulang.
            </p>
        </div>
        <FormFieldOptions v-if="hasOptions" v-model="draft.options" :error="errors.options" />
    </ActionDialog>
</template>
