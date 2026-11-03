import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import PhaseDeadlineSelect from '@/Components/FormEngine/PhaseDeadlineSelect.vue'
import type { SubmissionDateOption } from '@/types/form-engine'

const options: SubmissionDateOption[] = [
    {
        id: 1,
        type: 'submit',
        cycle: null,
        periodId: 1,
        label: 'Periode A — Batas Submit — 01/03/2026',
    },
    {
        id: 2,
        type: 'evaluation',
        cycle: null,
        periodId: 1,
        label: 'Periode A — Batas Evaluasi — 20/03/2026',
    },
    {
        id: 3,
        type: 'evaluation',
        cycle: 1,
        periodId: 1,
        label: 'Periode A — Batas Evaluasi Monev I — 20/06/2026',
    },
    {
        id: 4,
        type: 'revision',
        cycle: null,
        periodId: 1,
        label: 'Periode A — Batas Revisi — 15/04/2026',
    },
]

const stubs = {
    Select: { template: '<div><slot /></div>' },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { props: ['placeholder'], template: '<span>{{ placeholder }}</span>' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: { props: ['value'], template: '<div class="item" :data-id="value"><slot /></div>' },
}

const mountSelect = (type: SubmissionDateOption['type'], error?: string) =>
    mount(PhaseDeadlineSelect, {
        props: { options, type, label: 'Batas Evaluasi', modelValue: null, error },
        global: { stubs },
    })

describe('PhaseDeadlineSelect', () => {
    it('[FE-WINDOW-09] menawarkan hanya tenggat berjenis kolomnya (BR-FE-26)', () => {
        const ids = mountSelect('evaluation')
            .findAll('.item')
            .map((item) => item.attributes('data-id'))

        expect(ids).toEqual(['2', '3'])
    })

    it('menampilkan galat validasi kolomnya', () => {
        const wrapper = mountSelect(
            'evaluation',
            'Detail yang direview wajib punya Batas Evaluasi.'
        )

        expect(wrapper.text()).toContain('Detail yang direview wajib punya Batas Evaluasi.')
    })
})
