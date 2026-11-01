export interface Auth {
    user: User
}

export interface User {
    id: number
    name: string
    email: string
    email_verified_at?: string
    is_active: boolean
    is_reviewer: boolean
    roles: Role[]
    permissions: string[]
}
export interface Role {
    id: number
    name: string
    guard_name: string
    created_at?: string
    updated_at?: string
    permissions: Permission[]
}

export interface Permission {
    id: number
    name: string
    guard_name: string
    created_at?: string
    updated_at?: string
}

export interface NavigationFlags {
    hasApprovedSubmission: boolean
    // W32 — jumlah pengumuman terlihat yang belum dibaca (fe-v2-design §4.7).
    unreadAnnouncements: number
}

export interface FlashMessages {
    message: string | null
    error: string | null
    success: string | null
    workloadWarnings: string[] | null
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: Auth
    navigation: NavigationFlags | null
    flash: FlashMessages
}
