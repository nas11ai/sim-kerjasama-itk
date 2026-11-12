import type {
    EvaluationFieldEditData,
    EvaluationLimits,
    FieldTypeOption,
} from '@/types/form-engine'

// Field draf Form Evaluasi di editor pustaka (BR-FE-31). `key` stabil untuk seret; tanpa id —
// Terbitkan menyalin baris baru (BR-REV-18). Opsi membawa `id: null` agar cocok dengan
// FormFieldOptions bersama, yang membuang baris baru yang kosong.
export type EditableEvaluationOption = { id: number | null; label: string; value: string | null }

export type EditableEvaluationField = Omit<EvaluationFieldEditData, 'options'> & {
    key: string
    options: EditableEvaluationOption[]
}

type LimitRules = {
    min_length?: number
    max_length?: number
    min_value?: number
    max_value?: number
}

export type EvaluationFieldPayload = {
    field_type_id: number
    label: string
    description: string | null
    is_required: boolean
    validation_rules: LimitRules | null
    options?: { label: string; value: string | null }[]
}

export type EvaluationDraftPayload = { fields: EvaluationFieldPayload[] }

const NO_LIMITS: EvaluationLimits = {
    minLength: null,
    maxLength: null,
    minValue: null,
    maxValue: null,
}

// W103 — batas per jenis tipe, dibaca dari `limitType` backend.
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

let keyCounter = 0
const nextKey = () => {
    keyCounter += 1
    return `field-${keyCounter}`
}

export function blankEvaluationField(fieldTypeId: number): EditableEvaluationField {
    return {
        key: nextKey(),
        label: '',
        description: null,
        fieldTypeId,
        isRequired: false,
        limits: { ...NO_LIMITS },
        options: [],
    }
}

export function toEditableEvaluationFields(
    fields: EvaluationFieldEditData[]
): EditableEvaluationField[] {
    return fields.map((field) => ({
        ...field,
        key: nextKey(),
        limits: { ...field.limits },
        options: field.options.map((option) => ({ ...option, id: null })),
    }))
}

// BR-FE-31 / §4.5 — draf yang belum terbit terlihat di daftar dan di editor, sama bunyinya.
export const draftBannerText = (latestVersionLabel: string | null) =>
    latestVersionLabel === null
        ? 'Draf belum diterbitkan. Belum ada versi yang dipakai reviewer.'
        : `Draf belum diterbitkan. Reviewer masih memakai ${latestVersionLabel}.`

// §4.5 — konfirmasi Terbitkan; kalimat kedua hanya bila ada angkatan yang tetap di versi lama.
export const publishImpactText = (impact: { nextLabel: string; pinnedCohorts: number }) =>
    `Terbitkan ${impact.nextLabel}? Berlaku untuk langkah yang belum mulai dinilai.` +
    (impact.pinnedCohorts > 0
        ? ` ${impact.pinnedCohorts} angkatan sudah memakai versi lama dan tetap memakainya.`
        : '')

export const typeOf = (types: FieldTypeOption[], id: number) =>
    types.find((type) => type.value === String(id))

// W103 — hanya batas yang terisi dan sesuai jenis tipenya; tanpa batas berarti null.
function limitRules(field: EditableEvaluationField, limitType: FieldTypeOption['limitType']) {
    if (limitType === null) return null
    const rules: LimitRules = {}
    for (const [key, rule] of LIMIT_KEYS[limitType]) {
        const value = field.limits[key]
        if (value !== null) rules[rule] = value
    }
    return Object.keys(rules).length === 0 ? null : rules
}

// Cermin BR-FE-24 — `options` hanya untuk tipe ber-opsi; `value` tersimpan ikut agar jawaban lama terbaca.
export function toDraftPayload(
    fields: EditableEvaluationField[],
    types: FieldTypeOption[]
): EvaluationDraftPayload {
    return {
        fields: fields.map((field) => {
            const type = typeOf(types, field.fieldTypeId)
            const payload: EvaluationFieldPayload = {
                field_type_id: field.fieldTypeId,
                label: field.label,
                description: field.description || null,
                is_required: field.isRequired,
                validation_rules: limitRules(field, type?.limitType ?? null),
            }
            if (type?.hasOptions !== true) return payload
            payload.options = field.options.map((option) => ({
                label: option.label,
                value: option.value,
            }))
            return payload
        }),
    }
}
