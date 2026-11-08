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

import StatusBreakdownCard from '@/Components/Reporting/StatusBreakdownCard.vue'
import type { StatusShare } from '@/types/reporting'

const status = (
    key: string,
    label: string,
    count: number,
    share: number,
    href: string | null
): StatusShare => ({
    key,
    label,
    variant: 'default',
    count,
    share,
    href,
})

describe('StatusBreakdownCard', () => {
    it('setiap status menaut ke daftar berfilter dengan angka dan persennya', () => {
        const wrapper = mount(StatusBreakdownCard, {
            props: {
                rows: [
                    status('approved', 'Disetujui', 7, 0.384, '/admin/submissions?status=approved'),
                    status('rejected', 'Ditolak', 3, 0.166, '/admin/submissions?status=rejected'),
                    status(
                        'withdrawn',
                        'Ditarik Kembali',
                        8,
                        0.45,
                        '/admin/submissions?status=withdrawn'
                    ),
                ],
            },
        })

        expect(wrapper.findAll('a[href^="/admin/submissions?status="]')).toHaveLength(3)
        const approved = wrapper.find('a[href="/admin/submissions?status=approved"]')
        expect(approved.text()).toContain('Disetujui')
        expect(approved.text()).toContain('7')
        expect(approved.text()).toContain('38%')
        expect(wrapper.find('[data-testid="status-bar"]').exists()).toBe(true)
    })

    it('tanpa izin daftar, baris tidak menjadi tautan', () => {
        const wrapper = mount(StatusBreakdownCard, {
            props: { rows: [status('approved', 'Disetujui', 2, 1, null)] },
        })

        expect(wrapper.find('a').exists()).toBe(false)
        expect(wrapper.text()).toContain('Disetujui')
    })

    it('widget kosong menampilkan Tidak ada data', () => {
        const wrapper = mount(StatusBreakdownCard, {
            props: {
                rows: [
                    status('approved', 'Disetujui', 0, 0, '/x'),
                    status('rejected', 'Ditolak', 0, 0, '/y'),
                ],
            },
        })

        expect(wrapper.text()).toContain('Tidak ada data')
        expect(wrapper.find('[data-testid="status-bar"]').exists()).toBe(false)
    })
})
