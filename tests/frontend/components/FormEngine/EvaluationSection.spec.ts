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

// W158, BR-FE-22 — set form dibekukan saat penugasan.
describe('EvaluationSection', () => {
    it('memberi tahu pengajuan yang tetap memakai form lama', () => {
        expect(mountSection(2).text()).toContain(
            '2 pengajuan yang sedang direview tetap memakai form lama; form ini berlaku untuk penugasan berikutnya.'
        )
    })

    it('diam bila tidak ada pengajuan yang sedang direview', () => {
        const wrapper = mountSection(0)
        expect(wrapper.text()).not.toContain('sedang direview')
        expect(wrapper.find('[role="status"]').exists()).toBe(false)
    })
})
