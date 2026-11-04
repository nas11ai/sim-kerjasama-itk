import type {
    EvaluationFieldEditData,
    EvaluationFormEditData,
    EvaluationLimits,
    FieldTypeOption,
} from '@/types/form-engine'

// Field form evaluasi di kartu. `key` stabil untuk seret; field baru `new-<n>`, field lama `field-<id>`.
export type EditableEvaluationOption = { id: number | null; label: string; value: string | null }

export type EditableEvaluationField = Omit<EvaluationFieldEditData, 'id' | 'options'> & {
    key: string
    id: number | null
    options: EditableEvaluationOption[]
}

export type EvaluationGeneral = {
    title: string
    description: string
    isRequired: boolean
    isActive: boolean
}

type LimitRules = {
    min_length?: number
    max_length?: number
    min_value?: number
    max_value?: number
}

export type EvaluationFieldPayload = {
    id?: number
    field_type_id: number
    label: string
    description: string | null
    is_required: boolean
    validation_rules: LimitRules | null
    options?: { id?: number; label: string; value: string | null }[]
}

export type EvaluationPayload = {
    title: string
    description: string | null
    is_required: boolean
    is_active: boolean
    fields: EvaluationFieldPayload[]
}

const NO_LIMITS: EvaluationLimits = {
    minLength: null,
    maxLength: null,
    minValue: null,
    maxValue: null,
}

// W103 — batas per jenis tipe, dibaca dari `limitKind` backend.
export const LIMIT_KEYS = {
    length: [
        ['minLength', 'min_length'],
        ['maxLength', 'max_length'],
    ],
    value: [
        ['minValue', 'min_value'],
        ['maxValue', 'max_value'],
    ],
} as const

let newCounter = 0

export function blankEvaluationField(fieldTypeId: number): EditableEvaluationField {
    newCounter += 1
    return {
        key: `new-${newCounter}`,
        id: null,
        label: '',
        description: null,
        fieldTypeId,
        isRequired: false,
        limits: { ...NO_LIMITS },
        answerCount: 0,
        options: [],
    }
}

export function toEditableEvaluationFields(
    fields: EvaluationFieldEditData[]
): EditableEvaluationField[] {
    return fields.map((field) => ({
        ...field,
        key: `field-${field.id}`,
        limits: { ...field.limits },
        options: field.options.map((option) => ({ ...option })),
    }))
}

export const toEvaluationGeneral = (form: EvaluationFormEditData | null): EvaluationGeneral => ({
    title: form?.title ?? '',
    description: form?.description ?? '',
    isRequired: form?.isRequired ?? true,
    isActive: form?.isActive ?? true,
})

const typeOf = (types: FieldTypeOption[], id: number) =>
    types.find((type) => type.value === String(id))

// W103 — hanya batas yang terisi dan sesuai jenis tipenya; tanpa batas berarti null.
function limitRules(field: EditableEvaluationField, kind: FieldTypeOption['limitKind']) {
    if (kind === null) return null
    const rules: LimitRules = {}
    for (const [key, rule] of LIMIT_KEYS[kind]) {
        const value = field.limits[key]
        if (value !== null) rules[rule] = value
    }
    return Object.keys(rules).length === 0 ? null : rules
}

// Cermin BR-FE-24 — `options` hanya untuk tipe ber-opsi; `value` tersimpan ikut agar jawaban lama terbaca.
export function toEvaluationPayload(
    form: EvaluationGeneral,
    fields: EditableEvaluationField[],
    types: FieldTypeOption[]
): EvaluationPayload {
    return {
        title: form.title,
        description: form.description.trim() === '' ? null : form.description,
        is_required: form.isRequired,
        is_active: form.isActive,
        fields: fields.map((field) => {
            const type = typeOf(types, field.fieldTypeId)
            const payload: EvaluationFieldPayload = {
                ...(field.id === null ? {} : { id: field.id }),
                field_type_id: field.fieldTypeId,
                label: field.label,
                description: field.description || null,
                is_required: field.isRequired,
                validation_rules: limitRules(field, type?.limitKind ?? null),
            }
            if (type?.hasOptions !== true) return payload
            payload.options = field.options.map((option) => ({
                ...(option.id === null ? {} : { id: option.id }),
                label: option.label,
                value: option.value,
            }))
            return payload
        }),
    }
}

// Kartu dipasang ulang saat id field/opsinya berubah (sesudah simpan): baris baru mendapat id-nya.
export const evaluationCardKey = (form: EvaluationFormEditData) =>
    `form-${form.id}:` +
    form.fields.map((field) => `${field.id}(${field.options.map((o) => o.id).join(',')})`).join(';')
