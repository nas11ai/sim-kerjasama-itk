import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, routerPost } = vi.hoisted(() => ({ post: vi.fn(), routerPost: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPost },
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post, reset: vi.fn() }),
}))

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import ReviewThreadCard from '@/Components/Submission/Revision/ReviewThreadCard.vue'
import type { ReviewThread } from '@/types/submission-revision'

const thread: ReviewThread = {
    id: 11,
    reviewer: 'Penilai Ulir',
    inheritedBy: null,
    isOpen: true,
    notes: '<b>Perbaiki</b> metodologi.',
    at: '2026-10-01T08:00:00+08:00',
    attachments: [{ name: 'catatan.pdf', url: '/unduh/catatan.pdf' }],
    comments: [
        {
            id: 5,
            author: 'Dr. Rina',
            body: 'Sudah diperbaiki.',
            at: null,
            replies: [
                {
                    id: 6,
                    author: 'Penilai Ulir',
                    body: 'Terima kasih.',
                    at: null,
                    replies: [],
                    attachments: [],
                },
            ],
            attachments: [],
        },
    ],
}

const mountCard = (canReply = true, canResolve = false, overrides: Partial<ReviewThread> = {}) =>
    mount(ReviewThreadCard, {
        props: { thread: { ...thread, ...overrides }, canReply, canResolve },
        global: { stubs: { teleport: true } },
    })

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: unknown) => `/${name}/${String(params)}`)
    post.mockReset()
    routerPost.mockReset()
})

describe('ReviewThreadCard', () => {
    it('[REV-DISCUSS-06] judul ulir warisan memuat penulis asli dan pewarisnya', () => {
        const wrapper = mountCard(false, true, { reviewer: 'Sari', inheritedBy: 'Andi' })

        expect(wrapper.text()).toContain('Sari · diwarisi Andi')
    })

    it('judul ulir yang tidak diwarisi hanya nama penulis', () => {
        const wrapper = mountCard(false, false, { reviewer: 'Sari', inheritedBy: null })

        expect(wrapper.text()).toContain('Sari')
        expect(wrapper.text()).not.toContain('diwarisi')
    })

    it('[REV-DISCUSS-02] ketua membalas lewat review-comments.store', async () => {
        const wrapper = mountCard()

        await wrapper.find('textarea').setValue('Bab 2 sudah disitasi ulang.')
        await wrapper.find('form').trigger('submit')

        expect(post).toHaveBeenCalledWith(
            '/review-comments.store/11',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[REV-DISCUSS-04] tidak ada tombol tandai selesai bagi peneliti', () => {
        const wrapper = mountCard()

        expect(wrapper.text()).not.toContain('Tandai Selesai')
        expect(wrapper.text()).toContain(
            'Hanya penilai yang membuka catatan ini yang dapat menandainya selesai.'
        )
    })

    it('tanpa hak balas, kotak balas tidak dirender', () => {
        const wrapper = mountCard(false)

        expect(wrapper.find('textarea').exists()).toBe(false)
        expect(wrapper.text()).toContain('Terima kasih.')
    })

    it('catatan dirender sebagai teks, bukan HTML', () => {
        const wrapper = mountCard()

        expect(wrapper.find('b').exists()).toBe(false)
        expect(wrapper.text()).toContain('<b>Perbaiki</b> metodologi.')
        expect(wrapper.find('a[href="/unduh/catatan.pdf"]').text()).toContain('catatan.pdf')
    })

    it('[REV-DISCUSS-03] reviewer menandai ulir selesai', async () => {
        const wrapper = mountCard(true, true)

        expect(wrapper.text()).not.toContain('Hanya penilai yang membuka catatan ini')
        await wrapper.find('[data-test="resolve-thread"]').trigger('click')
        wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')

        expect(routerPost).toHaveBeenCalledWith(
            '/review-summaries.resolve/11',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('pembaca tanpa hak balas tidak diajak membalas', () => {
        expect(mountCard(false).text()).not.toContain('Balas untuk menyampaikan perbaikan Anda.')
    })
})
