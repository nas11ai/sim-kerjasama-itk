import { describe, expect, it } from 'vitest'

import {
    isNavItemActive,
    navGroups,
    navItemCount,
    visibleNavGroups,
    type VisibleNavGroup,
} from '@/lib/navigation'

const RESEARCHER = [
    'submissions.create',
    'submissions.view-own',
    'budget.edit',
    'members.manage',
    'outputs.manage',
]
const REVIEWER_INTERNAL = [
    'reviewers.evaluate',
    'submissions.view-assigned',
    'reviewers.view-scores-others',
]
const FULL_CATALOG = [
    'submissions.create',
    'submissions.view-own',
    'submissions.view-all',
    'submissions.view-assigned',
    'budget.edit',
    'members.manage',
    'reviewers.assign',
    'reviewers.evaluate',
    'reviewers.view-scores-others',
    'periods.manage',
    'schemes.manage',
    'outputs.manage',
    'users.verify',
    'users.manage',
    'organizations.manage',
    'reporting.export',
    'reporting.view-audit-log',
    'announcements.manage',
]
const NO_FLAGS = {
    hasOutputSubmission: false,
    hasMonevSubmission: false,
    unreadAnnouncements: 0,
    unreadNotifications: 0,
}

const labels = (groups: VisibleNavGroup[]) => groups.flatMap((g) => g.items.map((i) => i.label))
const groupLabels = (groups: VisibleNavGroup[]) => groups.map((g) => g.label)

describe('visibleNavGroups', () => {
    it('peneliti hanya melihat Dashboard, Pengumuman, Notifikasi, dan Daftar Pengajuan', () => {
        expect(labels(visibleNavGroups(navGroups, RESEARCHER, NO_FLAGS))).toEqual([
            'Dashboard',
            'Pengumuman',
            'Notifikasi',
            'Daftar Pengajuan',
        ])
    })

    it('grup tanpa item yang tampil ikut hilang beserta labelnya', () => {
        const result = groupLabels(visibleNavGroups(navGroups, RESEARCHER, NO_FLAGS))

        expect(result).toEqual([null, 'Pengajuan'])
        expect(result).not.toContain('Review')
        expect(result).not.toContain('Manajemen')
    })

    it('peneliti + reviewer internal melihat gabungan Pengajuan dan Review', () => {
        const permissions = [...RESEARCHER, ...REVIEWER_INTERNAL]

        expect(labels(visibleNavGroups(navGroups, permissions, NO_FLAGS))).toEqual([
            'Dashboard',
            'Pengumuman',
            'Notifikasi',
            'Daftar Pengajuan',
            'Penugasan Review',
            'Riwayat Review',
        ])
    })

    it('User & Verifikasi tampil cukup dengan users.verify', () => {
        expect(labels(visibleNavGroups(navGroups, ['users.verify'], NO_FLAGS))).toContain(
            'User & Verifikasi'
        )
    })

    it('Organisasi tampil untuk organizations.manage, tidak untuk users.manage atau schemes.manage saja (W105)', () => {
        const granted = visibleNavGroups(navGroups, ['organizations.manage'], NO_FLAGS)
        const organisasi = granted.flatMap((g) => g.items).find((i) => i.label === 'Organisasi')

        expect(organisasi?.routeName).toBe('admin.organizations.index')
        for (const permission of ['users.manage', 'schemes.manage']) {
            expect(labels(visibleNavGroups(navGroups, [permission], NO_FLAGS))).not.toContain(
                'Organisasi'
            )
        }
    })

    it('item ber-routeName null tidak pernah tampil, termasuk untuk katalog penuh', () => {
        const hidden = navGroups.flatMap((g) => g.items).filter((i) => i.routeName === null)
        const shown = labels(
            visibleNavGroups(navGroups, FULL_CATALOG, {
                ...NO_FLAGS,
                hasOutputSubmission: true,
            })
        )

        expect(hidden.length).toBeGreaterThan(0)
        for (const item of hidden) {
            expect(shown).not.toContain(item.label)
        }
    })

    it('item requiresOutputSubmission butuh flag hasOutputSubmission', () => {
        const gated = {
            label: null,
            items: [
                {
                    label: 'Gated',
                    icon: navGroups[0].items[0].icon,
                    routeName: 'dashboard',
                    anyPermission: [],
                    requiresOutputSubmission: true as const,
                },
            ],
        }

        expect(labels(visibleNavGroups([gated], [], NO_FLAGS))).toEqual([])
        expect(
            labels(visibleNavGroups([gated], [], { ...NO_FLAGS, hasOutputSubmission: true }))
        ).toEqual(['Gated'])
    })

    it('Daftar Monev muncul hanya bila hasMonevSubmission (W128)', () => {
        const withMonev = { ...NO_FLAGS, hasMonevSubmission: true }

        expect(labels(visibleNavGroups(navGroups, RESEARCHER, NO_FLAGS))).not.toContain(
            'Daftar Monev'
        )
        expect(labels(visibleNavGroups(navGroups, RESEARCHER, withMonev))).toContain('Daftar Monev')
        expect(
            labels(
                visibleNavGroups(navGroups, RESEARCHER, {
                    ...NO_FLAGS,
                    hasOutputSubmission: true,
                })
            )
        ).not.toContain('Daftar Monev')
    })

    it('Luaran Penelitian muncul hanya bila hasOutputSubmission (W142)', () => {
        const withOutputs = { ...NO_FLAGS, hasOutputSubmission: true }

        expect(labels(visibleNavGroups(navGroups, RESEARCHER, NO_FLAGS))).not.toContain(
            'Luaran Penelitian'
        )
        expect(labels(visibleNavGroups(navGroups, RESEARCHER, withOutputs))).toContain(
            'Luaran Penelitian'
        )
    })

    it('Luaran Penelitian aktif di setiap halaman luaran', () => {
        const item = navGroups.flatMap((g) => g.items).find((i) => i.label === 'Luaran Penelitian')

        expect(item?.routeName).toBe('research-outputs.index')
        expect(item?.activePattern).toBe('research-outputs.*')
    })

    it('Daftar Monev aktif di halaman laporan monev', () => {
        const item = navGroups.flatMap((g) => g.items).find((i) => i.label === 'Daftar Monev')

        expect(item?.routeName).toBe('monev.index')
        expect(item?.activePattern).toBe('monev.reports.*')

        if (item === undefined) throw new Error('Daftar Monev hilang dari navigasi')
        const visible = { ...item, routeName: 'monev.index' }
        const on = (name: string) => (pattern: string) =>
            pattern === name || (pattern.endsWith('.*') && name.startsWith(pattern.slice(0, -1)))

        expect(isNavItemActive(visible, on('monev.index'))).toBe(true)
        expect(isNavItemActive(visible, on('monev.reports.edit'))).toBe(true)
        expect(isNavItemActive(visible, on('monev.manage.index'))).toBe(false)
    })

    it('Kelola Siklus Monev untuk submissions.view-all, tidak untuk periods.manage saja (W129)', () => {
        const item = navGroups
            .flatMap((g) => g.items)
            .find((i) => i.label === 'Kelola Siklus Monev')

        expect(item?.routeName).toBe('monev.manage.index')
        expect(item?.activePattern).toBe('monev.manage.*')
        expect(labels(visibleNavGroups(navGroups, ['submissions.view-all'], NO_FLAGS))).toContain(
            'Kelola Siklus Monev'
        )
        expect(labels(visibleNavGroups(navGroups, ['periods.manage'], NO_FLAGS))).not.toContain(
            'Kelola Siklus Monev'
        )
    })
})

