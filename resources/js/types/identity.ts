// Kontrak props halaman Identity/*, mengikuti app/Data/View/Identity dan app/Queries/Identity.
import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'

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

// Sumber: UserDetailData.
export interface UserDetailData {
    id: number
    name: string
    email: string
    nidn: string | null
    phone: string | null
    organization: string | null
    expertise: string | null
    registeredAt: string
    status: AccountStatus
}

// Sumber: UserAccessData. `effectivePermissions` = gabungan role + langsung (IAM-PERM-03).
export interface UserAccessData {
    roles: Option[]
    directPermissions: Option[]
    effectivePermissions: Option[]
    assignableRoles: Option[]
    grantablePermissions: Option[]
}

export interface UserSummaryData {
    activeLeadSubmissions: number
    runningReviews: number
    registeredAt: string
}

// Sumber: DeactivationBlockerData (SM-OWNER-03/04).
export interface DeactivationBlockerData {
    submissionId: number
    title: string
    status: SubmissionStatusView
    hasActiveCoInvestigator: boolean
    manageUrl: string | null
}

export interface UserSubmissionRowData {
    id: number
    title: string
    role: string
    status: SubmissionStatusView
    url: string | null
}

// `id` = SubmissionReviewer; satu pengajuan bisa muncul dua kali (diganti lalu ditugaskan lagi).
export interface UserReviewRowData {
    id: number
    submissionId: number
    title: string
    assignmentStatus: Option
    evaluationStatus: Option
    assignedAt: string
}

export type UserTab = 'profile' | 'submissions' | 'reviews'

export interface UserAbilities {
    deactivate: boolean
    changeRoles: boolean
    managePermissions: boolean
    appointReviewer: boolean
}

export interface UserShowPageProps {
    user: UserDetailData
    access: UserAccessData
    summary: UserSummaryData
    deactivationBlockers: DeactivationBlockerData[]
    submissions: UserSubmissionRowData[]
    reviews: UserReviewRowData[]
    tab: UserTab
    can: UserAbilities
}
