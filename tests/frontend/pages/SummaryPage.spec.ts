import { mount } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post: vi.fn(), reset: vi.fn() }),
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
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

import NewThreadForm from '@/Components/Review/NewThreadForm.vue'
import ReviewThreadCard from '@/Components/Submission/Revision/ReviewThreadCard.vue'
import SummaryPage from '@/Pages/Review/SummaryPage.vue'
import type { SummaryPageProps } from '@/types/review'

const baseProps: SummaryPageProps = {
    submission: {
        id: 9,
        title: 'Analisis Potensi Biochar',
        scheme: null,
        period: null,
        leadName: 'Dr. Budi Santoso',
        memberCount: 1,
        status: { key: 'under_review', label: 'Direview', variant: 'secondary' },
    },
    threads: [
        {
            id: 11,
            reviewer: 'Sari',
            inheritedBy: null,
            isOpen: true,
            notes: 'Perkuat metodologi.',
            at: null,
            attachments: [],
            comments: [],
        },
    ],
    canCreateThread: true,
    scores: { visible: false, reason: 'Skor reviewer lain terbuka nanti.', scores: [] },
    outcome: { reviewerCount: 2, unsettledCount: 0, openThreadCount: 1 },
}

const route = (name: string, params?: unknown) => `/${name}/${String(params)}`

const mountPage = (props: Partial<SummaryPageProps> = {}) =>
    mount(SummaryPage, {
        props: { ...baseProps, ...props },
        global: { stubs: { teleport: true }, mocks: { route } },
    })

beforeEach(() => {
    vi.stubGlobal('route', route)
})

describe('SummaryPage', () => {
    it('ulir reviewer bisa dibalas dan ditandai selesai', () => {
        const card = mountPage().findComponent(ReviewThreadCard)

        expect(card.props('canReply')).toBe(true)
        expect(card.props('canResolve')).toBe(true)
    })

    it('[REV-EVAL-04] hak membuat ringkasan dibaca dari payload', () => {
        const wrapper = mountPage({ canCreateThread: false })

        expect(wrapper.findComponent(NewThreadForm).props('canCreate')).toBe(false)
    })

    it('tanpa ulir menampilkan keadaan kosong dan alasan skor tertutup', () => {
        const wrapper = mountPage({ threads: [] })

        expect(wrapper.text()).toContain('Belum ada catatan ringkasan.')
        expect(wrapper.text()).toContain('Skor reviewer lain terbuka nanti.')
    })
})
