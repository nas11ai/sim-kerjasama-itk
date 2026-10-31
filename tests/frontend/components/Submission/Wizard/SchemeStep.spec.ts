import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn(), delete: vi.fn() } }))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import SchemeStep from '@/Components/Submission/Wizard/SchemeStep.vue'
import type { WizardField } from '@/types/submission-wizard'

const field = (id: number, type: WizardField['type'], value: string | null): WizardField => ({
    id,
    label: type,
    type,
    required: true,
    value,
    options: [
        { value: '5', label: 'Lima' },
        { value: '6', label: 'Enam' },
    ],
    fileName: null,
    downloadUrl: null,
})

const mountScheme = (fields: WizardField[]) =>
    mount(SchemeStep, {
        props: {
            fields,
            values: Object.fromEntries(fields.map((f) => [f.id, f.value])),
            errors: {},
            submissionId: 7,
            targetOutputs: [],
            targetOutputTypes: [],
        },
    })

describe('SchemeStep', () => {
    it('mengosongkan TRL saat skema berganti', async () => {
        const wrapper = mountScheme([
            field(1, 'scheme_selector', '5'),
            field(2, 'trl_selector', '9'),
        ])

        wrapper.findAllComponents(OptionSelect)[0].vm.$emit('update:modelValue', '6')
        await wrapper.vm.$nextTick()

        expect(wrapper.emitted('update')).toEqual([
            [1, '6'],
            [2, null],
        ])
    })

    it('[SM-WIZARD-09] form tanpa skema memberi tahu batas anggaran tidak berlaku', () => {
        expect(mountScheme([]).text()).toContain(
            'Form ini tidak memakai skema — batas anggaran tidak berlaku.'
        )
    })
})
