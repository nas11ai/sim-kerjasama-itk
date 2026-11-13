import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import PhaseStepPreview from '@/Components/FormEngine/PhaseStepPreview.vue'
import type { PreviewStep } from '@/lib/phaseDefinition'

const step = (overrides: Partial<PreviewStep> = {}): PreviewStep => ({
    key: 'step-0',
    title: 'Isi Proposal: Form Proposal',
    access: 'Pengaju · Fakultas Sains',
    deadlineLabel: 'Pengajuan',
    needsReview: true,
    evaluationForms: [
        {
            formId: 3,
            title: 'Lembar Substansi',
            versionLabel: 'Versi 2',
            isRequired: true,
            isActive: true,
        },
        {
            formId: 4,
            title: 'Lembar Lama',
            versionLabel: 'Versi 1',
            isRequired: false,
            isActive: false,
        },
    ],
    ...overrides,
})

describe('FormEngine/PhaseStepPreview', () => {
    // G3-W8 — alur langkah baca-saja: urutan, Form, nama tenggat, review, Formulir Evaluasi aktif.
    it('merender langkah berurutan beserta tenggat dan Formulir Evaluasi aktifnya', () => {
        const wrapper = mount(PhaseStepPreview, {
            props: {
                steps: [
                    step(),
                    step({
                        key: 'step-1',
                        title: 'Laporan: Form Monev',
                        deadlineLabel: 'Laporan Monev I',
                        needsReview: false,
                    }),
                ],
            },
        })
        const items = wrapper.findAll('ol > li')

        expect(items).toHaveLength(2)
        expect(items[0].text()).toContain('Langkah 1')
        expect(items[0].text()).toContain('Perlu Review')
        expect(items[0].text()).toContain('Lembar Substansi · Versi 2 · wajib')
        expect(items[0].text()).not.toContain('Lembar Lama')
        expect(items[1].text()).toContain('Langkah 2')
        expect(items[1].text()).toContain('Laporan Monev I')
        expect(items[1].text()).toContain('Tanpa Review')
        expect(wrapper.find('button').exists()).toBe(false)
    })

    it('tanpa langkah memberi tahu belum ada yang dipratinjau', () => {
        const wrapper = mount(PhaseStepPreview, { props: { steps: [] } })

        expect(wrapper.text()).toContain('Belum ada langkah untuk dipratinjau.')
    })
})
