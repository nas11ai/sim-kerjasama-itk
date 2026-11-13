import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import DecisionReadinessCard from '@/Components/Submission/Manage/DecisionReadinessCard.vue'
import type { DecisionReadiness } from '@/types/submission-manage'

const readiness = (overrides: Partial<DecisionReadiness> = {}): DecisionReadiness => ({
    ready: false,
    approvable: false,
    blockers: [],
    openThreadCount: 0,
    round: { kind: 'assessment', number: 2, finished: 1, total: 3 },
    ...overrides,
})

describe('DecisionReadinessCard', () => {
    it('[REV-OUTCOME-12] menampilkan kemajuan putaran dan setiap penahan', () => {
        const text = mount(DecisionReadinessCard, {
            props: {
                readiness: readiness({
                    openThreadCount: 1,
                    blockers: [
                        '2 dari 3 penilai belum menyelesaikan putaran.',
                        '1 catatan penilai masih terbuka.',
                    ],
                }),
            },
        }).text()

        expect(text).toContain('Putaran 2: 1 dari 3 penilai selesai')
        expect(text).toContain('1 catatan terbuka')
        expect(text).toContain('2 dari 3 penilai belum menyelesaikan putaran.')
        expect(text).toContain('1 catatan penilai masih terbuka.')
    })

    it('pengajuan siap tanpa catatan menyatakan siap disetujui atau ditolak', () => {
        const text = mount(DecisionReadinessCard, {
            props: { readiness: readiness({ ready: true, approvable: true }) },
        }).text()

        expect(text).toContain('Siap disetujui atau ditolak.')
    })

    it('[REV-OUTCOME-09] fase tanpa review tanpa putaran', () => {
        const text = mount(DecisionReadinessCard, {
            props: { readiness: readiness({ ready: true, approvable: true, round: null }) },
        }).text()

        expect(text).toContain('Tanpa putaran penilaian.')
    })
})
