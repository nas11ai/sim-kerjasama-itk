import type { NavigationFlags } from '@/types'
import {
    BookOpen,
    Building2,
    ChartBar,
    ClipboardList,
    Clock,
    Cog,
    Download,
    FileText,
    Flag,
    Folder,
    Layers,
    LayoutDashboard,
    Settings,
    SquarePen,
    SquarePlus,
    User,
    Users,
    type LucideIcon,
} from 'lucide-vue-next'

export interface NavItem {
    label: string
    icon: LucideIcon
    /** null = halamannya belum ada, item tersembunyi sampai irisan terkait mengisinya */
    routeName: string | null
    activePattern?: string
    /** kosong = semua pengguna; selain itu cukup memegang salah satu */
    anyPermission: string[]
    requiresApprovedSubmission?: true
}

export interface NavGroup {
    label: string | null
    items: NavItem[]
}

export const navGroups: NavGroup[] = [
    {
        label: null,
        items: [
            {
                label: 'Dashboard',
                icon: LayoutDashboard,
                routeName: 'dashboard',
                anyPermission: [],
            },
        ],
    },
    {
        label: 'Pengajuan',
        items: [
            {
                label: 'Daftar Pengajuan',
                icon: FileText,
                routeName: 'user.submissions.index',
                activePattern: 'user.submissions.*',
                anyPermission: ['submissions.view-own'],
            },
            {
                label: 'Buat Pengajuan Baru',
                icon: SquarePlus,
                routeName: null,
                anyPermission: ['submissions.create'],
            },
        ],
    },
    {
        label: 'Review',
        items: [
            {
                label: 'Penugasan Review',
                icon: ClipboardList,
                routeName: 'reviewer.assignments.index',
                anyPermission: ['reviewers.evaluate'],
            },
            {
                label: 'Riwayat Review',
                icon: BookOpen,
                routeName: null,
                anyPermission: ['reviewers.evaluate'],
            },
        ],
    },
    {
        label: 'Monev',
        items: [
            {
                label: 'Daftar Monev',
                icon: Flag,
                routeName: null,
                anyPermission: ['submissions.view-own'],
                requiresApprovedSubmission: true,
            },
            {
                label: 'Kelola Siklus Monev',
                icon: Layers,
                routeName: null,
                anyPermission: ['periods.manage'],
            },
        ],
    },
    {
        label: null,
        items: [
            {
                label: 'Luaran Penelitian',
                icon: Folder,
                routeName: null,
                anyPermission: ['submissions.view-own', 'outputs.manage'],
                requiresApprovedSubmission: true,
            },
        ],
    },
    {
        label: 'Manajemen',
        items: [
            {
                label: 'Semua Pengajuan',
                icon: FileText,
                routeName: 'admin.submissions.index',
                activePattern: 'admin.submissions.*',
                anyPermission: ['submissions.view-all'],
            },
            {
                label: 'Periode Pengajuan',
                icon: Clock,
                routeName: 'admin.submission-periods.index',
                activePattern: 'admin.submission-periods.*',
                anyPermission: ['periods.manage'],
            },
            {
                label: 'Reviewer',
                icon: Users,
                routeName: 'admin.reviewers.index',
                activePattern: 'admin.reviewers.*',
                anyPermission: ['reviewers.assign'],
            },
            {
                label: 'User & Verifikasi',
                icon: User,
                routeName: 'admin.users.index',
                activePattern: 'admin.users.*',
                anyPermission: ['users.verify', 'users.manage'],
            },
        ],
    },
    {
        label: 'Laporan',
        items: [
            {
                label: 'Statistik',
                icon: ChartBar,
                routeName: 'admin.dashboard',
                anyPermission: ['reporting.export', 'reporting.view-audit-log'],
            },
            {
                label: 'Export Data',
                icon: Download,
                routeName: 'exports.index',
                anyPermission: ['reporting.export'],
            },
            {
                label: 'Audit Log',
                icon: BookOpen,
                routeName: 'audit-log.index',
                anyPermission: ['reporting.view-audit-log'],
            },
        ],
    },
    {
        label: 'Konfigurasi',
        items: [
            {
                label: 'Form Builder',
                icon: SquarePen,
                routeName: 'admin.forms.index',
                activePattern: 'admin.forms.*',
                anyPermission: ['schemes.manage'],
            },
            {
                label: 'Skema Penelitian',
                icon: Settings,
                routeName: 'admin.schemes.index',
                anyPermission: ['schemes.manage'],
            },
            {
                label: 'Organisasi',
                icon: Building2,
                routeName: null,
                anyPermission: ['schemes.manage'],
            },
            {
                label: 'Konfigurasi Umum',
                icon: Cog,
                routeName: 'admin.reference.index',
                anyPermission: ['schemes.manage'],
            },
        ],
    },
]

/** Item yang lolos penyaringan selalu punya halaman tujuan. */
export type VisibleNavItem = NavItem & { routeName: string }

export interface VisibleNavGroup {
    label: string | null
    items: VisibleNavItem[]
}

function hasRoute(item: NavItem): item is VisibleNavItem {
    return item.routeName !== null
}

function isPermitted(item: NavItem, permissions: string[], flags: NavigationFlags): boolean {
    if (item.requiresApprovedSubmission && !flags.hasApprovedSubmission) return false
    if (item.anyPermission.length === 0) return true

    return item.anyPermission.some((permission) => permissions.includes(permission))
}

export function visibleNavGroups(
    groups: NavGroup[],
    permissions: string[],
    flags: NavigationFlags
): VisibleNavGroup[] {
    return groups
        .map((group) => ({
            label: group.label,
            items: group.items
                .filter(hasRoute)
                .filter((item) => isPermitted(item, permissions, flags)),
        }))
        .filter((group) => group.items.length > 0)
}

export function isNavItemActive(
    item: VisibleNavItem,
    current: (pattern: string) => boolean
): boolean {
    return current(item.activePattern ?? item.routeName)
}
