import {
    ChartBar,
    ClipboardCheck,
    FilePlus,
    KeyRound,
    Layers,
    Megaphone,
    Network,
    SquarePen,
    SquarePlus,
    UserCheck,
    UserCog,
    UserPlus,
    Users,
    Workflow,
    type LucideIcon,
} from 'lucide-vue-next'

import type { VisibleNavGroup } from '@/lib/navigation'
import type { NavigationFlags } from '@/types'

/**
 * K10 (pemilik 2026-11-13) — palette topbar mencari halaman dan fitur seperti command palette pada
 * umumnya: instan di klien, cocok sebagian kata dan sinonim, menyebut letaknya di menu.
 */
export interface PaletteEntry {
    kind: 'page' | 'feature'
    label: string
    icon: LucideIcon
    routeName: string
    routeParams?: Record<string, string>
    /** letak di menu, tampil redup di samping label; null = menu teratas */
    location: string | null
    keywords: string[]
}

interface FeatureDefinition extends Omit<PaletteEntry, 'kind'> {
    /** kosong = semua pengguna; selain itu cukup memegang salah satu */
    anyPermission: string[]
    requiresOutputSubmission?: true
}

// Sinonim halaman sidebar, termasuk istilah lama berbahasa Inggris yang masih diketik orang.
const PAGE_KEYWORDS: Record<string, string[]> = {
    dashboard: ['beranda', 'dashboard', 'home', 'halaman utama', 'ringkasan'],
    'user.announcements.index': ['pengumuman', 'announcement', 'berita', 'info'],
    'notifications.index': ['notifikasi', 'notification', 'pemberitahuan', 'kotak masuk'],
    'user.submissions.index': ['pengajuan saya', 'submission', 'proposal', 'usulan'],
    'reviewer.assignments.index': ['penugasan', 'review', 'reviewer', 'penilaian', 'tugas menilai'],
    'reviewer.history.index': ['riwayat', 'review', 'history', 'penilaian selesai'],
    'monev.index': ['monev', 'monitoring', 'evaluasi', 'laporan kemajuan', 'laporan akhir'],
    'monev.manage.index': ['monev', 'siklus', 'monitoring', 'kelola monev'],
    'research-outputs.index': ['luaran', 'output', 'publikasi', 'paten', 'hki'],
    'admin.submissions.index': ['semua pengajuan', 'submission', 'proposal', 'kelola pengajuan'],
    'admin.submission-periods.index': ['periode', 'jadwal', 'tenggat', 'deadline', 'gelombang'],
    'admin.announcements.index': ['pengumuman', 'announcement', 'kelola pengumuman'],
    'admin.reviewers.index': ['penilai', 'reviewer', 'daftar penilai'],
    'admin.users.index': ['pengguna', 'user', 'akun', 'dosen', 'verifikasi', 'peran', 'role'],
    'reporting.stats': ['statistik', 'statistics', 'grafik', 'rekap', 'dashboard laporan'],
    'reporting.exports.index': ['ekspor', 'export', 'unduh', 'excel', 'csv', 'download'],
    'reporting.audit.index': ['log audit', 'audit log', 'jejak', 'riwayat perubahan', 'aktivitas'],
    'admin.forms.index': ['formulir', 'form', 'form builder', 'pembuat formulir', 'isian'],
    'admin.evaluation-forms.index': ['formulir evaluasi', 'form evaluasi', 'rubrik', 'penilaian'],
    'admin.phases.index': ['tahap', 'fase', 'phase', 'alur', 'tahapan'],
    'admin.schemes.index': ['skema', 'scheme', 'hibah', 'pendanaan'],
    'admin.organizations.index': [
        'organisasi',
        'fakultas',
        'jurusan',
        'prodi',
        'program studi',
        'unit',
    ],
    'admin.reference.index': [
        'konfigurasi',
        'data acuan',
        'referensi',
        'tkt',
        'trl',
        'pengaturan',
        'settings',
    ],
}

const SCHEMES = ['schemes.manage']

