import { describe, expect, it } from 'vitest'

import { navGroups, visibleNavGroups } from '@/lib/navigation'
import { normalize, paletteEntries, searchPalette } from '@/lib/paletteEntries'
import type { NavigationFlags } from '@/types'

const FLAGS: NavigationFlags = {
    hasOutputSubmission: false,
    hasMonevSubmission: false,
    unreadAnnouncements: 0,
    unreadNotifications: 0,
}
const ADMIN = [
    'submissions.view-all',
    'periods.manage',
    'announcements.manage',
    'reviewers.assign',
    'users.verify',
    'users.manage',
    'reporting.export',
    'reporting.view-audit-log',
    'schemes.manage',
    'organizations.manage',
]
const RESEARCHER = ['submissions.create', 'submissions.view-own', 'budget.edit', 'members.manage']

const entriesFor = (permissions: string[], flags = FLAGS) =>
    paletteEntries(visibleNavGroups(navGroups, permissions, flags), permissions, flags)
const labels = (permissions: string[], term: string) =>
    searchPalette(entriesFor(permissions), term).map((entry) => entry.label)

describe('paletteEntries / searchPalette (K10)', () => {
    it('kueri kosong memberi semua halaman sidebar tanpa fitur', () => {
        const result = searchPalette(entriesFor(ADMIN), '  ')

        expect(result.length).toBeGreaterThan(0)
        expect(result.every((entry) => entry.kind === 'page')).toBe(true)
    })

    it('menemukan fitur lewat sebagian kata: "sandi" → Ubah Kata Sandi', () => {
        expect(labels(RESEARCHER, 'sandi')).toContain('Ubah Kata Sandi')
    })

    it('istilah Inggris lama tetap ketemu lewat sinonim: "password", "form", "user"', () => {
        expect(labels(RESEARCHER, 'password')).toContain('Ubah Kata Sandi')
        expect(labels(ADMIN, 'form')).toEqual(expect.arrayContaining(['Buat Formulir']))
        expect(
            searchPalette(entriesFor(ADMIN), 'form').some(
                (e) => e.routeName === 'admin.forms.index'
            )
        ).toBe(true)
        expect(
            searchPalette(entriesFor(ADMIN), 'user').some(
                (e) => e.routeName === 'admin.users.index'
            )
        ).toBe(true)
    })

    it('setiap token harus cocok, urutan bebas: "unduh excel" → halaman ekspor', () => {
        const result = searchPalette(entriesFor(ADMIN), 'unduh excel')

        expect(result[0]?.routeName).toBe('reporting.exports.index')
        expect(searchPalette(entriesFor(ADMIN), 'zzz qqq')).toEqual([])
    })

    it('label yang diawali kueri mendahului cocok lewat kata kunci', () => {
        const result = searchPalette(entriesFor(ADMIN), 'buat')

        expect(result[0]?.label.startsWith('Buat')).toBe(true)
    })

    it('tidak peka huruf besar dan diakritik', () => {
        expect(normalize('  ÉKSPOR   Data ')).toBe('ekspor data')
        expect(labels(ADMIN, 'VERIFIKASI nidn')).toContain('Verifikasi NIDN')
    })

    it('peneliti tidak pernah melihat entri pemegang schemes.manage', () => {
        const routes = entriesFor(RESEARCHER).map((entry) => entry.routeName)

        expect(routes).not.toContain('admin.forms.create')
        expect(routes).not.toContain('admin.forms.index')
        expect(routes).not.toContain('admin.schemes.create')
    })

    it('Catat Luaran hanya bila user punya pengajuan berluaran', () => {
        const permissions = [...RESEARCHER, 'outputs.manage']

        expect(labels(permissions, 'luaran')).not.toContain('Catat Luaran')
        expect(
            searchPalette(
                entriesFor(permissions, { ...FLAGS, hasOutputSubmission: true }),
                'luaran'
            ).map((entry) => entry.label)
        ).toContain('Catat Luaran')
    })

    it('fitur statistik membawa tab-nya, halaman sidebar muncul sekali per route', () => {
        const entries = entriesFor(ADMIN)
        const now = entries.find((entry) => entry.label === 'Statistik Saat Ini')
        const pageRoutes = entries
            .filter((entry) => entry.kind === 'page')
            .map((entry) => entry.routeName)

        expect(now?.routeParams).toEqual({ tab: 'now' })
        expect(new Set(pageRoutes).size).toBe(pageRoutes.length)
    })

    it('hasil dibatasi', () => {
        expect(searchPalette(entriesFor(ADMIN), 'a', 5)).toHaveLength(5)
    })
})
