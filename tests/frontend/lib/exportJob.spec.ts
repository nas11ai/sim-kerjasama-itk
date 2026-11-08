import { describe, expect, it } from 'vitest'

import { hoursLeft, jobState } from '@/lib/exportJob'

import { exportJob } from '../fixtures/exportJob'

const now = new Date('2026-07-03T09:00:00+08:00')

describe('jobState', () => {
    it('antrean dan proses mengikuti status mentah, meski berkasnya belum ada', () => {
        const pending = {
            file_is_gone: true,
            download_url: null,
            completed_at: null,
            file_expires_at: null,
        }

        expect(jobState(exportJob({ status: 'queued', ...pending }), now)).toBe('queued')
        expect(jobState(exportJob({ status: 'processing', ...pending }), now)).toBe('processing')
    })

    it('gagal menang atas segalanya', () => {
        expect(
            jobState(exportJob({ status: 'failed', file_is_gone: true, download_url: null }), now)
        ).toBe('failed')
    })

    it('[RPT-EXPORT-11] berkas 23 jam masih siap diunduh', () => {
        expect(jobState(exportJob(), now)).toBe('ready')
    })

    it('[RPT-EXPORT-10] berkas terhapus menang atas tautan', () => {
        expect(jobState(exportJob({ file_is_gone: true }), now)).toBe('gone')
        // Lewat masa simpan tetapi penjadwal belum menghapus: unduhan 410, jadi sama dengan terhapus.
        expect(jobState(exportJob(), new Date('2026-07-03T10:00:00+08:00'))).toBe('gone')
    })

    it('[RPT-EXPORT-04] tautan lewat masa berlaku menjadi linkExpired', () => {
        const job = exportJob({ link_expires_at: '2026-07-03T08:59:00+08:00' })

        expect(jobState(job, now)).toBe('linkExpired')
    })
})

describe('hoursLeft', () => {
    it('sisa jam penuh hingga masa simpan habis', () => {
        expect(hoursLeft(exportJob({ file_expires_at: '2026-07-03T12:00:00+08:00' }), now)).toBe(3)
        expect(hoursLeft(exportJob({ file_expires_at: '2026-07-03T11:59:00+08:00' }), now)).toBe(2)
        expect(hoursLeft(exportJob({ file_expires_at: null }), now)).toBe(0)
    })
})
