import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

export type FieldResponse = { form_field_id: number; value: WizardFieldValue }

// Nilai awal isian: jawaban tersimpan, kecuali berkas yang hanya dikirim bila diunggah ulang.
export const initialFieldValues = (fields: WizardField[]): Record<number, WizardFieldValue> =>
    Object.fromEntries(fields.map((f) => [f.id, f.type === 'file' ? null : f.value]))

// Field non-berkas selalu dikirim (kosong = dikosongkan); berkas hanya bila ada yang baru.
export const fieldResponses = (
    fields: WizardField[],
    values: Record<number, WizardFieldValue>
): FieldResponse[] =>
    fields.flatMap((f): FieldResponse[] => {
        const value = values[f.id] ?? null
        if (f.type === 'file') return value instanceof File ? [{ form_field_id: f.id, value }] : []
        return [{ form_field_id: f.id, value: value === '' ? null : value }]
    })
