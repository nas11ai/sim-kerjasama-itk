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

import DeadlineList from '@/Components/Dashboard/DeadlineList.vue'
import type { Deadline } from '@/types/dashboard'

const deadline = (overrides: Partial<Deadline>): Deadline => ({
    title: 'Evaluasi: Biochar',
    closesAt: '2026-07-06T23:59:00+08:00',
    daysLeft: 3,
    urgent: true,
    href: null,
    ...overrides,
})

describe('DeadlineList', () => {
    it('baris mendesak diberi penanda mengikuti urgent', () => {
        const wrapper = mount(DeadlineList, {
            props: {
                deadlines: [
                    deadline({}),
                    deadline({ title: 'Tutup periode', daysLeft: 9, urgent: false }),
                ],
            },
        })
        const rows = wrapper.findAll('[data-deadline]')

        expect(rows[0].attributes('data-urgent')).toBe('true')
        expect(rows[1].attributes('data-urgent')).toBe('false')
    })

    it('menulis sisa hari ala wireframe', () => {
        const text = mount(DeadlineList, {
            props: {
                deadlines: [
                    deadline({ daysLeft: 3 }),
                    deadline({ title: 'B', daysLeft: 0 }),
                    deadline({ title: 'C', daysLeft: -1 }),
                ],
            },
        }).text()

        expect(text).toContain('3 hari lagi')
        expect(text).toContain('Hari ini')
        expect(text).toContain('Terlambat')
    })

    it('baris ber-href dirender sebagai tautan', () => {
        const wrapper = mount(DeadlineList, {
            props: { deadlines: [deadline({ href: '/reviewer/assignments/1/evaluation' })] },
        })

        expect(wrapper.find('a[href="/reviewer/assignments/1/evaluation"]').exists()).toBe(true)
    })

    it('EmptyState saat kosong', () => {
        expect(mount(DeadlineList, { props: { deadlines: [] } }).text()).toContain(
            'Tidak ada tenggat mendekat'
        )
    })
})
