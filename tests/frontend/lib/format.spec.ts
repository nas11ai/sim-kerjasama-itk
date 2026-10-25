import { describe, expect, it } from 'vitest'

import { formatDate, formatRupiah } from '@/lib/format'

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
