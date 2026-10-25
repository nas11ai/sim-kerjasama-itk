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
