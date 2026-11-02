// Kontrak SHARED-01-06/07, cermin `App\Data\View\Identity\OwnProfileData`.
export interface OwnProfile {
    name: string
    email: string
    hasProfile: boolean
    nidn: string | null
    organization: string | null
    phone: string | null
    expertise: string | null
    photoUrl: string | null
    /** BR-IAM-03 — label data tambahan yang belum terisi; satu-satunya sumber "belum lengkap". */
    missing: string[]
}

export interface ProfileShowPageProps {
    profile: OwnProfile
}
