import { ref, type Ref } from 'vue'

import { keptOptions, optionError, type EditableOption } from '@/lib/formDefinition'
import type { FieldTypeOption } from '@/types/form-engine'

type DraftField = { label: string; fieldTypeId: number; options: EditableOption[] }

export type FieldDraftErrors = { label?: string; options?: string }

// Simpan dialog field (form maupun form evaluasi): label wajib, opsi diperiksa (BR-FE-24),
// baris opsi baru yang kosong dibuang, lalu dialog ditutup.
export function useFieldDraftSave<T extends DraftField>(
    draft: Ref<T>,
    props: { fieldTypeOptions: FieldTypeOption[] },
    open: Ref<boolean>,
    emit: (event: 'save', field: T) => void
) {
    const errors = ref<FieldDraftErrors>({})

    const save = () => {
        const label = draft.value.label.trim()
        const type = props.fieldTypeOptions.find((t) => t.value === String(draft.value.fieldTypeId))
        errors.value = {
            label: label === '' ? 'Label wajib diisi' : undefined,
            options: optionError(draft.value.options, type?.hasOptions === true),
        }
        if (errors.value.label || errors.value.options) return
        emit('save', { ...draft.value, label, options: keptOptions(draft.value.options) })
        open.value = false
    }

    return { errors, save }
}
