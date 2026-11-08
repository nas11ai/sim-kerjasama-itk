import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import EvaluationSection from '@/Components/FormEngine/EvaluationSection.vue'
import type { EvaluationSectionData } from '@/types/form-engine'

const section = (reviewsInProgress: number): EvaluationSectionData => ({
    detailId: 7,
    detailLabel: 'Review Proposal — Form Proposal',
    forms: [],
    reviewsInProgress,
})

const mountSection = (reviewsInProgress: number) =>
    mount(EvaluationSection, {
        props: { section: section(reviewsInProgress), fieldTypeOptions: [] },
        global: { stubs: { EvaluationFormCard: true } },
    })

// W158 — form hanya ditugaskan otomatis saat reviewer ditugaskan (AssignReviewers); reviewer yang sudah
// bertugas tetap bisa memulai form baru sendiri dari halaman evaluasinya (StartEvaluationForm).
describe('EvaluationSection', () => {
    it('memberi tahu bahwa form baru tidak otomatis ditugaskan ke reviewer yang sedang bekerja', () => {
        expect(mountSection(2).text()).toContain(
            'Form baru tidak otomatis ditugaskan ke reviewer 2 pengajuan yang sedang direview; reviewernya tetap bisa memulainya dari halaman evaluasi.'
        )
    })

    it('diam bila tidak ada pengajuan yang sedang direview', () => {
        const wrapper = mountSection(0)
        expect(wrapper.text()).not.toContain('sedang direview')
        expect(wrapper.find('[role="status"]').exists()).toBe(false)
    })
})
