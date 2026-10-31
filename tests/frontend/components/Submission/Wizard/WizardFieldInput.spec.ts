import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import WizardFieldInput from '@/Components/Submission/Wizard/WizardFieldInput.vue'
import type { WizardField, WizardFieldType } from '@/types/submission-wizard'

const field = (type: WizardFieldType, overrides: Partial<WizardField> = {}): WizardField => ({
    id: 4,
    label: 'Isian',
    type,
    required: false,
    value: null,
    options: [{ value: '1', label: 'Satu' }],
    fileName: null,
    downloadUrl: null,
    ...overrides,
})

const mountInput = (f: WizardField, error?: string) =>
    mount(WizardFieldInput, { props: { field: f, modelValue: f.value, error } })

describe('WizardFieldInput', () => {
    it.each([
        ['text', 'input[type="text"]'],
        ['email', 'input[type="email"]'],
        ['url', 'input[type="url"]'],
        ['phone', 'input[type="tel"]'],
        ['number', 'input[type="number"]'],
        ['date', 'input[type="date"]'],
        ['time', 'input[type="time"]'],
        ['textarea', 'textarea'],
        ['file', 'input[type="file"]'],
        ['radio', '[role="radiogroup"]'],
        ['checkbox', '[role="checkbox"]'],
    ] as const)('tipe %s merender %s', (type, selector) => {
        expect(mountInput(field(type)).find(selector).exists()).toBe(true)
    })

    it.each(['select', 'scheme_selector', 'trl_selector'] as const)(
        'tipe %s memakai Select',
        (type) => {
            expect(mountInput(field(type)).findComponent(OptionSelect).exists()).toBe(true)
        }
    )

    it('menampilkan error field_{id}', () => {
        const wrapper = mountInput(field('text'), "Field 'Isian' wajib diisi sebelum submit.")

        expect(wrapper.text()).toContain("Field 'Isian' wajib diisi sebelum submit.")
    })

    it('trl tanpa opsi nonaktif', () => {
        const select = mountInput(field('trl_selector', { options: [] })).findComponent(
            OptionSelect
        )

        expect(select.props('disabled')).toBe(true)
        expect(select.props('placeholder')).toBe('Pilih skema terlebih dahulu')
    })

    it('berkas tersimpan ditautkan ke unduhannya', () => {
        const wrapper = mountInput(
            field('file', { fileName: 'proposal.pdf', downloadUrl: '/user/submissions/7/files/3' })
        )

        expect(wrapper.find('a[href="/user/submissions/7/files/3"]').text()).toContain(
            'proposal.pdf'
        )
    })

    it('menandai field wajib', () => {
        expect(mountInput(field('text', { required: true })).text()).toContain('*')
    })
})
