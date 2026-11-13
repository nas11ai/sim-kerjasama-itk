import {
    Bell,
    BookOpen,
    ClipboardList,
    Cog,
    FileText,
    Plus,
    Settings,
    SquarePen,
    User,
    Users,
    type LucideIcon,
} from 'lucide-vue-next'

export interface QuickAction {
    label: string
    routeName: string
    params?: Record<string, string>
    icon: LucideIcon
    primary: boolean
}

type QuickActionDef = Omit<QuickAction, 'primary'> & {
    /** kosong = semua pengguna */
    permission: string | null
}

// W45 — urutan frame SHARED-02-01..04; diturunkan dari permission seperti sidebar (§4.4).
const definitions: QuickActionDef[] = [
    {
        label: 'Buat Pengajuan Baru',
        routeName: 'user.submissions.create',
        icon: Plus,
        permission: 'submissions.create',
    },
    {
        label: 'Lihat Daftar Pengajuan',
        routeName: 'user.submissions.index',
        icon: FileText,
        permission: 'submissions.view-own',
    },
    {
        label: 'Lihat Penugasan Penilaian',
        routeName: 'reviewer.assignments.index',
        icon: ClipboardList,
        permission: 'reviewers.evaluate',
    },
    {
        label: 'Riwayat Penilaian',
        routeName: 'reviewer.history.index',
        icon: BookOpen,
        permission: 'reviewers.evaluate',
    },
    {
        label: 'Semua Pengajuan',
        routeName: 'admin.submissions.index',
        icon: FileText,
        permission: 'submissions.view-all',
    },
    {
        label: 'Tugaskan Penilai',
        routeName: 'admin.submissions.index',
        params: { status: 'submitted' },
        icon: Users,
        permission: 'reviewers.assign',
    },
    {
        label: 'Antrian Verifikasi',
        routeName: 'admin.verifications.index',
        icon: User,
        permission: 'users.verify',
    },
    {
        label: 'Kelola Skema',
        routeName: 'admin.schemes.index',
        icon: Settings,
        permission: 'schemes.manage',
    },
    {
        label: 'Pembuat Formulir',
        routeName: 'admin.forms.index',
        icon: SquarePen,
        permission: 'schemes.manage',
    },
    {
        label: 'Konfigurasi Umum',
        routeName: 'admin.reference.index',
        icon: Cog,
        permission: 'schemes.manage',
    },
    { label: 'Notifikasi', routeName: 'notifications.index', icon: Bell, permission: null },
]

/**
 * Aksi cepat Beranda; satu daftar tetap, jadi user dua peran tidak mendapat duplikat.
 * Paket 2 P1 — tidak ada aksi nonaktif: "Buat Pengajuan Baru" dengan profil belum lengkap tetap
 * menaut, dan backend mengarahkannya ke Lengkapi Profil yang menjelaskan alasannya (BR-IAM-03).
 */
export function quickActions(permissions: string[]): QuickAction[] {
    return definitions
        .filter((d) => d.permission === null || permissions.includes(d.permission))
        .map((d, index) => ({
            label: d.label,
            routeName: d.routeName,
            params: d.params,
            icon: d.icon,
            primary: index === 0,
        }))
}
