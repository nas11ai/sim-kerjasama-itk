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

import RankedBarList from '@/Components/Reporting/RankedBarList.vue'

describe('RankedBarList', () => {
    it('baris terbesar selebar penuh, lainnya sebanding', () => {
        const wrapper = mount(RankedBarList, {
            props: {
                rows: [
                    { label: 'Teknik Mesin', count: 8, href: null },
                    { label: 'Fisika', count: 2, href: null },
                ],
            },
        })
        const bars = wrapper.findAll('[data-testid="ranked-bar"]')

        expect(bars[0].attributes('style')).toContain('width: 100%')
        expect(bars[1].attributes('style')).toContain('width: 25%')
        expect(wrapper.text()).toContain('Teknik Mesin')
        expect(wrapper.text()).toContain('8')
    })

    it('baris tanpa href bukan tautan, baris ber-href menjadi tautan', () => {
        const wrapper = mount(RankedBarList, {
            props: {
                rows: [
                    { label: 'Dr. Sari', count: 3, href: '/reviewers/1' },
                    { label: 'Lainnya', count: 1, href: null },
                ],
            },
        })

        expect(wrapper.findAll('a')).toHaveLength(1)
        expect(wrapper.find('a').attributes('href')).toBe('/reviewers/1')
    })

    it('tanpa baris menampilkan teks kosong', () => {
        expect(mount(RankedBarList, { props: { rows: [] } }).text()).toContain('Tidak ada data')
    })
})
