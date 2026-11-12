import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import FormPreview from '@/Components/FormEngine/FormPreview.vue'
import WizardFieldList from '@/Components/Submission/Wizard/WizardFieldList.vue'
import type { EditableField } from '@/lib/formDefinition'
import type { FieldTypeOption } from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', name: 'text', label: 'Teks', hasOptions: false, limitType: 'length' },
    { value: '5', name: 'select', label: 'Pilihan', hasOptions: true, limitType: null },
]

const field = (overrides: Partial<EditableField>): EditableField => ({
    key: 'field-11',
    id: 11,
    label: 'Judul',
    fieldTypeId: 1,
    isRequired: true,
    requiredSince: null,
    answerCount: 0,
    options: [],
    ...overrides,
})

describe('FormEngine/FormPreview', () => {
    it('merender field draf lewat WizardFieldList, termasuk field baru tanpa id (G3-W8)', () => {
        const wrapper = mount(FormPreview, {
            props: {
                fieldTypeOptions: types,
                fields: [
                    field({}),
                    field({
                        key: 'new-1',
                        id: null,
                        label: 'Skema',
                        fieldTypeId: 5,
                        isRequired: false,
                        options: [
                            { id: 21, label: 'Hibah Internal' },
                            { id: null, label: '' },
                        ],
                    }),
                ],
            },
        })

        const fields = wrapper.findComponent(WizardFieldList).props('fields')
        expect(fields).toEqual([
            expect.objectContaining({ id: 11, label: 'Judul', type: 'text', required: true }),
            expect.objectContaining({
                id: -2,
                label: 'Skema',
                type: 'select',
                required: false,
                options: [{ value: '21', label: 'Hibah Internal' }],
            }),
        ])
        expect(wrapper.text()).toContain('Judul')
        expect(wrapper.text()).toContain('Skema')
    })

    it('tanpa field menampilkan keadaan kosong', () => {
        const wrapper = mount(FormPreview, { props: { fieldTypeOptions: types, fields: [] } })

        expect(wrapper.text()).toContain('Form ini tidak punya isian di langkah ini.')
    })
})
