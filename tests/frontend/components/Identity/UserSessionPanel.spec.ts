import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import type { UserDetailData } from '@/types/identity'

type Listener = (payload: { userId: number; at: string }) => void

let listener: Listener | null = null
const listen = vi.fn((_event: string, callback: Listener) => {
    listener = callback
    return { listen }
})
vi.mock('@/lib/echo', () => ({ echo: () => ({ private: () => ({ listen }), leave: vi.fn() }) }))
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }))

import UserSessionPanel from '@/Components/Identity/UserSessionPanel.vue'

const user: UserDetailData = {
    id: 7,
    name: 'Sari',
    email: 'sari@itk.ac.id',
    nidn: null,
    phone: null,
    organization: null,
    expertise: null,
    registeredAt: null,
    status: { active: true, verification: null, online: false, lastActiveAt: null },
}

const Stub = defineComponent({
    setup:
        (_p, { slots }) =>
        () =>
            h('div', slots.default?.()),
})

describe('UserSessionPanel', () => {
    beforeEach(() => (listener = null))

    it('berganti dari "Belum ada sesi tercatat" ke "Sedang aktif" tanpa reload', async () => {
        const wrapper = mount(UserSessionPanel, {
            props: { user, canEnd: false },
            global: { stubs: { SectionCard: Stub, ConfirmDialog: true } },
        })
        expect(wrapper.text()).toContain('Belum ada sesi tercatat')

        listener?.({ userId: 7, at: new Date().toISOString() })
        await nextTick()

        expect(wrapper.text()).toContain('Sedang aktif')
    })
})
