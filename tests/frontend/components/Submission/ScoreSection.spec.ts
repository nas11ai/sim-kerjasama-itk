import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import ScoreSection from '@/Components/Submission/ScoreSection.vue'
import type { ReviewerScores } from '@/types/submission-page'

const held: ReviewerScores = {
    visible: false,
    reason: 'Hasil review terbuka setelah keputusan akhir atas pengajuan ini diambil.',
    scores: [],
}

const opened: ReviewerScores = {
    visible: true,
    reason: null,
    scores: [
        {
            reviewer: 'Reviewer 1',
            reviewer_type: 'internal',
            submitted_at: '2026-03-01T08:00:00',
            replaced: false,
            answers: [{ label: 'Skor', value: '62' }],
        },
        {
            reviewer: 'Reviewer 2',
            reviewer_type: 'external',
            submitted_at: '2026-03-02T08:00:00',
            replaced: true,
            answers: [{ label: 'Skor', value: '58' }],
        },
    ],
}

describe('ScoreSection', () => {
    it('[REV-VIS-04] [RPT-PRINT-03] menampilkan alasan penahanan, tanpa skor', () => {
        const text = mount(ScoreSection, { props: { scores: held } }).text()

        expect(text).toContain(held.reason)
        expect(text).not.toContain('Hasil Evaluasi Reviewer')
    })

    it('[REV-VIS-05] menampilkan skor per reviewer', () => {
        const text = mount(ScoreSection, { props: { scores: opened } }).text()

        expect(text).toContain('Hasil Evaluasi Reviewer')
        expect(text).toContain('Reviewer 1')
        expect(text).toContain('62')
        expect(text).toContain('Reviewer 2')
        expect(text).toContain('58')
    })

    it('menyatakan belum ada evaluasi bila skor terbuka tapi kosong', () => {
        const text = mount(ScoreSection, { props: { scores: { ...opened, scores: [] } } }).text()

        expect(text).toContain('Belum ada evaluasi yang disubmit.')
    })

    it('[REV-REASSIGN-02] lembar reviewer yang digantikan berlabel', () => {
        const wrapper = mount(ScoreSection, { props: { scores: opened } })

        expect(wrapper.text().match(/Digantikan/g)).toHaveLength(1)
    })
})
