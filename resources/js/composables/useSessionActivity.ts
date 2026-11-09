import { computed, onUnmounted, reactive, ref, type ComputedRef } from 'vue'

import { echo } from '@/lib/echo'
import type { AccountStatus } from '@/types/identity'

// RT1, W168 — badge "Sedang aktif" menyala tanpa reload. Sumber kehadiran tetap server
// (SessionRegistry); dorongan `session.active` hanya memperbarui `lastActiveAt` di sini.

/** BR-IAM-21 — cermin SessionRegistry::ONLINE_WINDOW_MINUTES. */
export const ONLINE_WINDOW_MS = 5 * 60_000

const CHANNEL = 'identity.activity'
const TICK_MS = 30_000

interface SessionActive {
    userId: number
    at: string
}

const pushed = reactive(new Map<number, string>())
const now = ref(Date.now())
let consumers = 0
let timer: ReturnType<typeof setInterval> | null = null

function subscribe(): void {
    // Pemakai baru selalu merender dengan jam sekarang, bukan detak terakhir (≤ 30 detik basi).
    now.value = Date.now()
    consumers += 1
    if (consumers > 1) {
        return
    }

    timer = setInterval(() => (now.value = Date.now()), TICK_MS)
    echo()
        ?.private(CHANNEL)
        .listen('.session.active', (event: SessionActive) => pushed.set(event.userId, event.at))
}

function unsubscribe(): void {
    consumers -= 1
    if (consumers > 0) {
        return
    }

    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
    echo()?.leave(CHANNEL)
}

function latest(server: string | null, event: string | undefined): string | null {
    if (event === undefined) {
        return server
    }

    return server !== null && Date.parse(server) >= Date.parse(event) ? server : event
}

export function useLiveAccountStatus(
    userId: () => number,
    status: () => AccountStatus
): ComputedRef<AccountStatus> {
    // Di setup, bukan onMounted: render pertama harus memakai jam sekarang, bukan `now` basi.
    subscribe()
    onUnmounted(unsubscribe)

    return computed(() => {
        const base = status()
        const lastActiveAt = latest(base.lastActiveAt, pushed.get(userId()))
        const online =
            lastActiveAt !== null && now.value - Date.parse(lastActiveAt) <= ONLINE_WINDOW_MS

        return { ...base, lastActiveAt, online }
    })
}
