import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import PhaseDetailDialog from '@/Components/FormEngine/PhaseDetailDialog.vue'
import { blankDetail, type EditableDetail } from '@/lib/phaseDefinition'

const detail = (overrides: Partial<EditableDetail> = {}): EditableDetail => ({
    ...blankDetail(1, 1),
    key: 'detail-7',
    id: 7,
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
            evaluationFormCount: 0,
            accessOptions: [],
            phaseTypeOptions: [],
        },
        attachTo: document.body,
    })
    await flushPromises()
}

afterEach(() => wrapper.unmount())

describe('FormEngine/PhaseDetailDialog', () => {
    // BR-FE-20, K-1 — jenis langkah menentukan tanggal yang dibaca pengajuannya.
    it('jenis langkah terkunci bila detail sudah dipakai pengajuan', async () => {
        await open(detail({ submissionCount: 3 }))

        expect(kindSelect()?.props('disabled')).toBe(true)
        expect(document.body.textContent).toContain(
            'Detail ini sudah dipakai pengajuan; form, tipe tahap, dan jenis langkahnya tidak dapat diubah.'
        )
    })

    it('jenis langkah bisa diganti selama belum ada pengajuan', async () => {
        await open(detail())

        expect(kindSelect()?.props('disabled')).toBeFalsy()
    })
})
