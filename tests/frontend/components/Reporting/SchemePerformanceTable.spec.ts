import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import SchemePerformanceTable from '@/Components/Reporting/SchemePerformanceTable.vue'
import type { SchemePerformance } from '@/types/reporting'

const row = (overrides: Partial<SchemePerformance> = {}): SchemePerformance => ({
    scheme: 'DIPA',
    submitted: 12,
    approved: 6,
    rejected: 2,
    withdrawn: 1,
    approvalRate: '75%',
    approvedBudget: 'Rp 30.000.000',
    href: '/admin/submissions?scheme=3',
    ...overrides,
})

describe('SchemePerformanceTable', () => {
    it('header kolom kinerja skema', () => {
        const headers = mount(SchemePerformanceTable, { props: { rows: [row()] } })
            .findAll('thead th')
            .map((th) => th.text())

        expect(headers).toEqual([
            'Skema',
            'Diajukan',
            'Disetujui',
            'Ditolak',
            'Ditarik',
            'Tingkat Persetujuan',
            'Anggaran Disetujui',
        ])
    })

    it('nama skema menaut ke href, angka dan teks dari backend', () => {
        const wrapper = mount(SchemePerformanceTable, {
            props: { rows: [row(), row({ scheme: 'Tanpa skema', href: null })] },
        })

        expect(wrapper.findAll('a')).toHaveLength(1)
        expect(wrapper.find('a').attributes('href')).toBe('/admin/submissions?scheme=3')
        expect(wrapper.find('a').text()).toBe('DIPA')
        expect(wrapper.text()).toContain('75%')
        expect(wrapper.text()).toContain('Rp 30.000.000')
        expect(wrapper.text()).toContain('Tanpa skema')
    })

    it('tanpa baris menampilkan Tidak ada data', () => {
        expect(mount(SchemePerformanceTable, { props: { rows: [] } }).text()).toContain(
            'Tidak ada data'
        )
    })
})
