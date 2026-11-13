import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import DecisionSection from '@/Components/Submission/DecisionSection.vue'
import type { SubmissionDecision } from '@/types/submission-page'

const decision: SubmissionDecision = {
    outcome: 'rejected',
    reason: 'Mitra pelaksana belum memiliki perjanjian kerja sama.',
    decidedBy: 'Siti Aminah',
    decidedAt: '2026-03-12T09:00:00',
}

const render = (overrides: Partial<SubmissionDecision> = {}) =>
    mount(DecisionSection, { props: { decision: { ...decision, ...overrides } } })

describe('DecisionSection', () => {
    it('[REV-VIS-06] menampilkan tanggal dan pemutus penolakan', () => {
        const text = render().text()

        expect(text).toContain('Pengajuan ini ditolak Operator LPPM pada 12 Mar 2026.')
        expect(text).toContain('tidak pernah terjadi otomatis dari hasil penilaian dari penilai')
        expect(text).toContain('Siti Aminah')
        expect(text).toContain(decision.reason)
    })

    it('menampilkan tanda pisah bila pemutus tidak tercatat', () => {
        const wrapper = render({ decidedBy: null, decidedAt: null })

        expect(wrapper.text()).toContain('Pengajuan ini ditolak Operator LPPM.')
        expect(wrapper.findAll('dd').map((dd) => dd.text())).toEqual(['-', '-'])
    })

    it('tidak merender alasan sebagai HTML', () => {
        const wrapper = render({ reason: '<b>x</b>' })

        expect(wrapper.find('b').exists()).toBe(false)
        expect(wrapper.text()).toContain('<b>x</b>')
    })

    it('[REV-OUTCOME-11] persetujuan menampilkan pemutus tanpa alasan penolakan', () => {
        const text = render({ outcome: 'approved', reason: null }).text()

        expect(text).toContain('Pengajuan ini disetujui Operator LPPM pada 12 Mar 2026.')
        expect(text).toContain('Siti Aminah')
        expect(text).not.toContain('Alasan Penolakan')
        expect(text).not.toContain('ditolak')
    })

    it('menjelaskan bahwa pengajuan baru tetap boleh dibuat', () => {
        expect(render().text()).toContain(
            'Anda tetap dapat mengajukan proposal baru ke skema yang sama pada periode ini.'
        )
    })
})
