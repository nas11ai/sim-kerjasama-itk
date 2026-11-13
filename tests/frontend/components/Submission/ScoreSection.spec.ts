import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import ScoreSection from '@/Components/Submission/ScoreSection.vue'
import type { ReviewerScore, ReviewerScores } from '@/types/submission-page'

const held: ReviewerScores = {
    visible: false,
    reason: 'Hasil penilaian terbuka setelah keputusan akhir atas pengajuan ini diambil.',
    scores: [],
}

const first: ReviewerScore = {
    reviewer: 'Penilai 1',
    reviewer_type: 'internal',
    submitted_at: '2026-03-01T08:00:00',
    replaced: false,
    version_label: 'Versi 2',
    superseded: false,
    answers: [{ label: 'Skor', value: '62' }],
}

const opened: ReviewerScores = {
    visible: true,
    reason: null,
    scores: [
        first,
        {
            reviewer: 'Penilai 2',
            reviewer_type: 'external',
            submitted_at: '2026-03-02T08:00:00',
            replaced: true,
            version_label: 'Versi 1',
            superseded: false,
            answers: [{ label: 'Skor', value: '58' }],
        },
    ],
}

describe('ScoreSection', () => {
    it('[REV-VIS-04] [RPT-PRINT-03] menampilkan alasan penahanan, tanpa skor', () => {
        const text = mount(ScoreSection, { props: { scores: held } }).text()

        expect(text).toContain(held.reason)
        expect(text).not.toContain('Hasil Evaluasi Penilai')
    })

    it('[REV-VIS-05] menampilkan skor per reviewer', () => {
        const text = mount(ScoreSection, { props: { scores: opened } }).text()

        expect(text).toContain('Hasil Evaluasi Penilai')
        expect(text).toContain('Penilai 1')
        expect(text).toContain('62')
        expect(text).toContain('Penilai 2')
        expect(text).toContain('58')
    })

    it('menyatakan belum ada evaluasi bila skor terbuka tapi kosong', () => {
        const text = mount(ScoreSection, { props: { scores: { ...opened, scores: [] } } }).text()

        expect(text).toContain('Belum ada evaluasi yang dikirim.')
    })

    it('[REV-REASSIGN-02] lembar reviewer yang digantikan berlabel', () => {
        const wrapper = mount(ScoreSection, { props: { scores: opened } })

        expect(wrapper.text().match(/Digantikan/g)).toHaveLength(1)
    })

    it('[REV-VER-05] tiap lembar menyebut versi yang diisinya', () => {
        const text = mount(ScoreSection, { props: { scores: opened } }).text()

        expect(text).toContain('Versi 2')
        expect(text).toContain('Versi 1')
    })

    it('[REV-VER-06] lembar yang digantikan versi baru tampil sebagai riwayat yang diredam', () => {
        const history = { ...first, version_label: 'Versi 1', superseded: true }
        const wrapper = mount(ScoreSection, {
            props: { scores: { ...opened, scores: [history, first] } },
        })

        const rows = wrapper.findAll('[data-score]')
        expect(rows[0]?.text()).toContain('Riwayat · Versi 1')
        expect(rows[0]?.classes()).toContain('opacity-60')
        expect(rows[1]?.text()).not.toContain('Riwayat')
    })
})
