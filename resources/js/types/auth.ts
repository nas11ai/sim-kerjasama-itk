// Kontrak props halaman Auth/*, mengikuti controller di app/Http/Controllers/Auth.
import type { AnnouncementSummary } from '@/types/announcement'

export interface LoginPageProps {
    canResetPassword: boolean
    status: string | null
    // fe-v2-design §4.7, W6 — pengumuman publik terbaru, paling banyak 5.
    announcements: AnnouncementSummary[]
}

export interface RegistrableFaculty {
    id: number
    name: string
    study_programs: { id: number; name: string }[]
}

export interface RegisterPageProps {
    faculties: RegistrableFaculty[]
}

// type, bukan interface: useForm butuh index signature implisit.
export type RegisterForm = {
    name: string
    email: string
    nidn: string
    organization_id: number | null
    password: string
    password_confirmation: string
}

export interface RegisterInvitationPageProps {
    token: string
    organization: string | null
    permissions: string[]
}

// Sumber: app/Data/View/AccountVerificationData.php. Akun aktif tidak pernah sampai ke layar ini.
export interface AccountVerification {
    status: 'pending' | 'rejected'
    statusLabel: string
    nidn: string | null
    email: string
    rejectionReason: string | null
    rejectedAt: string | null
}

export interface VerificationStatusPageProps {
    verification: AccountVerification
}

export interface ForgotPasswordPageProps {
    status: string | null
}

// email null bila tautan reset dibuka tanpa query ?email=.
export interface ResetPasswordPageProps {
    email: string | null
    token: string
}

export interface VerifyEmailPageProps {
    status: string | null
}
