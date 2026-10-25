// Kontrak props halaman Auth/*, mengikuti controller di app/Http/Controllers/Auth.

export interface LoginPageProps {
    canResetPassword: boolean
    status: string | null
}

export interface RegistrableFaculty {
    id: number
    name: string
    study_programs: { id: number; name: string }[]
}

export interface RegisterPageProps {
    faculties: RegistrableFaculty[]
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
}

export interface VerificationStatusPageProps {
    verification: AccountVerification
}
