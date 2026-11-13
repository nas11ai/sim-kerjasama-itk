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

import SubmissionTable from '@/Components/Submission/SubmissionTable.vue'

const route = (name: string) => `/${name}`

const mountTable = (props: { filtered: boolean; canCreate?: boolean }) =>
    mount(SubmissionTable, { props: { rows: [], ...props }, global: { mocks: { route } } })

describe('SubmissionTable', () => {
    it('[Paket 2 P1] belum ada pengajuan: keadaan kosong menawarkan pengajuan pertama', () => {
        const wrapper = mountTable({ filtered: false, canCreate: true })

        expect(wrapper.text()).toContain('Belum ada pengajuan')
        expect(wrapper.get('a[href="/user.submissions.create"]').text()).toContain(
            'Buat Pengajuan Baru'
        )
    })

    it('tanpa hak membuat, atau hasil filter kosong, tidak menawarkan pengajuan baru', () => {
        expect(mountTable({ filtered: false }).find('a').exists()).toBe(false)
        expect(mountTable({ filtered: true, canCreate: true }).find('a').exists()).toBe(false)
    })
})
