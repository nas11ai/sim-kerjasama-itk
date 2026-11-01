import { describe, expect, it } from 'vitest'

import { formatDate, formatRelative, formatRupiah } from '@/lib/format'

// Intl memakai spasi tak-putus; disamakan supaya assertion terbaca.
const plain = (value: string) => value.replace(/\s/g, ' ')

describe('formatDate', () => {
    it('menulis tanggal pendek berbahasa Indonesia', () => {
        expect(formatDate('2026-01-12T08:00:00')).toBe('12 Jan 2026')
    })

    it('menulis tanda pisah untuk tanggal kosong', () => {
        expect(formatDate(null)).toBe('—')
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
