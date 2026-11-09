import { beforeEach, describe, expect, it, vi } from 'vitest'

type Listener = (payload: { reason: string }) => void

const listeners = new Map<string, Listener>()
const listen = vi.fn((event: string, callback: Listener) => {
    listeners.set(event, callback)
    return { listen }
})
const fake = {
    private: vi.fn((_name: string) => ({ listen })),
    leave: vi.fn(),
}
const echoMock = vi.hoisted(() => vi.fn())

vi.mock('@/lib/echo', () => ({ echo: echoMock }))

async function freshSync() {
    vi.resetModules()
    return (await import('@/lib/accountChannel')).syncAccountChannel
}

describe('syncAccountChannel', () => {
    beforeEach(() => {
        listeners.clear()
        listen.mockClear()
        fake.private.mockClear()
        fake.leave.mockClear()
        echoMock.mockReset()
        echoMock.mockReturnValue(fake)
        vi.stubGlobal(
            'route',
            (name: string, params: { sesi: string }) => `/${name}?sesi=${params.sesi}`
        )
    })

    it('berlangganan kanal privat user sekali, tidak dobel di kunjungan berikutnya', async () => {
        const sync = await freshSync()

        sync(7)
        sync(7)

        expect(fake.private).toHaveBeenCalledTimes(1)
        expect(fake.private).toHaveBeenCalledWith('App.Models.User.7')
        expect(listen).toHaveBeenCalledWith('.sessions.ended', expect.any(Function))
    })

    it('ganti user tanpa muat ulang meninggalkan kanal lama lebih dulu', async () => {
        const sync = await freshSync()

        sync(7)
        sync(9)

        expect(fake.leave).toHaveBeenCalledWith('App.Models.User.7')
        expect(fake.private).toHaveBeenLastCalledWith('App.Models.User.9')
        expect(fake.leave.mock.invocationCallOrder[0]).toBeLessThan(
            fake.private.mock.invocationCallOrder[1]
        )
    })

    it('logout (user null) meninggalkan kanal tanpa berlangganan yang baru', async () => {
        const sync = await freshSync()

        sync(9)
        sync(null)

        expect(fake.leave).toHaveBeenCalledWith('App.Models.User.9')
        expect(fake.private).toHaveBeenCalledTimes(1)
    })

    it('[IAM-DEACT-09] sessions.ended memindahkan tab ke login dengan alasannya', async () => {
        const assign = vi.fn()
        vi.stubGlobal('location', { ...window.location, assign })
        const sync = await freshSync()

        sync(7)
        listeners.get('.sessions.ended')?.({ reason: 'nonaktif' })

        expect(assign).toHaveBeenCalledWith('/login?sesi=nonaktif')
    })

    it('tanpa Reverb (echo() null) tidak melakukan apa pun', async () => {
        echoMock.mockReturnValue(null)
        const sync = await freshSync()

        expect(() => {
            sync(7)
            sync(null)
        }).not.toThrow()
        expect(fake.private).not.toHaveBeenCalled()
        expect(fake.leave).not.toHaveBeenCalled()
    })
})
