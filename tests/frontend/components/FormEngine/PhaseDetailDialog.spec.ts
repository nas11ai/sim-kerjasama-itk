import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import PhaseDetailDialog from '@/Components/FormEngine/PhaseDetailDialog.vue'
import { blankDetail, type EditableDetail } from '@/lib/phaseDefinition'

const detail = (overrides: Partial<EditableDetail> = {}): EditableDetail => ({
    ...blankDetail(1, 1),
    key: 'step-0',
    ...overrides,
})

const kindSelect = () =>
    wrapper.findAllComponents(OptionSelect).find((s) => s.props('id') === 'detail-kind')

let wrapper: VueWrapper

const open = async (value: EditableDetail) => {
    wrapper = mount(PhaseDetailDialog, {
        props: {
            open: true,
            detail: value,
            adding: false,
            accessOptions: [],
            phaseTypeOptions: [],
        },
        attachTo: document.body,
    })
    await flushPromises()
}

afterEach(() => wrapper.unmount())

describe('FormEngine/PhaseDetailDialog', () => {
    // G3-W5 — versi terbit beku: mengganti jenis langkah hanya berlaku sejak versi berikutnya.
    it('jenis langkah selalu bisa diganti di draf', async () => {
        await open(detail())

        expect(kindSelect()?.props('disabled')).toBeFalsy()
        expect(document.body.textContent).toContain('Ubah Langkah')
    })

    // W97 — review terkunci oleh form evaluasi aktif yang terpasang di langkah draf itu sendiri.
    it('Perlu Review terkunci selama langkah memasang form evaluasi aktif', async () => {
        await open(
            detail({
                needsReview: true,
                evaluationForms: [
                    {
                        formId: 3,
                        title: 'Lembar',
                        versionLabel: 'Versi 1',
                        isRequired: true,
                        isActive: true,
                    },
                    {
                        formId: 4,
                        title: 'Lain',
                        versionLabel: 'Versi 1',
                        isRequired: true,
                        isActive: false,
                    },
                ],
            })
        )

        expect(document.body.querySelector('[role="switch"]')?.hasAttribute('disabled')).toBe(true)
        expect(document.body.textContent).toContain('Detail ini punya 1 formulir evaluasi aktif.')
    })
})
