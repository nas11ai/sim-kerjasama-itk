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
    /** Diisi bila aksi tampil tapi tertahan gerbang backend; tombol dirender nonaktif. */
    disabledReason?: string
}

// BR-IAM-03 — gerbang profil menahan pembuatan pengajuan (state IAM-VERIFY-04).
const PROFILE_INCOMPLETE = 'Lengkapi profil Anda sebelum membuat pengajuan.'

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
        label: 'Lihat Penugasan Review',
        routeName: 'reviewer.assignments.index',
        icon: ClipboardList,
        permission: 'reviewers.evaluate',
    },
    {
        label: 'Riwayat Review',
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
        label: 'Tugaskan Reviewer',
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
        label: 'Form Builder',
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

/** Aksi cepat dasbor; satu daftar tetap, jadi user dua peran tidak mendapat duplikat. */
export function quickActions(permissions: string[], profileIncomplete = false): QuickAction[] {
    return definitions
        .filter((d) => d.permission === null || permissions.includes(d.permission))
        .map((d, index) => ({
            label: d.label,
            routeName: d.routeName,
            params: d.params,
            icon: d.icon,
            primary: index === 0,
            disabledReason:
                profileIncomplete && d.routeName === 'user.submissions.create'
                    ? PROFILE_INCOMPLETE
                    : undefined,
        }))
}
