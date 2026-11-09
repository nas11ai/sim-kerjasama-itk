import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const EchoMock = vi.hoisted(() => vi.fn())
const PusherMock = vi.hoisted(() => vi.fn())

vi.mock('laravel-echo', () => ({ default: EchoMock }))
vi.mock('pusher-js', () => ({ default: PusherMock }))

async function freshEcho() {
    vi.resetModules()
    return (await import('@/lib/echo')).echo
}

describe('echo()', () => {
    beforeEach(() => {
        EchoMock.mockReset()
        EchoMock.mockImplementation(function (this: object) {
            return this
        })
        vi.stubEnv('VITE_REVERB_HOST', 'ws.simpas.test')
        vi.stubEnv('VITE_REVERB_PORT', '8080')
        vi.stubEnv('VITE_REVERB_SCHEME', 'http')
    })

    afterEach(() => {
        vi.unstubAllEnvs()
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
    })

    it('tanpa VITE_REVERB_APP_KEY tidak membuka koneksi', async () => {
        vi.stubEnv('VITE_REVERB_APP_KEY', '')
        const echo = await freshEcho()

        expect(echo()).toBeNull()
        expect(EchoMock).not.toHaveBeenCalled()
        expect(PusherMock).not.toHaveBeenCalled()
    })

    it('dengan kunci, satu instance dipakai bersama', async () => {
        vi.stubEnv('VITE_REVERB_APP_KEY', 'kunci')
        const echo = await freshEcho()

        const first = echo()
        expect(first).not.toBeNull()
        expect(echo()).toBe(first)
        expect(EchoMock).toHaveBeenCalledTimes(1)
        expect(EchoMock.mock.calls[0][0]).toMatchObject({
            broadcaster: 'reverb',
            key: 'kunci',
            wsHost: 'ws.simpas.test',
            wsPort: 8080,
            wssPort: 8080,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            Pusher: PusherMock,
        })
    })

    it('otorisasi kanal membawa token XSRF yang berlaku saat itu, bukan saat koneksi dibuat', async () => {
        vi.stubEnv('VITE_REVERB_APP_KEY', 'kunci')
        const echo = await freshEcho()
        echo()
        const { channelAuthorization } = EchoMock.mock.calls[0][0]

        document.cookie = 'XSRF-TOKEN=lama%3D'
        expect(channelAuthorization.headersProvider()).toEqual({ 'X-XSRF-TOKEN': 'lama=' })
        document.cookie = 'XSRF-TOKEN=baru'
        expect(channelAuthorization.headersProvider()).toEqual({ 'X-XSRF-TOKEN': 'baru' })
        expect(channelAuthorization.endpoint).toBe('/broadcasting/auth')
    })
})