export const FEATURES: FeatureDefinition[] = [
    {
        label: 'Buat Pengajuan Baru',
        icon: SquarePlus,
        routeName: 'user.submissions.create',
        location: 'Pengajuan',
        keywords: ['ajukan', 'proposal baru', 'submission baru', 'usulan baru'],
        anyPermission: ['submissions.create'],
    },
    {
        label: 'Ubah Profil dan Foto',
        icon: UserCog,
        routeName: 'profile.edit',
        location: 'Akun',
        keywords: ['profil', 'profile', 'foto', 'avatar', 'nama', 'telepon', 'bidang keahlian'],
        anyPermission: [],
    },
    {
        label: 'Ubah Kata Sandi',
        icon: KeyRound,
        routeName: 'profile.edit',
        location: 'Akun',
        keywords: ['kata sandi', 'password', 'sandi', 'ganti sandi', 'keamanan'],
        anyPermission: [],
    },
    {
        label: 'Buat Pengumuman',
        icon: Megaphone,
        routeName: 'admin.announcements.create',
        location: 'Manajemen',
        keywords: ['pengumuman baru', 'announcement', 'umumkan', 'terbitkan'],
        anyPermission: ['announcements.manage'],
    },
    {
        label: 'Buat Periode Pengajuan',
        icon: SquarePen,
        routeName: 'admin.submission-periods.index',
        location: 'Manajemen',
        keywords: ['periode baru', 'buka pengajuan', 'jadwal', 'tenggat', 'deadline'],
        anyPermission: ['periods.manage'],
    },
    {
        label: 'Undang Pengguna',
        icon: UserPlus,
        routeName: 'admin.invitations.index',
        location: 'Manajemen',
        keywords: ['undangan', 'invite', 'invitation', 'mitra', 'eksternal', 'token'],
        anyPermission: ['users.verify', 'users.manage'],
    },
    {
        label: 'Verifikasi NIDN',
        icon: UserCheck,
        routeName: 'admin.verifications.index',
        location: 'Manajemen',
        keywords: ['verifikasi', 'nidn', 'pendaftar', 'antrian', 'setujui akun'],
        anyPermission: ['users.verify'],
    },
    {
        label: 'Tunjuk Penilai',
        icon: Users,
        routeName: 'admin.reviewers.index',
        location: 'Manajemen',
        keywords: ['reviewer', 'penilai', 'angkat penilai', 'tambah penilai'],
        anyPermission: ['reviewers.assign'],
    },
    {
        label: 'Buat Formulir',
        icon: FilePlus,
        routeName: 'admin.forms.create',
        location: 'Konfigurasi',
        keywords: ['form', 'formulir baru', 'form builder', 'pembuat formulir'],
        anyPermission: SCHEMES,
    },
    {
        label: 'Buat Formulir Evaluasi',
        icon: ClipboardCheck,
        routeName: 'admin.evaluation-forms.create',
        location: 'Konfigurasi',
        keywords: ['form evaluasi', 'rubrik', 'penilaian', 'reviewer'],
        anyPermission: SCHEMES,
    },
    {
        label: 'Buat Tahap',
        icon: Workflow,
        routeName: 'admin.phases.create',
        location: 'Konfigurasi',
        keywords: ['fase', 'phase', 'tahapan', 'alur baru'],
        anyPermission: SCHEMES,
    },
    {
        label: 'Buat Skema',
        icon: Layers,
        routeName: 'admin.schemes.create',
        location: 'Konfigurasi',
        keywords: ['skema baru', 'scheme', 'hibah'],
        anyPermission: SCHEMES,
    },
    {
        label: 'Tambah Unit Organisasi',
        icon: Network,
        routeName: 'admin.organizations.index',
        location: 'Konfigurasi',
        keywords: ['fakultas', 'jurusan', 'prodi', 'program studi', 'unit baru'],
        anyPermission: ['organizations.manage'],
    },
    ...(
        [
            ['now', 'Statistik Saat Ini', ['sekarang', 'berjalan']],
            ['history', 'Riwayat Statistik', ['tahun lalu', 'tren', 'history']],
            [
                'follow-up',
                'Statistik Tindak Lanjut',
                ['tindak lanjut', 'perlu ditangani', 'follow up'],
            ],
        ] as const
    ).map(
        ([tab, label, keywords]): FeatureDefinition => ({
            label,
            icon: ChartBar,
            routeName: 'reporting.stats',
            routeParams: { tab },
            location: 'Laporan',
            keywords: ['statistik', 'rekap', ...keywords],
            anyPermission: ['reporting.export', 'reporting.view-audit-log'],
        })
    ),
    {
        label: 'Catat Luaran',
        icon: FilePlus,
        routeName: 'research-outputs.create',
        location: 'Luaran Penelitian',
        keywords: ['luaran', 'output', 'publikasi', 'paten', 'jurnal', 'hki'],
        anyPermission: ['outputs.manage'],
        requiresOutputSubmission: true,
    },
]

