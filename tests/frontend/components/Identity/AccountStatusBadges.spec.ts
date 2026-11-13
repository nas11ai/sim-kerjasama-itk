import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

type Listener = (payload: { userId: number; at: string }) => void

let listener: Listener | null = null
const listen = vi.fn((_event: string, callback: Listener) => {
    listener = callback
    return { listen }
})
vi.mock('@/lib/echo', () => ({ echo: () => ({ private: () => ({ listen }), leave: vi.fn() }) }))

import AccountStatusBadges from '@/Components/Identity/AccountStatusBadges.vue'

describe('AccountStatusBadges', () => {
    beforeEach(() => (listener = null))

    it('menampilkan "Sedang aktif" begitu session.active untuk user-nya tiba', async () => {
        const wrapper = mount(AccountStatusBadges, {
            props: {
                userId: 7,
                status: {
                    active: true,
                    verification: null,
                    online: false,
                    lastActiveAt: null,
                    checkedAt: new Date().toISOString(),
                },
            },
        })
        expect(wrapper.text()).not.toContain('Sedang aktif')

        listener?.({ userId: 8, at: new Date().toISOString() })
        await nextTick()
        expect(wrapper.text()).not.toContain('Sedang aktif')

        listener?.({ userId: 7, at: new Date().toISOString() })
        await nextTick()
        expect(wrapper.text()).toContain('Sedang aktif')
    })
})
