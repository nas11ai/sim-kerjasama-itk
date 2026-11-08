export interface Auth {
    user: User | null
}

// Sumber: HandleInertiaRequests::share — `auth.user`, null bagi tamu (W162).
export interface User {
    id: number
    name: string
    email: string
    permissions: string[]
}

export interface NavigationFlags {
    // W142 — Daftar Luaran berisi pengajuan yang luarannya bisa dibaca.
    hasOutputSubmission: boolean
    // W128 — Daftar Monev berisi induk yang bisa dipantau.
    hasMonevSubmission: boolean
    // W32 — jumlah pengumuman terlihat yang belum dibaca (fe-v2-design §4.7).
    unreadAnnouncements: number
    // W38/W41 — notifikasi in-app belum dibaca, ber-cache (BR-NOT-05).
    unreadNotifications: number
}

export interface FlashMessages {
    workloadWarnings: string[] | null
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: Auth
    navigation: NavigationFlags | null
    flash: FlashMessages
}
