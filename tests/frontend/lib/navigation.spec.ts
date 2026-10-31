import { describe, expect, it } from 'vitest'

import {
    isNavItemActive,
    navGroups,
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
    'reporting.export',
    'reporting.view-audit-log',
]
const NO_APPROVED = { hasApprovedSubmission: false }

const labels = (groups: VisibleNavGroup[]) => groups.flatMap((g) => g.items.map((i) => i.label))
const groupLabels = (groups: VisibleNavGroup[]) => groups.map((g) => g.label)

describe('visibleNavGroups', () => {
    it('peneliti hanya melihat Dashboard dan Daftar Pengajuan', () => {
        expect(labels(visibleNavGroups(navGroups, RESEARCHER, NO_APPROVED))).toEqual([
            'Dashboard',
            'Daftar Pengajuan',
        ])
    })

    it('grup tanpa item yang tampil ikut hilang beserta labelnya', () => {
        const result = groupLabels(visibleNavGroups(navGroups, RESEARCHER, NO_APPROVED))

        expect(result).toEqual([null, 'Pengajuan'])
        expect(result).not.toContain('Review')
        expect(result).not.toContain('Manajemen')
    })

    it('peneliti + reviewer internal melihat gabungan Pengajuan dan Review', () => {
        const permissions = [...RESEARCHER, ...REVIEWER_INTERNAL]

        expect(labels(visibleNavGroups(navGroups, permissions, NO_APPROVED))).toEqual([
            'Dashboard',
            'Daftar Pengajuan',
            'Penugasan Review',
        ])
    })

    it('User & Verifikasi tampil cukup dengan users.verify', () => {
        expect(labels(visibleNavGroups(navGroups, ['users.verify'], NO_APPROVED))).toContain(
            'User & Verifikasi'
        )
    })

    it('item ber-routeName null tidak pernah tampil, termasuk untuk katalog penuh', () => {
        const hidden = navGroups.flatMap((g) => g.items).filter((i) => i.routeName === null)
        const shown = labels(
            visibleNavGroups(navGroups, FULL_CATALOG, { hasApprovedSubmission: true })
        )

        expect(hidden.length).toBeGreaterThan(0)
        for (const item of hidden) {
            expect(shown).not.toContain(item.label)
        }
    })

    it('item requiresApprovedSubmission butuh flag hasApprovedSubmission', () => {
        const gated = {
            label: null,
            items: [
                {
                    label: 'Gated',
                    icon: navGroups[0].items[0].icon,
                    routeName: 'dashboard',
                    anyPermission: [],
                    requiresApprovedSubmission: true as const,
                },
            ],
        }

        expect(labels(visibleNavGroups([gated], [], NO_APPROVED))).toEqual([])
        expect(labels(visibleNavGroups([gated], [], { hasApprovedSubmission: true }))).toEqual([
            'Gated',
        ])
    })
})

describe('isNavItemActive', () => {
    const visible = visibleNavGroups(navGroups, FULL_CATALOG, NO_APPROVED).flatMap((g) => g.items)
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

    it('memakai routeName bila tidak ada activePattern', () => {
        const dashboard = visible[0]

        expect(isNavItemActive(dashboard, (p) => p === 'dashboard')).toBe(true)
        expect(isNavItemActive(dashboard, () => false)).toBe(false)
    })
})
