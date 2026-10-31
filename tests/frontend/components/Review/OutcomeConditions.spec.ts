import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import OutcomeConditions from '@/Components/Review/OutcomeConditions.vue'
import type { OutcomeConditions as Outcome } from '@/types/review'
import type { SubmissionStatusView } from '@/types/submission-status'

const status: SubmissionStatusView = { key: 'under_review', label: 'Direview', variant: 'secondary' }

const mountPanel = (outcome: Outcome) => mount(OutcomeConditions, { props: { outcome, status } })

describe('OutcomeConditions', () => {
    it('[REV-OUTCOME-02] evaluasi yang belum masuk disebut sebagai penahan', () => {
        const wrapper = mountPanel({ reviewerCount: 2, unsettledCount: 1, openThreadCount: 0 })

        expect(wrapper.text()).toContain('1/2 evaluasi selesai')
        expect(wrapper.text()).toContain(
            'Status bertahan di Direview: 1 evaluasi belum masuk.'
        )
    })

    it('[REV-OUTCOME-03] ulir terbuka disebut sebagai penahan', () => {
        const wrapper = mountPanel({ reviewerCount: 2, unsettledCount: 0, openThreadCount: 1 })

        expect(wrapper.text()).toContain('1 ulir terbuka')
        expect(wrapper.text()).toContain(
            'Status bertahan di Direview: 1 ringkasan masih terbuka.'
        )
    })

    it('[REV-OUTCOME-08] tidak ada jalur tolak di layar reviewer', () => {
        const wrapper = mountPanel({ reviewerCount: 2, unsettledCount: 0, openThreadCount: 0 })

        expect(wrapper.text()).toContain(
            'Penolakan tidak tersedia di layar reviewer. Hanya Operator yang dapat menolak, lewat aksi tertulis.'
        )
        expect(wrapper.find('button').exists()).toBe(false)
    })
})
