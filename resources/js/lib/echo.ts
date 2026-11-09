import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// RT1 — satu koneksi Reverb per tab, dibuat saat pertama dibutuhkan. Dorongan ini hanya
// mempercepat tampilan; penegakan tetap di server (EnsureAccountActive, W169). Build tanpa
// VITE_REVERB_APP_KEY (CI, test, dev tanpa Reverb) tidak membuka koneksi sama sekali.

let instance: Echo<'reverb'> | null = null

/**
 * Token XSRF dibaca ulang di setiap otorisasi kanal. Meta `csrf-token` hanya dirender sekali
 * dan tidak diperbarui Inertia, padahal logout memutar tokennya: login ulang tanpa muat ulang
 * akan ditolak 419 bila memakai meta itu.
 */
function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)

    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {}
}

export function echo(): Echo<'reverb'> | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY
    if (!key) {
        return null
    }

    if (instance === null) {
        const secure = import.meta.env.VITE_REVERB_SCHEME === 'https'
        const port = Number(import.meta.env.VITE_REVERB_PORT) || (secure ? 443 : 80)

        instance = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: secure,
            enabledTransports: ['ws', 'wss'],
            Pusher,
            channelAuthorization: {
                endpoint: '/broadcasting/auth',
                transport: 'ajax',
                headersProvider: xsrfHeader,
            },
        })
    }

    return instance
}
