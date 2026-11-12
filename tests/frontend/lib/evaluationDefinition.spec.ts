import { describe, expect, it } from 'vitest'

import {
    blankEvaluationField,
    toDraftPayload,
    toEditableEvaluationFields,
} from '@/lib/evaluationDefinition'
import { moveItem } from '@/lib/formDefinition'
import type { EvaluationFieldEditData, FieldTypeOption } from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', name: 'text', label: 'Teks', hasOptions: false, limitType: 'length' },
    { value: '3', name: 'number', label: 'Angka', hasOptions: false, limitType: 'value' },
    { value: '5', name: 'select', label: 'Pilihan (dropdown)', hasOptions: true, limitType: null },
]

const noLimits = { minLength: null, maxLength: null, minValue: null, maxValue: null }

const saved: EvaluationFieldEditData[] = [
    {
        label: 'Metodologi',
        description: null,
        fieldTypeId: 3,
        isRequired: true,
        limits: { ...noLimits, minValue: 1, maxValue: 7 },
        options: [],
    },
    {
        label: 'Rekomendasi',
        description: 'Pilih satu',
        fieldTypeId: 5,
        isRequired: false,
        limits: noLimits,
        options: [
            { label: 'Layak', value: 'layak' },
            { label: 'Tidak Layak', value: null },
        ],
    },
]

describe('lib/evaluationDefinition', () => {
    it('batas kosong dikirim sebagai validation_rules null', () => {
        const field = { ...blankEvaluationField(1), label: 'Catatan' }

        const [payload] = toDraftPayload([field], types).fields

        expect(payload.validation_rules).toBeNull()
    })

    it('hanya batas yang terisi dan sesuai jenis tipenya yang dikirim (W103)', () => {
        const field = {
            ...blankEvaluationField(1),
            label: 'Ringkasan',
            limits: { minLength: null, maxLength: 500, minValue: 1, maxValue: null },
        }

        const [payload] = toDraftPayload([field], types).fields

        expect(payload.validation_rules).toEqual({ max_length: 500 })
    })

    it('tipe tanpa opsi tidak membawa kunci options; draf tidak membawa id', () => {
        const [number, select] = toDraftPayload(toEditableEvaluationFields(saved), types).fields

        expect(number).not.toHaveProperty('options')
        expect(number).not.toHaveProperty('id')
        expect(number.validation_rules).toEqual({ min_value: 1, max_value: 7 })
        expect(select.options).toEqual([
            { label: 'Layak', value: 'layak' },
            { label: 'Tidak Layak', value: null },
        ])
    })

    it('urutan seret menjadi urutan kiriman', () => {
        const fields = moveItem(toEditableEvaluationFields(saved), 1, 0)

        expect(toDraftPayload(fields, types).fields.map((field) => field.label)).toEqual([
            'Rekomendasi',
            'Metodologi',
        ])
    })

    it('setiap field mendapat key unik untuk seret', () => {
        const keys = [...toEditableEvaluationFields(saved), blankEvaluationField(1)].map(
            (field) => field.key
        )

        expect(new Set(keys).size).toBe(3)
    })
})
