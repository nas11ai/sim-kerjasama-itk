import type { FieldTypeOption, FormFieldEditData } from '@/types/form-engine'

// Field di editor. `key` stabil untuk v-for/seret; field baru `new-<n>`, field lama `field-<id>`.
export type EditableOption = { id: number | null; label: string }

export type EditableField = {
    key: string
    id: number | null
    label: string
    fieldTypeId: number
    isRequired: boolean
    requiredSince: string | null
    answerCount: number
    options: EditableOption[]
}

export type DefinitionFieldPayload = {
    id?: number
    field_type_id: number
    label: string
    is_required: boolean
    options?: { id?: number; label: string }[]
}

let newCounter = 0

export function blankField(fieldTypeId: number): EditableField {
    newCounter += 1
    return {
        key: `new-${newCounter}`,
        id: null,
        label: '',
        fieldTypeId,
        isRequired: false,
        requiredSince: null,
        answerCount: 0,
        options: [],
    }
}

export function toEditableFields(fields: FormFieldEditData[]): EditableField[] {
    return fields.map((field) => ({
        ...field,
        key: `field-${field.id}`,
        options: field.options.map((option) => ({ ...option })),
    }))
}

export function moveItem<T>(items: T[], from: number, to: number): T[] {
    const copy = [...items]
    const inside = (index: number) => index >= 0 && index < copy.length
    if (!inside(from) || !inside(to)) return copy
    const [moved] = copy.splice(from, 1)
    if (moved !== undefined) copy.splice(to, 0, moved)
    return copy
}

// BR-FE-24 — `options` hanya untuk tipe ber-opsi; tanpa kunci, opsi tersimpan tak berubah (FE-CHANGE-14).
export function toDefinitionFields(
    fields: EditableField[],
    types: FieldTypeOption[]
): DefinitionFieldPayload[] {
    return fields.map((field) => {
        const payload: DefinitionFieldPayload = {
            ...(field.id === null ? {} : { id: field.id }),
            field_type_id: field.fieldTypeId,
            label: field.label,
            is_required: field.isRequired,
        }
        const hasOptions = types.find((t) => t.value === String(field.fieldTypeId))?.hasOptions
        if (hasOptions !== true) return payload
        payload.options = field.options.map((option) => ({
            ...(option.id === null ? {} : { id: option.id }),
            label: option.label,
        }))
        return payload
    })
}
