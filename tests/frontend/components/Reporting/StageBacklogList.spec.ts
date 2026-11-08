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

import StageBacklogList from '@/Components/Reporting/StageBacklogList.vue'

describe('StageBacklogList', () => {
    it('baris tahap menampilkan jumlah dan umur terlama, menaut ke href', () => {
        const wrapper = mount(StageBacklogList, {
            props: {
                rows: [
                    {
                        key: 'under_review',
                        label: 'Direview',
                        count: 4,
                        oldestAgeDays: 12,
                        href: '/admin/submissions?status=under_review',
                    },
                ],
            },
        })
        const link = wrapper.find('a')

        expect(link.attributes('href')).toBe('/admin/submissions?status=under_review')
        expect(link.text()).toContain('Direview')
        expect(link.text()).toContain('4')
        expect(link.text()).toContain('terlama 12 hari')
    })

    it('tahap kosong tanpa teks umur, tanpa href bukan tautan', () => {
        const wrapper = mount(StageBacklogList, {
            props: {
                rows: [
                    {
                        key: 'resubmitted',
                        label: 'Diajukan Ulang',
                        count: 0,
                        oldestAgeDays: null,
                        href: null,
                    },
                ],
            },
        })

        expect(wrapper.find('a').exists()).toBe(false)
        expect(wrapper.text()).toContain('Diajukan Ulang')
        expect(wrapper.text()).not.toContain('terlama')
    })
})
