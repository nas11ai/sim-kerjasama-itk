import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import IndexPage from '@/Pages/Submission/IndexPage.vue'
import type { SubmissionIndexPageProps, SubmissionRow } from '@/types/submission-page'

const sampleRow: SubmissionRow = {
    id: 10,
    title: 'Pengembangan Pupuk Hayati untuk Lahan Bekas Tambang',
    scheme: 'Penelitian Terapan ITK',
    period: '2026',
    status: { key: 'approved', label: 'Disetujui', variant: 'default' },
    submittedAt: '2026-03-01T10:00:00Z',
    isArchived: false,
}

const makeProps = (overrides: Partial<SubmissionIndexPageProps> = {}): SubmissionIndexPageProps => ({
    submissions: {
        data: [sampleRow],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
        from: 1,
        to: 1,
        prev_page_url: null,
        next_page_url: null,
    },
    filters: { search: '', status: '', period: '' },
    statusOptions: [],
    periodOptions: [],
    canCreate: true,
    ...overrides,
})

const route = (name: string, params?: Record<string, unknown>) => {
    if (params?.type) {
        return `/${name}?type=${params.type}`
    }
    return `/${name}`
}

beforeEach(() => {
    vi.stubGlobal('route', route)
})

describe('Submission/IndexPage', () => {
    it('[RPT-EXP-01] menyediakan tombol ekspor ke halaman reporting exports', () => {
        const wrapper = mount(IndexPage, {
            props: makeProps(),
            global: { mocks: { route } },
        })

        const exportLink = wrapper.find('a[href="/reporting.exports.index?type=submissions"]')
        expect(exportLink.exists()).toBe(true)
        expect(exportLink.text()).toContain('Ekspor')
    })

    it('tombol ekspor tetap ada meskipun tidak canCreate', () => {
        const wrapper = mount(IndexPage, {
            props: makeProps({ canCreate: false }),
            global: { mocks: { route } },
        })

        const exportLink = wrapper.find('a[href="/reporting.exports.index?type=submissions"]')
        expect(exportLink.exists()).toBe(true)
        expect(wrapper.find('a[href="/user.submissions.create"]').exists()).toBe(false)
    })
})
