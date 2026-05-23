export interface Auth {
    user: User
}

export interface User {
    id: number
    name: string
    email: string
    email_verified_at?: string
    is_reviewer: boolean
    roles: Role[]
    permissions: Permission[]
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

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: Auth
}
