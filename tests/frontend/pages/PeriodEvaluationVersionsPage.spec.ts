import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { post: vi.fn() } }))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots, attrs }) =>
            () =>
                h('a', { ...attrs, href: props.href }, slots.default?.()),
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

import PeriodEvaluationVersionsPage from '@/Pages/FormEngine/PeriodEvaluationVersionsPage.vue'
import type { CohortEvaluationVersion } from '@/types/form-engine'

const row = (overrides: Partial<CohortEvaluationVersion> = {}): CohortEvaluationVersion => ({
    phaseTitle: 'Penelitian DIPA',
    stepLabel: 'Pengajuan',
    formTitle: 'Lembar Proposal',
    pinId: 7,
    pinnedLabel: 'Versi 1',
    latestLabel: 'Versi 2',
    responseCount: 3,
    decidedCount: 0,
    canApply: true,
    ...overrides,
})

const route = (name: string, params?: number) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

let wrapper: VueWrapper

const mountPage = (rows: CohortEvaluationVersion[]) => {
    wrapper = mount(PeriodEvaluationVersionsPage, {
        props: { period: { id: 5, name: '2025' }, rows },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    return wrapper
}

const buttons = (label: string) =>
    Array.from(document.querySelectorAll('button')).filter((b) => b.textContent?.trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.post.mockClear()
})

afterEach(() => {
    wrapper.unmount()
    document.body.innerHTML = ''
})

describe('FormEngine/PeriodEvaluationVersionsPage', () => {
    it('[REV-VER-01] angkatan yang belum mulai dinilai memakai versi terbaru, tanpa tombol', () => {
        mountPage([row({ pinId: null, pinnedLabel: null, responseCount: 0, canApply: false })])

        expect(wrapper.text()).toContain('Belum mulai dinilai — memakai Versi 2')
        expect(buttons('Terapkan Versi 2')).toHaveLength(0)
    })

    it('[REV-VER-06] tombol Terapkan hanya untuk angkatan yang boleh diterapkan', () => {
        mountPage([row(), row({ pinnedLabel: 'Versi 2', canApply: false })])

        expect(buttons('Terapkan Versi 2')).toHaveLength(1)
        expect(wrapper.text()).toContain('Memakai versi terbaru')
    })

    it('[REV-VER-07] langkah yang sudah ada keputusannya menyebut alasannya, tanpa tombol', () => {
        mountPage([row({ decidedCount: 2, canApply: false })])

        expect(wrapper.text()).toContain(
            'Tidak bisa diterapkan: 2 pengajuan di langkah ini sudah diputuskan.'
        )
        expect(buttons('Terapkan Versi 2')).toHaveLength(0)
    })

    it('[REV-VER-08] konfirmasi menyebut jumlah penilaian yang dibuka ulang, lalu mengirim', async () => {
        mountPage([row()])

        buttons('Terapkan Versi 2')[0]?.click()
        await flushPromises()

        const dialog = document.querySelector('[role="dialog"]')
        expect(dialog?.textContent).toContain(
            'Terapkan Versi 2 ke angkatan ini? 3 penilaian akan dibuka ulang dan reviewer-nya diberi tahu.'
        )
        const confirm = Array.from(dialog?.querySelectorAll('button') ?? []).find(
            (b) => b.textContent?.trim() === 'Terapkan Versi 2'
        )
        confirm?.click()
        expect(router.post).toHaveBeenCalledWith(
            '/admin.evaluation-form-pins.apply/7',
            {},
            expect.any(Object)
        )
    })

    it('tanpa langkah direview menampilkan keadaan kosong', () => {
        mountPage([])

        expect(wrapper.text()).toContain('Belum ada Form Evaluasi di tahap periode ini')
    })
})
