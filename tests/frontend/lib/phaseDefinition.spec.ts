import { describe, expect, it } from 'vitest'

import {
    applyStepKind,
    blankDetail,
    stepKindKey,
    stepKindOptions,
    toEditableDetails,
    toPhasePayload,
    type EditableDetail,
} from '@/lib/phaseDefinition'
import type { PhaseDetailEditData } from '@/types/form-engine'

const saved = (overrides: Partial<PhaseDetailEditData> = {}): PhaseDetailEditData => ({
    id: 31,
    order: 1,
    formAccessControlId: 7,
    formTitle: 'Form Proposal',
    accessLabel: 'Pengaju · Fakultas Sains',
    phaseTypeId: 3,
    phaseTypeName: 'Isi Proposal',
    needsReview: true,
    deadlineType: 'submit',
    deadlineCycle: null,
    deadlineLabel: 'Pengajuan',
    submissionCount: 0,
    assignmentCount: 0,
    evaluationFormCount: 0,
    ...overrides,
})

const detail = (overrides: Partial<EditableDetail> = {}): EditableDetail => ({
    ...blankDetail(7, 3),
    ...overrides,
})

const optionByLabel = (label: string) => {
    const option = stepKindOptions.find((o) => o.label === label)
    if (option === undefined) throw new Error(`opsi ${label} tidak ada`)
    return option
}

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
            deadlineType: 'submit',
            deadlineCycle: null,
            deadlineLabel: 'Pengajuan',
        })
    })

    it('pilihan jenis langkah: Pengajuan, Laporan Monev I…X, Upload Luaran', () => {
        const labels = stepKindOptions.map((option) => option.label)

        expect(labels[0]).toBe('Pengajuan')
        expect(labels).toContain('Laporan Monev I')
        expect(labels).toContain('Laporan Monev X')
        expect(labels.at(-1)).toBe('Upload Luaran')
        expect(labels).toHaveLength(12)
    })

    it('jenis langkah tersimpan menjadi pilihan yang sama', () => {
        const [editable] = toEditableDetails([
            saved({ deadlineCycle: 2, deadlineLabel: 'Laporan Monev II' }),
        ])

        expect(editable === undefined ? '' : stepKindKey(editable)).toBe(
            optionByLabel('Laporan Monev II').value
        )
    })

    it('[FE-WINDOW-09] Upload Luaran mematikan Perlu Review dan tidak bersiklus', () => {
        const output = applyStepKind(
            detail({ needsReview: true, deadlineCycle: 3 }),
            optionByLabel('Upload Luaran').value
        )

        expect(output).toMatchObject({
            deadlineType: 'output',
            deadlineCycle: null,
            deadlineLabel: 'Upload Luaran',
            needsReview: false,
        })
    })

    it('payload membawa jenis dan siklus dari pilihan "Laporan Monev II"', () => {
        const [kept] = toEditableDetails([saved({ needsReview: false })])
        const monev = applyStepKind(detail(), optionByLabel('Laporan Monev II').value)

        const payload = toPhasePayload({ title: 'Tahap', description: '', isActive: false }, [
            ...(kept === undefined ? [] : [kept]),
            monev,
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
                    deadline_type: 'submit',
                    deadline_cycle: null,
                },
                {
                    form_access_control_id: 7,
                    phase_type_id: 3,
                    order: 2,
                    needs_review: false,
                    deadline_type: 'submit',
                    deadline_cycle: 2,
                },
            ],
        })
    })
})
