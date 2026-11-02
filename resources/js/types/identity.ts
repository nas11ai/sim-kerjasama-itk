// Kontrak props halaman Identity/*, mengikuti app/Data/View/Identity dan app/Queries/Identity.
import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'

export type Option = FilterOption

// Sumber: AccountStatusData. `verification` null = terverifikasi, akun undangan, atau tanpa profil (W49).
export interface AccountStatus {
    active: boolean
    verification: Option | null
}

// Sumber: UserRowData.
export interface UserRowData {
    id: number
    name: string
    email: string
    nidn: string | null
    organization: string | null
    roles: Option[]
    status: AccountStatus
    showUrl: string
}

export type UserIndexFilters = {
    search: string
    role: string
    account: string
    verification: string
}

export interface UserIndexPageProps {
    users: Paginated<UserRowData>
    filters: UserIndexFilters
    roleOptions: Option[]
    accountOptions: Option[]
    verificationOptions: Option[]
    awaitingVerification: number
    can: { verify: boolean; manageInvitations: boolean }
}
