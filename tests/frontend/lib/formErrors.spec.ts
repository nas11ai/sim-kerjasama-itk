import { describe, expect, it } from 'vitest'

import { errorsUnder } from '@/lib/formErrors'

describe('errorsUnder', () => {
    const errors = {
        'fields.0.label': 'Label wajib diisi',
        'fields.0.options.1.value': 'Nilai kembar',
        'fields.10.label': 'Label terlalu panjang',
        title: 'Judul wajib diisi',
    }

    it('mengambil pesan baris berawalan itu saja, termasuk galat turunannya', () => {
        expect(errorsUnder(errors, 'fields.0.')).toEqual(['Label wajib diisi', 'Nilai kembar'])
    })

    it('titik penutup mencegah baris 1 menangkap baris 10', () => {
        expect(errorsUnder(errors, 'fields.1.')).toEqual([])
    })
})
