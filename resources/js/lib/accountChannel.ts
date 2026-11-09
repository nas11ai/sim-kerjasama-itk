import { echo } from '@/lib/echo'

// RT1 — kanal privat milik user yang sedang login. Dipasang di level aplikasi, bukan layout,
// supaya kunjungan Inertia tidak berlangganan ulang. Logout lalu login sebagai user lain tanpa
// muat ulang memindahkan langganan; tab tidak pernah mendengar sessions.ended milik orang lain.

interface SessionsEnded {
    reason: string
}

let currentId: number | null = null

const channelOf = (userId: number) => `App.Models.User.${userId}`

/**
 * BR-IAM-05, BR-IAM-21 — sesi sudah dihapus di server; di sini tab hanya dipindahkan ke login.
 * Muat ulang penuh (bukan kunjungan Inertia) memutus koneksi Echo dan membuang data di layar (W170).
 */
function leaveToLogin(event: SessionsEnded): void {
    window.location.assign(route('login', { sesi: event.reason }))
}

export function syncAccountChannel(userId: number | null): void {
    const connection = echo()
    if (connection === null || userId === currentId) {
        return
    }

    if (currentId !== null) {
        connection.leave(channelOf(currentId))
    }

    currentId = userId
    if (userId === null) {
        return
    }

    connection.private(channelOf(userId)).listen('.sessions.ended', leaveToLogin)
}
