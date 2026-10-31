import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { get },
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

import AssignmentCard from '@/Components/Review/AssignmentCard.vue'
import AssignmentIndexPage from '@/Pages/Review/AssignmentIndexPage.vue'
import type { AssignmentCard as Card, AssignmentIndexPageProps } from '@/types/review'

const card: Card = {
    submissionId: 9,
    title: 'Smart Farming Hidroponik Kampus',
    leadName: 'Ir. Dewi Lestari',
    scheme: null,
    dueDate: null,
    state: 'done',
    stateLabel: 'Selesai',
}

const route = (name: string, params?: unknown) =>
    params === undefined ? `/${name}` : `/${name}/${String(params)}`

const mountPage = (props: Partial<AssignmentIndexPageProps> = {}) =>
    mount(AssignmentIndexPage, {
        props: { needsAction: [], done: [], filter: 'action', ...props },
        global: { mocks: { route } },
    })

beforeEach(() => {
    vi.stubGlobal('route', route)
    get.mockReset()
})

describe('AssignmentIndexPage', () => {
    it('segmen Semua meminta filter all', async () => {
        await mountPage().find('button[data-filter="all"]').trigger('click')

        expect(get).toHaveBeenCalledWith(
            '/reviewer.assignments.index',
            { filter: 'all' },
            expect.objectContaining({ preserveState: true, replace: true })
        )
    })

    it('tanpa penugasan perlu tindakan menampilkan keadaan kosong', () => {
        expect(mountPage().text()).toContain('Tidak ada penugasan yang menunggu tindakan Anda')
    })

    it('seksi Selesai hanya tampil pada filter Semua', () => {
        expect(mountPage({ done: [card] }).text()).not.toContain('Selesai')

        const wrapper = mountPage({ done: [card], filter: 'all' })
        expect(wrapper.findAllComponents(AssignmentCard)).toHaveLength(1)
    })
})
