// Kontrak props halaman Auth/*, mengikuti controller di app/Http/Controllers/Auth.

export interface LoginPageProps {
    canResetPassword: boolean
    status: string | null
}
