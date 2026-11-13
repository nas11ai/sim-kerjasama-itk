import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({ router: { post } }))

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import ReviewRoundCard from '@/Components/Review/ReviewRoundCard.vue'
import type { ReviewerRound } from '@/types/review'

const route = (name: string, params: unknown) => `/${name}/${String(params)}`

const running = (overrides: Partial<ReviewerRound> = {}): ReviewerRound => ({
    kind: 'assessment',
    finishLabel: 'Selesai menilai',
    isParticipant: true,
    finished: false,
    canFinish: true,
    reason: null,
    progress: { kind: 'assessment', number: 1, finished: 1, total: 3 },
    ...overrides,
})

const mountCard = (round: ReviewerRound | null) =>
    mount(ReviewRoundCard, {
        props: { round, submissionId: 7 },
        global: { stubs: { teleport: true }, mocks: { route } },
    })

const finishButton = (wrapper: ReturnType<typeof mountCard>) =>
    wrapper.find('button[data-action="finish-round"]')

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
})

describe('ReviewRoundCard', () => {
    it('menampilkan kemajuan putaran', () => {
        expect(mountCard(running()).text()).toContain('Putaran 1: 1 dari 3 penilai selesai')
    })

    it('Selesai menilai dikirim setelah konfirmasi', async () => {
        const wrapper = mountCard(running())

        await finishButton(wrapper).trigger('click')
        await flushPromises()
        expect(post).not.toHaveBeenCalled()
        const dialog = wrapper.findComponent(ConfirmDialog)
        expect(dialog.props('open')).toBe(true)
        expect(dialog.props('description')).toBe('Tanda ini tidak bisa dibatalkan.')

        dialog.vm.$emit('confirm')

        expect(post).toHaveBeenCalledWith(
            '/reviewer.assignments.finish/7',
            {},
            expect.objectContaining({ onFinish: expect.any(Function) })
        )
    })

    it('putaran pemeriksaan memakai label Selesai memeriksa', () => {
        const wrapper = mountCard(running({ kind: 'recheck', finishLabel: 'Selesai memeriksa' }))

        expect(finishButton(wrapper).text()).toBe('Selesai memeriksa')
    })

    it('[REV-OUTCOME-14] tombol nonaktif dengan alasan selama lembar belum terkirim', () => {
        const wrapper = mountCard(
            running({ canFinish: false, reason: 'Kirim lembar penilaian lebih dulu.' })
        )

        expect(finishButton(wrapper).attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('Kirim lembar penilaian lebih dulu.')
    })

    it('[REV-OUTCOME-15] setelah Selesai tidak ada tombol, hanya keterangan', () => {
        const wrapper = mountCard(
            running({
                finished: true,
                canFinish: false,
                reason: 'Anda sudah menyelesaikan putaran ini.',
            })
        )

        expect(finishButton(wrapper).exists()).toBe(false)
        expect(wrapper.text()).toContain('Anda sudah menyelesaikan putaran ini.')
    })

    it('[REV-OUTCOME-08] tidak ada jalur tolak di layar reviewer', () => {
        const wrapper = mountCard(null)

        expect(wrapper.text()).toContain(
            'Penolakan tidak tersedia di layar penilai. Hanya Operator yang dapat menolak, lewat aksi tertulis.'
        )
        expect(wrapper.find('button').exists()).toBe(false)
    })
})
