import { describe, expect, it } from 'vitest'

import { quickActions } from '@/lib/dashboardActions'

const RESEARCHER = ['submissions.create', 'submissions.view-own']
const REVIEWER = ['reviewers.evaluate']
const OPERATOR = ['submissions.view-all', 'reviewers.assign', 'users.verify']

const labels = (permissions: string[]) => quickActions(permissions).map((a) => a.label)

describe('quickActions (W45, §4.4)', () => {
    it('tanpa permission hanya "Notifikasi"', () => {
        expect(labels([])).toEqual(['Notifikasi'])
    })

    it('peneliti mendapat aksi frame RESEARCHER', () => {
        expect(labels(RESEARCHER)).toEqual([
            'Buat Pengajuan Baru',
            'Lihat Daftar Pengajuan',
            'Notifikasi',
        ])
    })

    it('user dua peran mendapat gabungan tanpa duplikat', () => {
        const result = labels([...RESEARCHER, ...REVIEWER, ...RESEARCHER])

        expect(result).toEqual([
            'Buat Pengajuan Baru',
            'Lihat Daftar Pengajuan',
            'Lihat Penugasan Review',
            'Riwayat Review',
            'Notifikasi',
        ])
        expect(new Set(result).size).toBe(result.length)
    })

    it('Tugaskan Reviewer menaut daftar yang difilter status submitted', () => {
        const assign = quickActions(OPERATOR).find((a) => a.label === 'Tugaskan Reviewer')

        expect(assign?.routeName).toBe('admin.submissions.index')
        expect(assign?.params).toEqual({ status: 'submitted' })
    })

    it('tepat satu primary: item pertama yang lolos', () => {
        for (const permissions of [[], RESEARCHER, REVIEWER, OPERATOR, ['schemes.manage']]) {
            const actions = quickActions(permissions)

            expect(actions.filter((a) => a.primary)).toHaveLength(1)
            expect(actions[0].primary).toBe(true)
        }
    })
})
