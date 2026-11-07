import { describe, expect, it } from 'vitest'

import { moveItem } from '@/lib/formDefinition'
import {
    blankEvaluationField,
    evaluationCardKey,
    toEditableEvaluationFields,
    toEvaluationPayload,
} from '@/lib/evaluationDefinition'
import type {
    EvaluationFieldEditData,
    EvaluationFormEditData,
    FieldTypeOption,
} from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', label: 'Teks', hasOptions: false, limitType: 'length' },
    { value: '3', label: 'Angka', hasOptions: false, limitType: 'value' },
    { value: '5', label: 'Pilihan (dropdown)', hasOptions: true, limitType: null },
]

const noLimits = { minLength: null, maxLength: null, minValue: null, maxValue: null }

const saved: EvaluationFieldEditData[] = [
    {
        id: 11,
        label: 'Metodologi',
        description: null,
        fieldTypeId: 3,
        isRequired: true,
        limits: { ...noLimits, minValue: 1, maxValue: 7 },
        answerCount: 2,
        options: [],
    },
    {
        id: 12,
        label: 'Rekomendasi',
        description: 'Pilih satu',
        fieldTypeId: 5,
        isRequired: false,
        limits: noLimits,
        answerCount: 0,
        options: [
            { id: 21, label: 'Layak', value: 'layak' },
            { id: 22, label: 'Tidak Layak', value: null },
        ],
    },
]

const general = { title: 'Lembar Substansi', description: '', isRequired: true, isActive: true }

describe('lib/evaluationDefinition', () => {
    it('batas kosong dikirim sebagai validation_rules null', () => {
        const field = { ...blankEvaluationField(1), label: 'Catatan' }

        const [payload] = toEvaluationPayload(general, [field], types).fields

        expect(payload.validation_rules).toBeNull()
    })

    it('hanya batas yang terisi dan sesuai jenis tipenya yang dikirim (W103)', () => {
        const field = {
            ...blankEvaluationField(1),
            label: 'Ringkasan',
            limits: { minLength: null, maxLength: 500, minValue: 1, maxValue: null },
        }

        const [payload] = toEvaluationPayload(general, [field], types).fields

        expect(payload.validation_rules).toEqual({ max_length: 500 })
    })

    it('tipe tanpa opsi tidak membawa kunci options', () => {
        const [number, select] = toEvaluationPayload(
            general,
            toEditableEvaluationFields(saved),
            types
        ).fields

        expect(number).not.toHaveProperty('options')
        expect(number.validation_rules).toEqual({ min_value: 1, max_value: 7 })
        expect(select.options).toEqual([
            { id: 21, label: 'Layak', value: 'layak' },
            { id: 22, label: 'Tidak Layak', value: null },
        ])
    })

    it('id field dan opsi bertahan setelah diurutkan ulang', () => {
        const fields = moveItem(toEditableEvaluationFields(saved), 1, 0)

        const payload = toEvaluationPayload(general, fields, types)

        expect(payload.fields.map((field) => field.id)).toEqual([12, 11])
        expect(payload.fields[0].options?.map((option) => option.id)).toEqual([21, 22])
        expect(payload).toMatchObject({
            title: 'Lembar Substansi',
            description: null,
            is_required: true,
            is_active: true,
        })
    })

    it('field baru tanpa kunci id', () => {
        const [payload] = toEvaluationPayload(
            general,
            [{ ...blankEvaluationField(5), label: 'Skor' }],
            types
        ).fields

        expect(payload).not.toHaveProperty('id')
        expect(payload.options).toEqual([])
    })
})

describe('evaluationCardKey', () => {
    const form = (fields: EvaluationFieldEditData[], title = 'Lembar'): EvaluationFormEditData => ({
        id: 9,
        title,
        description: null,
        isRequired: true,
        isActive: true,
        assignmentCount: 0,
        fields,
    })
    const textField = (id: number | null, label = 'Metodologi'): EvaluationFieldEditData => ({
        id,
        label,
        description: null,
        fieldTypeId: 1,
        isRequired: true,
        limits: noLimits,
        answerCount: 0,
        options: [],
    })

    it('berubah saat field atau opsi baru mendapat id', () => {
        const before = evaluationCardKey(form([textField(11)]))

        expect(evaluationCardKey(form([textField(11), textField(12)]))).not.toBe(before)
        const withOption = textField(11)
        withOption.options = [{ id: 5, label: 'A', value: 'a' }]
        expect(evaluationCardKey(form([withOption]))).not.toBe(before)
    })

    it('stabil bila hanya label berubah', () => {
        expect(evaluationCardKey(form([textField(11, 'Lain')], 'Judul Baru'))).toBe(
            evaluationCardKey(form([textField(11)]))
        )
    })
})
