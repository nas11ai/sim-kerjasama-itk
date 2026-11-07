import type { NavigationFlags } from '@/types'
import {
    BookOpen,
    Bell,
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
    Megaphone,
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
    /** W128 — Daftar Monev: induk APPROVED, atau ditarik dengan laporan */
    requiresMonevSubmission?: true
    /** flag `navigation` berisi jumlah yang ditampilkan sebagai badge bila > 0 */
    countKey?: 'unreadAnnouncements' | 'unreadNotifications'
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
            {
                label: 'Pengumuman',
                icon: Megaphone,
                routeName: 'user.announcements.index',
                anyPermission: [],
                countKey: 'unreadAnnouncements',
            },
            // W38 — sidebar adalah navbar app ini; badge belum dibaca BR-NOT-05.
            {
                label: 'Notifikasi',
                icon: Bell,
                routeName: 'notifications.index',
                anyPermission: [],
                countKey: 'unreadNotifications',
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
                activePattern: 'reviewer.assignments.*',
                anyPermission: ['reviewers.evaluate'],
            },
            {
                label: 'Riwayat Review',
                icon: BookOpen,
                routeName: 'reviewer.history.index',
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
                routeName: 'monev.index',
                activePattern: 'monev.reports.*',
                anyPermission: ['submissions.view-own'],
                requiresMonevSubmission: true,
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
                label: 'Kelola Pengumuman',
                icon: Megaphone,
                routeName: 'admin.announcements.index',
                activePattern: 'admin.announcements.*',
                anyPermission: ['announcements.manage'],
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
                // R1 menggantinya dengan Reporting/StatsPage; sampai itu, halaman statistik pertama.
                routeName: 'admin.stats.form-submission',
                activePattern: 'admin.stats.*',
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
                routeName: 'admin.organizations.index',
                // W105 — keputusan pemilik 2026-11-07: Admin dan Operator.
                anyPermission: ['organizations.manage'],
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
    if (item.requiresMonevSubmission && !flags.hasMonevSubmission) return false
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
    // Pola tambahan tidak menggantikan halaman item itu sendiri (Daftar Monev + laporannya).
    return (
        current(item.routeName) || (item.activePattern !== undefined && current(item.activePattern))
    )
}

/** Jumlah untuk badge item, atau null bila item tak berhitungan atau hitungannya nol. */
export function navItemCount(item: NavItem, flags: NavigationFlags): number | null {
    if (item.countKey === undefined) return null
    const count = flags[item.countKey]

    return count > 0 ? count : null
}
