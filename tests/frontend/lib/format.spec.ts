import { describe, expect, it } from 'vitest'

import {
    formatDate,
    formatDateTime,
    formatFileSize,
    formatPeriodName,
    formatRelative,
    formatRupiah,
} from '@/lib/format'

// Intl memakai spasi tak-putus; disamakan supaya assertion terbaca.
const plain = (value: string) => value.replace(/\s/g, ' ')

describe('formatDate', () => {
    it('menulis tanggal pendek berbahasa Indonesia', () => {
        expect(formatDate('2026-01-12T08:00:00')).toBe('12 Jan 2026')
    })

    it('menulis tanda pisah untuk tanggal kosong', () => {
        expect(formatDate(null)).toBe('-')
    })
})

describe('formatDateTime', () => {
    it('menulis tanggal dan jam di zona aplikasi (WITA), apa pun zona peramban', () => {
        expect(plain(formatDateTime('2026-02-15T23:59:00+08:00'))).toBe('15 Feb 2026 23.59')
        expect(plain(formatDateTime('2026-02-15T15:59:00Z'))).toBe('15 Feb 2026 23.59')
    })
})

describe('formatRupiah', () => {
    it('menulis rupiah tanpa desimal', () => {
        expect(plain(formatRupiah(48500000))).toBe('Rp 48.500.000')
    })
})

describe('formatRelative', () => {
    const now = new Date('2026-07-03T12:00:00')

    it('menulis jarak waktu ala wireframe', () => {
        expect(formatRelative('2026-07-03T11:59:30', now)).toBe('Baru saja')
        expect(formatRelative('2026-07-03T11:15:00', now)).toBe('45 menit lalu')
        expect(formatRelative('2026-07-03T10:00:00', now)).toBe('2 jam lalu')
        expect(formatRelative('2026-07-02T09:00:00', now)).toBe('1 hari lalu')
        expect(formatRelative('2026-06-26T12:00:00', now)).toBe('1 minggu lalu')
    })

    it('lewat empat minggu kembali ke tanggal', () => {
        expect(formatRelative('2026-05-01T12:00:00', now)).toBe('01 Mei 2026')
    })
})

describe('formatFileSize', () => {
    it('menulis ukuran berkas ala wireframe', () => {
        expect(formatFileSize(245760)).toBe('240 KB')
        expect(formatFileSize(1153434)).toBe('1,1 MB')
        expect(formatFileSize(512)).toBe('512 B')
    })
})

describe('formatPeriodName', () => {
    it('memberi awalan Periode pada nama tanpa awalan', () => {
        expect(formatPeriodName('2026 Gelombang 2')).toBe('Periode 2026 Gelombang 2')
    })

    it('tidak menggandakan awalan yang sudah ditulis operator', () => {
        expect(formatPeriodName('Periode 2026')).toBe('Periode 2026')
        expect(formatPeriodName('periode ganjil')).toBe('periode ganjil')
    })
})
