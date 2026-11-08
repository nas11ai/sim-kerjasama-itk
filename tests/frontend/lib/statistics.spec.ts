import { describe, expect, it } from 'vitest'

import { formatAge, formatShare } from '@/lib/statistics'

describe('formatShare', () => {
    it('membulatkan pangsa ke persen utuh', () => {
        expect(formatShare(0.384)).toBe('38%')
        expect(formatShare(1)).toBe('100%')
    })

    it('pangsa nol menjadi 0%', () => {
        expect(formatShare(0)).toBe('0%')
    })
})

describe('formatAge', () => {
    it('umur nol hari ditulis hari ini', () => {
        expect(formatAge(0)).toBe('hari ini')
    })

    it('umur lain ditulis dalam hari', () => {
        expect(formatAge(1)).toBe('1 hari')
        expect(formatAge(12)).toBe('12 hari')
    })
})
