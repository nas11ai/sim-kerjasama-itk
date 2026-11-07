import { describe, expect, it } from 'vitest'

import {
    blankDetail,
    deadlineChoices,
    submitDateChoices,
    toEditableDetails,
    toPhasePayload,
    type EditableDetail,
} from '@/lib/phaseDefinition'
import type { PhaseDetailEditData, SubmissionDateOption } from '@/types/form-engine'

const options: SubmissionDateOption[] = [
    { id: 1, type: 'submit', cycle: null, periodId: 10, label: 'P10 — Batas Submit' },
    { id: 2, type: 'evaluation', cycle: null, periodId: 10, label: 'P10 — Batas Evaluasi' },
    { id: 3, type: 'evaluation', cycle: 1, periodId: 10, label: 'P10 — Batas Evaluasi Monev I' },
    { id: 4, type: 'evaluation', cycle: null, periodId: 20, label: 'P20 — Batas Evaluasi' },
    { id: 5, type: 'revision', cycle: null, periodId: 10, label: 'P10 — Batas Revisi' },
    { id: 6, type: 'submit', cycle: 1, periodId: 10, label: 'P10 — Batas Submit Monev I' },
]

const saved = (overrides: Partial<PhaseDetailEditData> = {}): PhaseDetailEditData => ({
    id: 31,
    order: 1,
    formAccessControlId: 7,
    formTitle: 'Form Proposal',
    accessLabel: 'Pengaju · Fakultas Sains',
    phaseTypeId: 3,
    phaseTypeName: 'Isi Proposal',
    needsReview: true,
    submissionDateId: 1,
    evaluationDateId: 2,
    revisionDateId: null,
    currentDeadlines: [],
    submissionCount: 0,
    assignmentCount: 0,
    evaluationFormCount: 0,
    ...overrides,
})

const detail = (overrides: Partial<EditableDetail> = {}): EditableDetail => ({
    ...blankDetail(7, 3),
    ...overrides,
})

const ids = (choices: SubmissionDateOption[]) => choices.map((choice) => choice.id)

describe('lib/phaseDefinition', () => {
    it('detail tersimpan dan detail baru memakai key stabil', () => {
        const [editable] = toEditableDetails([saved()])

        expect(editable?.key).toBe('detail-31')
        expect(editable?.id).toBe(31)
        expect(blankDetail(7, 3).key).toMatch(/^new-\d+$/)
        expect(blankDetail(7, 3).key).not.toBe(blankDetail(7, 3).key)
        expect(blankDetail(7, 3)).toMatchObject({
            id: null,
            formAccessControlId: 7,
            phaseTypeId: 3,
            submissionDateId: null,
            submissionCount: 0,
        })
    })

    it('tenggat tersimpan di luar pilihan tetap ditawarkan', () => {
        const old: SubmissionDateOption = {
            id: 90,
            type: 'submit',
            cycle: null,
            periodId: 5,
            label: 'P5 — Batas Submit — 01/01/2025',
        }
        const [editable] = toEditableDetails([
            saved({ submissionDateId: 90, currentDeadlines: [old, { ...options[0] }] }),
        ])

        expect(ids(deadlineChoices(options, 'submit', editable))).toEqual([1, 6, 90])
    })

    it('[FE-WINDOW-11] Batas Evaluasi hanya menawarkan tenggat satu periode dan siklus dengan Batas Submit', () => {
        expect(
            ids(deadlineChoices(options, 'evaluation', detail({ submissionDateId: 1 })))
        ).toEqual([2])
        expect(
            ids(deadlineChoices(options, 'evaluation', detail({ submissionDateId: 6 })))
        ).toEqual([3])
        expect(ids(deadlineChoices(options, 'evaluation', detail()))).toEqual([2, 3, 4])
    })

    it('[FE-WINDOW-09] Batas Evaluasi tidak menawarkan tanggal berjenis submit', () => {
        const choices = deadlineChoices(options, 'evaluation', detail({ submissionDateId: 1 }))

        expect(choices.every((choice) => choice.type === 'evaluation')).toBe(true)
        expect(ids(deadlineChoices(options, 'revision', detail({ submissionDateId: 1 })))).toEqual([
            5,
        ])
    })

    // W139 — detail Upload Luaran memegang Batas Akhir di kolom Batas Submit-nya.
    it('Batas Submit juga menawarkan Batas Akhir untuk detail Upload Luaran', () => {
        const withOutput: SubmissionDateOption[] = [
            ...options,
            { id: 7, type: 'output', cycle: null, periodId: 10, label: 'P10 — Batas Akhir' },
        ]

        expect(ids(submitDateChoices(withOutput, detail()))).toEqual([1, 6, 7])
    })

    it('payload mempertahankan id detail dan mengosongkan Batas Evaluasi saat tanpa review', () => {
        const [kept] = toEditableDetails([saved({ needsReview: false, evaluationDateId: 2 })])
        const fresh = detail({ needsReview: true, submissionDateId: 1, evaluationDateId: 2 })

        const payload = toPhasePayload({ title: 'Tahap', description: '', isActive: false }, [
            kept,
            fresh,
        ])

        expect(payload).toEqual({
            title: 'Tahap',
            description: '',
            is_active: false,
            phase_details: [
                {
                    id: 31,
                    form_access_control_id: 7,
                    phase_type_id: 3,
                    order: 1,
                    needs_review: false,
                    submission_date_id: 1,
                    evaluation_date_id: null,
                    revision_date_id: null,
                },
                {
                    form_access_control_id: 7,
                    phase_type_id: 3,
                    order: 2,
                    needs_review: true,
                    submission_date_id: 1,
                    evaluation_date_id: 2,
                    revision_date_id: null,
                },
            ],
        })
    })
})
