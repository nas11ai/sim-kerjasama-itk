import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import ReviewersSection from '@/Components/Submission/Manage/ReviewersSection.vue'
import type { ManagedReviewer } from '@/types/submission-manage'

const reviewer = (overrides: Partial<ManagedReviewer> = {}): ManagedReviewer => ({
    reviewerId: 3,
    name: 'Dr. Sari Melati',
    nidn: '0011223344',
    type: 'internal',
    replaced: false,
    evaluationLabel: 'Evaluasi berjalan',
    accountActive: true,
    ...overrides,
})

const mountSection = (reviewers: ManagedReviewer[]) =>
    mount(ReviewersSection, {
        props: { reviewers, activeCount: reviewers.length, minimum: 2, canReplace: true },
    })

describe('ReviewersSection', () => {
    it('[IAM-DEACT-11] reviewer berakun nonaktif ditandai perlu diganti, tombol Ganti tetap ada', () => {
        const wrapper = mountSection([reviewer({ accountActive: false })])

        expect(wrapper.text()).toContain('Akun nonaktif — perlu diganti')
        expect(wrapper.findAll('button').some((b) => b.text() === 'Ganti')).toBe(true)
    })

    it('reviewer aktif tanpa penanda', () => {
        expect(mountSection([reviewer()]).text()).not.toContain('Akun nonaktif')
    })

    it('reviewer yang sudah digantikan tidak ditandai lagi', () => {
        const text = mountSection([reviewer({ accountActive: false, replaced: true })]).text()

        expect(text).toContain('Digantikan')
        expect(text).not.toContain('Akun nonaktif')
    })
})
