// Kontrak props halaman Identity/*, mengikuti app/Data/View/Identity dan app/Queries/Identity.
import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'

export type Option = FilterOption

// Sumber: AccountStatusData. `verification` null = terverifikasi, akun undangan, atau tanpa profil (W49).
// `online` = aktivitas sesi ≤ 5 menit (BR-IAM-21); `lastActiveAt` null = tak ada sesi tercatat.
// Di layar, keduanya dihitung ulang oleh useLiveAccountStatus dari dorongan session.active (RT1).
// `checkedAt` = jam server saat status dibaca; dipakai untuk mengoreksi jam perangkat (E2E-16).
export interface AccountStatus {
    active: boolean
    verification: Option | null
    online: boolean
    lastActiveAt: string | null
    checkedAt: string
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
    presence: string
}

export interface UserIndexPageProps {
    users: Paginated<UserRowData>
    filters: UserIndexFilters
    roleOptions: Option[]
    accountOptions: Option[]
    verificationOptions: Option[]
    presenceOptions: Option[]
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
    registeredAt: string | null
    status: AccountStatus
}

// Sumber: UserAccessData. `effectivePermissions` = gabungan role + langsung (IAM-PERM-03).
// Sumber: OrganizationOptions — `depth` = tingkat pohon untuk indentasi (K6, 2026-11-13).
export interface OrganizationOption extends Option {
    depth: number
}

// Sumber: PermissionCatalog::options — `description` dibaca tooltip hak akses (K5, 2026-11-13).
export interface PermissionOption extends Option {
    description: string
}

export interface UserAccessData {
    roles: Option[]
    directPermissions: PermissionOption[]
    effectivePermissions: PermissionOption[]
    assignableRoles: Option[]
    grantablePermissions: PermissionOption[]
}

export interface UserSummaryData {
    activeLeadSubmissions: number
    runningReviews: number
    registeredAt: string | null
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
    assignedAt: string | null
}

export type UserTab = 'profile' | 'submissions' | 'reviews'

export interface UserAbilities {
    deactivate: boolean
    changeRoles: boolean
    managePermissions: boolean
    appointReviewer: boolean
    endSessions: boolean
    reactivate: boolean
}

export interface UserShowPageProps {
    // Tipe reviewer untuk dialog "Tunjuk sebagai Reviewer" (BR-IAM-11).
    reviewerTypeOptions: Option[]
    user: UserDetailData
    access: UserAccessData
    summary: UserSummaryData
    deactivationBlockers: DeactivationBlockerData[]
    submissions: UserSubmissionRowData[]
    reviews: UserReviewRowData[]
    tab: UserTab
    can: UserAbilities
}

// Sumber: VerificationCardData (OPERATOR-08-02). `rejectedAt` dari entri audit penolakan terbaru (W53).
export interface VerificationCardData {
    profileId: number
    name: string
    email: string
    nidn: string | null
    organization: string | null
    registeredAt: string | null
    rejectedAt: string | null
    rejectionReason: string | null
}

export interface VerificationIndexPageProps {
    pending: VerificationCardData[]
    rejected: VerificationCardData[]
}

// Sumber: InvitationRowData (ADMIN-08-01). `preset` null = token lama/kustom; `maxUses` null = tanpa kuota.
export type InvitationStatusValue =
    | 'valid'
    | 'revoked'
    | 'organization_inactive'
    | 'exhausted'
    | 'expired'

export interface InvitationRowData {
    id: number
    organization: string | null
    organizationId: number | null
    preset: Option | null
    usedCount: number
    maxUses: number | null
    remainingUses: number | null
    expiresAt: string | null
    status: { value: InvitationStatusValue; label: string }
    revocable: boolean
}

export interface InvitationIndexPageProps {
    invitations: InvitationRowData[]
    organizationOptions: Option[]
    presetOptions: Option[]
}

// Sumber: ReviewerRowData (OPERATOR-08-04). Tanggal masa tugas berbentuk YYYY-MM-DD.
export type ReviewerStanding = 'active' | 'not_started' | 'ended' | 'account_inactive'

export interface ReviewerRowData {
    id: number
    userId: number
    name: string
    type: Option
    tenureStart: string
    tenureEnd: string | null
    // W54 — penugasan aktif yang evaluasinya belum beres.
    runningAssignments: number
    standing: { value: ReviewerStanding; label: string }
    historyUrl: string | null
}

// Sumber: AppointmentCandidateData. `currentReviewer` terisi = penunjukan memperbarui (IAM-REVIEWER-04).
export interface AppointmentCandidateData {
    userId: number
    name: string
    email: string
    nidn: string | null
    organization: string | null
    currentReviewer: {
        type: Option
        tenureStart: string
        tenureEnd: string | null
        standing: Option
    } | null
}

export type ReviewerIndexFilters = { search: string; type: string; standing: string }

export interface ReviewerIndexPageProps {
    reviewers: Paginated<ReviewerRowData>
    filters: ReviewerIndexFilters
    typeOptions: Option[]
    standingOptions: Option[]
    appointmentCandidates?: AppointmentCandidateData[]
    can: { manageInvitations: boolean }
}
