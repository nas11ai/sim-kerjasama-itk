import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import PeriodDateRows, { type PeriodDateRow } from '@/Components/FormEngine/PeriodDateRows.vue'

const typeOptions = [
    { value: 'submit', label: 'Batas Pengiriman' },
    { value: 'output', label: 'Batas Akhir' },
]

const mountRows = (rows: PeriodDateRow[]) =>
    mount(PeriodDateRows, {
        props: {
            typeOptions,
            modelValue: rows,
            'onUpdate:modelValue': () => undefined,
        },
    })

describe('PeriodDateRows', () => {
    // W139 — Batas Akhir (Upload Luaran) satu per periode, tidak per siklus monev.
    it('Batas Akhir tidak menawarkan siklus', () => {
        const wrapper = mountRows([{ type: 'output', cycle: null, date: '', locked: 0 }])

        expect(wrapper.find('#period-date-cycle-0').exists()).toBe(false)
    })

    it('memilih Batas Akhir mengosongkan siklus', async () => {
        const row: PeriodDateRow = { type: 'submit', cycle: 2, date: '', locked: 0 }
        const wrapper = mountRows([row])

        wrapper.findAllComponents(OptionSelect)[0].vm.$emit('update:modelValue', 'output')
        await wrapper.vm.$nextTick()

        expect(row).toMatchObject({ type: 'output', cycle: null })
        expect(wrapper.find('#period-date-cycle-0').exists()).toBe(false)
    })

    // BR-FE-07 — langkah bisa sampai Laporan Monev X, jadi kalender harus bisa memberi tanggalnya.
    it('menawarkan siklus sampai Monev X, sama dengan pilihan jenis langkah', () => {
        const wrapper = mountRows([{ type: 'submit', cycle: null, date: '', locked: 0 }])
        const labels = wrapper
            .findAllComponents(OptionSelect)[1]
            .props('options')
            .map((o) => o.label)

        expect(labels).toEqual([
            'Pengajuan utama',
            ...['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'].map(
                (n) => `Monev ${n}`
            ),
        ])
    })
})