function isPermitted(feature: FeatureDefinition, permissions: string[], flags: NavigationFlags) {
    if (feature.requiresOutputSubmission && !flags.hasOutputSubmission) return false
    if (feature.anyPermission.length === 0) return true

    return feature.anyPermission.some((permission) => permissions.includes(permission))
}

/** Halaman = item sidebar yang lolos izin (sekali per route); fitur = FEATURES yang lolos izin. */
export function paletteEntries(
    groups: VisibleNavGroup[],
    permissions: string[],
    flags: NavigationFlags
): PaletteEntry[] {
    const seen = new Set<string>()
    const pages: PaletteEntry[] = []

    for (const group of groups) {
        for (const item of group.items) {
            if (seen.has(item.routeName)) continue
            seen.add(item.routeName)
            pages.push({
                kind: 'page',
                label: item.label,
                icon: item.icon,
                routeName: item.routeName,
                location: group.label,
                keywords: PAGE_KEYWORDS[item.routeName] ?? [],
            })
        }
    }

    const features = FEATURES.filter((feature) => isPermitted(feature, permissions, flags)).map(
        (feature): PaletteEntry => ({
            kind: 'feature',
            label: feature.label,
            icon: feature.icon,
            routeName: feature.routeName,
            routeParams: feature.routeParams,
            location: feature.location,
            keywords: feature.keywords,
        })
    )

    return [...pages, ...features]
}

/** Huruf kecil, tanpa diakritik, spasi dirapatkan. */
export function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLocaleLowerCase('id')
        .replace(/\s+/g, ' ')
        .trim()
}

// 0 label diawali kueri; 1 tiap token mengawali kata di label; 2 tiap token ada di label; 3 lewat letak/kata kunci.
function rank(entry: PaletteEntry, query: string, tokens: string[]): number | null {
    const label = normalize(entry.label)
    const haystack = normalize([entry.label, entry.location ?? '', ...entry.keywords].join(' '))

    if (!tokens.every((token) => haystack.includes(token))) return null
    if (label.startsWith(query)) return 0

    const words = label.split(' ')
    if (tokens.every((token) => words.some((word) => word.startsWith(token)))) return 1
    if (tokens.every((token) => label.includes(token))) return 2

    return 3
}

/**
 * Kueri kosong = semua halaman (tanpa fitur), urutan menu. Selain itu entri yang setiap tokennya cocok,
 * urut peringkat lalu urutan asli, paling banyak `limit`.
 */
export function searchPalette(entries: PaletteEntry[], term: string, limit = 8): PaletteEntry[] {
    const query = normalize(term)
    if (query === '') return entries.filter((entry) => entry.kind === 'page')

    const tokens = query.split(' ')

    return entries
        .map((entry, index) => ({ entry, index, rank: rank(entry, query, tokens) }))
        .filter(
            (scored): scored is { entry: PaletteEntry; index: number; rank: number } =>
                scored.rank !== null
        )
        .sort((a, b) => a.rank - b.rank || a.index - b.index)
        .slice(0, limit)
        .map((scored) => scored.entry)
}
