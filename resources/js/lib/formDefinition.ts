import type { FieldTypeOption, FormFieldEditData, FormPublishImpact } from '@/types/form-engine'

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
        key: field.id === null ? blankField(field.fieldTypeId).key : `field-${field.id}`,
        options: field.options.map((option) => ({ ...option })),
    }))
}

// BR-FE-34 — ringkasan konfirmasi Terbitkan Form: satu kalimat per butir yang ada, berurutan.
export function formPublishImpactText(impact: FormPublishImpact): string {
    const sentences = [
        impact.newRequired > 0
            ? `${impact.newRequired} isian baru wajib, berlaku untuk pengajuan yang dibuat sesudah ini.`
            : null,
        impact.draftOwners > 0
            ? `${impact.draftOwners} peneliti dengan draf berjalan tidak terpengaruh.`
            : null,
        ...impact.relabelled.map(
            (row) =>
                `Label "${row.label}" diubah pada isian yang sudah dijawab ${row.answered} pengajuan.`
        ),
        impact.removed > 0
            ? `${impact.removed} isian dihapus, jawaban lamanya tetap tersimpan.`
            : null,
    ].filter((sentence): sentence is string => sentence !== null)
    return sentences.length === 0 ? 'Terbitkan perubahan Formulir ini?' : sentences.join(' ')
}

// BR-FE-34 — banner editor Form selama ada draf, atau selama Form belum pernah terbit.
export const formDraftBannerText = (publishedAt: string | null) =>
    publishedAt === null
        ? 'Draf belum diterbitkan. Formulir ini belum bisa dipakai tahap.'
        : 'Draf belum diterbitkan. Peneliti masih melihat isian terbitan terakhir.'

// G3-W9 (spec §4.6) — label field atau opsi terjawab yang berbeda dari terbitan: peringatan, bukan blokir.
export const RELABEL_WARNING =
    'Hanya untuk memperbaiki ketikan. Kalau pertanyaannya berbeda, tambahkan isian baru, jawaban lama tetap tercatat di isian lama.'

export function relabelsAnsweredField(
    field: EditableField,
    published: FormFieldEditData | undefined
): boolean {
    if (published === undefined || published.answerCount === 0) return false
    if (field.label !== published.label) return true
    return field.options.some((option) => {
        const saved = published.options.find((o) => o.id !== null && o.id === option.id)
        return saved !== undefined && saved.label !== option.label
    })
}

// BR-FE-24 — opsi tersimpan tidak boleh diarsipkan diam-diam; buang lewat tombol Buang.
export function optionError(options: EditableOption[], hasOptions: boolean): string | undefined {
    if (!hasOptions) return undefined
    if (options.some((o) => o.id !== null && o.label.trim() === ''))
        return 'Label opsi tidak boleh kosong'
    if (options.every((o) => o.label.trim() === '')) return 'Tambahkan minimal satu opsi'
    return undefined
}

// Baris opsi baru yang dibiarkan kosong dibuang saat field disimpan.
export const keptOptions = <T extends EditableOption>(options: T[]): T[] =>
    options.filter((o) => o.id !== null || o.label.trim() !== '')

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