describe('pengumuman (fe-v2-design §4.7, W6)', () => {
    it('Kelola Pengumuman hanya untuk announcements.manage', () => {
        expect(labels(visibleNavGroups(navGroups, RESEARCHER, NO_FLAGS))).not.toContain(
            'Kelola Pengumuman'
        )
        const manage = labels(visibleNavGroups(navGroups, ['announcements.manage'], NO_FLAGS))
        expect(manage).toContain('Kelola Pengumuman')
    })

    it('Pengumuman tampil untuk semua pengguna login', () => {
        expect(labels(visibleNavGroups(navGroups, [], NO_FLAGS))).toEqual([
            'Dashboard',
            'Pengumuman',
            'Notifikasi',
        ])
    })

    it('badge hanya tampil bila belum dibaca > 0', () => {
        const item = visibleNavGroups(navGroups, [], NO_FLAGS)
            .flatMap((g) => g.items)
            .find((i) => i.label === 'Pengumuman')

        expect(item).toBeDefined()
        expect(navItemCount(item!, NO_FLAGS)).toBeNull()
        expect(navItemCount(item!, { ...NO_FLAGS, unreadAnnouncements: 3 })).toBe(3)
        expect(
            navItemCount(visibleNavGroups(navGroups, [], NO_FLAGS)[0].items[0], {
                ...NO_FLAGS,
                unreadAnnouncements: 3,
            })
        ).toBeNull()
    })
})

describe('isNavItemActive', () => {
    const visible = visibleNavGroups(navGroups, FULL_CATALOG, NO_FLAGS).flatMap((g) => g.items)
    const item = visible.find((i) => i.label === 'Daftar Pengajuan')

    it('isNavItemActive memakai activePattern untuk halaman detail', () => {
        expect(item).toBeDefined()
        expect(isNavItemActive(item!, (p) => p === 'user.submissions.*')).toBe(true)
    })

    it('Penugasan Review aktif di halaman evaluasi', () => {
        const review = visible.find((i) => i.label === 'Penugasan Review')

        expect(review).toBeDefined()
        expect(isNavItemActive(review!, (p) => p === 'reviewer.assignments.*')).toBe(true)
    })

    it('Riwayat Review menaut ke halaman riwayat', () => {
        const history = visible.find((i) => i.label === 'Riwayat Review')

        expect(history?.routeName).toBe('reviewer.history.index')
    })

    it('memakai routeName bila tidak ada activePattern', () => {
        const dashboard = visible[0]

        expect(isNavItemActive(dashboard, (p) => p === 'dashboard')).toBe(true)
        expect(isNavItemActive(dashboard, () => false)).toBe(false)
    })
})

describe('notifikasi (W38, BR-NOT-05)', () => {
    const notifItem = () =>
        visibleNavGroups(navGroups, [], NO_FLAGS)
            .flatMap((g) => g.items)
            .find((i) => i.label === 'Notifikasi')

    it('Notifikasi terlihat untuk semua pengguna dan menaut ke daftar notifikasi', () => {
        expect(notifItem()?.routeName).toBe('notifications.index')
    })

    it('badge tampil saat unreadNotifications > 0', () => {
        const item = notifItem()

        expect(item).toBeDefined()
        expect(navItemCount(item!, NO_FLAGS)).toBeNull()
        expect(navItemCount(item!, { ...NO_FLAGS, unreadNotifications: 4 })).toBe(4)
        expect(navItemCount(item!, { ...NO_FLAGS, unreadAnnouncements: 4 })).toBeNull()
    })
})
