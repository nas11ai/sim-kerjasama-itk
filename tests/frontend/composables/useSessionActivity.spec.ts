import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import type { AccountStatus } from '@/types/identity'

type Listener = (payload: { userId: number; at: string }) => void

let listener: Listener | null = null
const listen = vi.fn((_event: string, callback: Listener) => {
    listener = callback
    return { listen }
})
const fake = { private: vi.fn((_name: string) => ({ listen })), leave: vi.fn() }
const echoMock = vi.hoisted(() => vi.fn())

vi.mock('@/lib/echo', () => ({ echo: echoMock }))

const NOW = new Date('2026-11-09T10:00:00Z')
const offline: AccountStatus = {
    active: true,
    verification: null,
    online: false,
    lastActiveAt: null,
    checkedAt: NOW.toISOString(),
}

async function load() {
    vi.resetModules()
    return import('@/composables/useSessionActivity')
}

/** Komponen uji: memasang composable dan mengekspos hasilnya. */
type Module = Awaited<ReturnType<typeof load>>

function mountProbe(module: Module, userId: number, status: AccountStatus) {
    let live: { value: AccountStatus } = { value: status }
    const Probe = defineComponent({
        setup() {
            live = module.useLiveAccountStatus(
                () => userId,
                () => status
            )
            return () => h('span')
        },
    })
    mount(Probe)
    return () => live.value
}

async function probe(userId: number, status: AccountStatus) {
    return { live: mountProbe(await load(), userId, status) }
}

describe('useLiveAccountStatus', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        vi.setSystemTime(NOW)
        listener = null
        listen.mockClear()
        fake.private.mockClear()
        fake.leave.mockClear()
        echoMock.mockReset()
        echoMock.mockReturnValue(fake)
    })

    afterEach(() => vi.useRealTimers())

    it('session.active menyalakan user itu saja', async () => {
        const module = await load()
        const live = mountProbe(module, 7, offline)
        const other = mountProbe(module, 8, offline)
        expect(live().online).toBe(false)

        listener?.({ userId: 7, at: NOW.toISOString() })

        expect(live()).toMatchObject({ online: true, lastActiveAt: NOW.toISOString() })
        expect(other()).toMatchObject({ online: false, lastActiveAt: null })
    })

    it('[IAM-SESSION-02] badge padam setelah jendela 5 menit, terakhir aktif tetap terbaca', async () => {
        const { live } = await probe(7, offline)
        listener?.({ userId: 7, at: NOW.toISOString() })

        vi.advanceTimersByTime(5 * 60_000 + 30_000)

        expect(live()).toMatchObject({ online: false, lastActiveAt: NOW.toISOString() })
    })

    it('lastActiveAt dari server yang lebih baru tetap dipakai', async () => {
        const newer = new Date(NOW.getTime() - 60_000).toISOString()
        const { live } = await probe(7, { ...offline, online: true, lastActiveAt: newer })

        listener?.({ userId: 7, at: new Date(NOW.getTime() - 4 * 60_000).toISOString() })

        expect(live().lastActiveAt).toBe(newer)
    })

    it('dua pemakai berbagi satu langganan; keduanya lepas → kanal ditinggalkan', async () => {
        const { useLiveAccountStatus } = await load()
        const Probe = defineComponent({
            props: { id: { type: Number, required: true } },
            setup(props) {
                useLiveAccountStatus(
                    () => props.id,
                    () => offline
                )
                return () => h('span')
            },
        })

        const a = mount(Probe, { props: { id: 7 } })
        const b = mount(Probe, { props: { id: 8 } })
        expect(fake.private).toHaveBeenCalledTimes(1)
        expect(fake.private).toHaveBeenCalledWith('identity.activity')

        a.unmount()
        expect(fake.leave).not.toHaveBeenCalled()
        b.unmount()
        expect(fake.leave).toHaveBeenCalledWith('identity.activity')
        expect(vi.getTimerCount()).toBe(0)
    })

    it('[IAM-SESSION-02] jam perangkat yang meleset tidak menggeser badge (E2E-16)', async () => {
        // Jam server 10:00; perangkat ini 10 menit lebih cepat.
        vi.setSystemTime(new Date(NOW.getTime() + 10 * 60_000))
        const twoMinutesAgo = new Date(NOW.getTime() - 2 * 60_000).toISOString()
        const { live } = await probe(7, { ...offline, online: true, lastActiveAt: twoMinutesAgo })

        expect(live().online).toBe(true)

        vi.advanceTimersByTime(3 * 60_000 + 30_000)

        expect(live().online).toBe(false)
    })

    it('jam perangkat yang tertinggal juga dikoreksi lewat dorongan session.active', async () => {
        // Perangkat 10 menit lebih lambat: tanpa koreksi, dorongan baru tampak aktif sampai 15 menit.
        vi.setSystemTime(new Date(NOW.getTime() - 10 * 60_000))
        const { live } = await probe(7, offline)
        listener?.({ userId: 7, at: NOW.toISOString() })
        expect(live().online).toBe(true)

        vi.advanceTimersByTime(5 * 60_000 + 30_000)

        expect(live().online).toBe(false)
    })

    it('status lama yang dipulihkan tidak menggeser koreksi jam', async () => {
        const module = await load()
        mountProbe(module, 8, offline)
        // Status dari riwayat: checkedAt satu jam lalu, lastActiveAt dua menit sebelumnya.
        const hourAgo = NOW.getTime() - 60 * 60_000
        const restored = mountProbe(module, 7, {
            ...offline,
            online: true,
            lastActiveAt: new Date(hourAgo - 2 * 60_000).toISOString(),
            checkedAt: new Date(hourAgo).toISOString(),
        })

        expect(restored().online).toBe(false)
    })

    it('tanpa Reverb status mengikuti prop dan tetap padam setelah jendela lewat', async () => {
        echoMock.mockReturnValue(null)
        const { live } = await probe(7, {
            ...offline,
            online: true,
            lastActiveAt: NOW.toISOString(),
        })
        expect(live().online).toBe(true)

        vi.advanceTimersByTime(5 * 60_000 + 30_000)

        expect(live().online).toBe(false)
        expect(fake.private).not.toHaveBeenCalled()
    })
})
