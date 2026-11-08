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

import FollowUpListCard from '@/Components/Reporting/FollowUpListCard.vue'
import type { FollowUpList } from '@/types/reporting'

const list = (overrides: Partial<FollowUpList> = {}): FollowUpList => ({
    key: 'awaitingReviewer',
    title: 'Menunggu Penugasan Reviewer',
    hint: 'Tugaskan reviewer dari detail pengajuan.',
    count: 7,
    items: [
        {
            id: 12,
            title: 'Sensor Banjir Balikpapan',
            leader: 'Dr. Budi',
            scheme: 'DIPA',
            ageDays: 12,
            href: '/admin/submissions/12',
        },
    ],
    href: '/admin/submissions?status=submitted',
    ...overrides,
})

describe('FollowUpListCard', () => {
    it('item menampilkan judul, ketua, umur, dan menaut ke href', () => {
        const wrapper = mount(FollowUpListCard, { props: { list: list() } })
        const item = wrapper.find('a[href="/admin/submissions/12"]')

        expect(wrapper.text()).toContain('Menunggu Penugasan Reviewer')
        expect(wrapper.text()).toContain('Tugaskan reviewer dari detail pengajuan.')
        expect(wrapper.text()).toContain('7')
        expect(item.text()).toContain('Sensor Banjir Balikpapan')
        expect(item.text()).toContain('Dr. Budi')
        expect(item.text()).toContain('12 hari sejak submit')
        expect(wrapper.find('a[href="/admin/submissions?status=submitted"]').exists()).toBe(true)
    })

    it('count 0 menampilkan Tidak ada yang menunggu.', () => {
        const wrapper = mount(FollowUpListCard, {
            props: { list: list({ count: 0, items: [], href: null }) },
        })

        expect(wrapper.text()).toContain('Tidak ada yang menunggu.')
        expect(wrapper.find('a').exists()).toBe(false)
    })

    it('tanpa item (bukan view-all) hanya angka, tanpa teks kosong', () => {
        const wrapper = mount(FollowUpListCard, {
            props: { list: list({ items: [], href: null }) },
        })

        expect(wrapper.text()).toContain('7')
        expect(wrapper.text()).not.toContain('Tidak ada yang menunggu.')
        expect(wrapper.find('a').exists()).toBe(false)
    })
})
