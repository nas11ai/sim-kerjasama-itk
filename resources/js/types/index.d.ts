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
}

export interface FlashMessages {
    message: string | null
    error: string | null
    success: string | null
    workloadWarnings: unknown[] | null
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: Auth
    navigation: NavigationFlags | null
    flash: FlashMessages
}
