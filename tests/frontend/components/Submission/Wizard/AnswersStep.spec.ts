import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AnswersStep from '@/Components/Submission/Wizard/AnswersStep.vue'
import type { WizardField, WizardFieldType } from '@/types/submission-wizard'

const field = (
    id: number,
    type: WizardFieldType,
    label: string,
    required: boolean
): WizardField => ({
    id,
    label,
    type,
    required,
    value: null,
    options: [],
    fileName: null,
    downloadUrl: null,
})

const mountStep = (fields: WizardField[], step = 6) =>
    mount(AnswersStep, {
        props: { step, label: 'Upload Berkas', fields, values: {}, errors: {} },
    })

// E2E-12, BR-SM-02 — banner berkas mengikuti field berkas wajib milik Form.
describe('AnswersStep', () => {
    it('menyebut field berkas wajib di langkah 6', () => {
        const wrapper = mountStep([
            field(1, 'file', 'Proposal PDF', true),
            field(2, 'file', 'RAB', true),
            field(3, 'file', 'Lampiran', false),
        ])

        expect(wrapper.text()).toContain('Wajib diunggah sebelum submit: Proposal PDF, RAB.')
        expect(wrapper.text()).not.toContain('Lampiran.')
    })

    it('tanpa field berkas wajib, tidak ada banner', () => {
        const wrapper = mountStep([field(3, 'file', 'Lampiran', false)])

        expect(wrapper.text()).not.toContain('Wajib diunggah')
        expect(wrapper.text()).not.toContain('Proposal PDF')
    })

    it('banner hanya di langkah 6', () => {
        expect(mountStep([field(1, 'file', 'Proposal PDF', true)], 2).text()).not.toContain(
            'Wajib diunggah'
        )
    })
})
