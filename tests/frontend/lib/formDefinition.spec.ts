import { describe, expect, it } from 'vitest'

import {
    blankField,
    moveField,
    toDefinitionFields,
    toEditableFields,
    type EditableField,
} from '@/lib/formDefinition'
import type { FieldTypeOption, FormFieldEditData } from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', label: 'Teks', hasOptions: false },
    { value: '5', label: 'Pilihan (dropdown)', hasOptions: true },
]

const saved: FormFieldEditData[] = [
    {
        id: 11,
        label: 'Judul',
        fieldTypeId: 1,
        isRequired: true,
        requiredSince: '2025-12-01T00:00:00+08:00',
        answerCount: 2,
        options: [],
    },
    {
        id: 12,
        label: 'Skema',
        fieldTypeId: 5,
        isRequired: false,
        requiredSince: null,
        answerCount: 0,
        options: [
            { id: 21, label: 'Dasar' },
            { id: 22, label: 'Terapan' },
        ],
    },
]

describe('lib/formDefinition', () => {
    it('[FE-CHANGE-14] tipe tanpa opsi tidak mengirim kunci options', () => {
        const [text] = toDefinitionFields(toEditableFields(saved), types)

        expect(text).toEqual({ id: 11, field_type_id: 1, label: 'Judul', is_required: true })
        expect('options' in (text ?? {})).toBe(false)
    })

    it('tipe ber-opsi mengirim opsinya beserta id', () => {
        const [, select] = toDefinitionFields(toEditableFields(saved), types)

        expect(select?.options).toEqual([
            { id: 21, label: 'Dasar' },
            { id: 22, label: 'Terapan' },
        ])
    })

    it('field baru tanpa id; field lama membawa id setelah dipindah dan dibuang', () => {
        const fresh: EditableField = {
            ...blankField(5),
            label: 'Baru',
            options: [{ id: null, label: 'A' }],
        }
        let fields = [...toEditableFields(saved), fresh]
        fields = moveField(fields, 2, 0)
        fields = fields.filter((field) => field.label !== 'Judul')
        const payload = toDefinitionFields(fields, types)

        expect(payload.map((field) => field.id)).toEqual([undefined, 12])
        expect('id' in (payload[0] ?? {})).toBe(false)
        expect(payload[0]?.options).toEqual([{ label: 'A' }])
        expect(payload[1]?.options?.map((option) => option.id)).toEqual([21, 22])
    })

    it('kunci field baru unik dan stabil', () => {
        expect(blankField(1).key).not.toBe(blankField(1).key)
        expect(toEditableFields(saved).map((field) => field.key)).toEqual(['field-11', 'field-12'])
    })

    it('moveField di luar batas tidak mengubah urutan', () => {
        const fields = toEditableFields(saved)

        expect(moveField(fields, 0, -1)).toEqual(fields)
        expect(moveField(fields, 1, 2)).toEqual(fields)
        expect(moveField(fields, 5, 0)).toEqual(fields)
        expect(moveField(fields, 0, 1).map((field) => field.id)).toEqual([12, 11])
        expect(fields.map((field) => field.id)).toEqual([11, 12])
    })
})
