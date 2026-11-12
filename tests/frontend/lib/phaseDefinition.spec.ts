import { describe, expect, it } from 'vitest'

import {
    applyStepKind,
    blankDetail,
    phaseDraftBannerText,
    phasePublishImpactText,
    stepKindKey,
    stepKindOptions,
    toEditableDetails,
    toPhasePayload,
    type EditableDetail,
} from '@/lib/phaseDefinition'
import type { PhaseDetailEditData } from '@/types/form-engine'

const saved = (overrides: Partial<PhaseDetailEditData> = {}): PhaseDetailEditData => ({
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
    evaluationForms: [],
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

        expect(editable?.key).toBe('step-0')
        expect(editable?.order).toBe(1)
        expect(blankDetail(7, 3).key).toMatch(/^new-\d+$/)
        expect(blankDetail(7, 3).key).not.toBe(blankDetail(7, 3).key)
        expect(blankDetail(7, 3)).toMatchObject({
            order: null,
            evaluationForms: [],
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
        const [kept] = toEditableDetails([
            saved({
                evaluationForms: [
                    {
                        formId: 9,
                        title: 'Lembar',
                        versionLabel: 'Versi 1',
                        isRequired: false,
                        isActive: true,
                    },
                ],
            }),
        ])
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
                    form_access_control_id: 7,
                    phase_type_id: 3,
                    needs_review: true,
                    deadline_type: 'submit',
                    deadline_cycle: null,
                    evaluation_forms: [
                        { review_evaluation_form_id: 9, is_required: false, is_active: true },
                    ],
                },
                {
                    form_access_control_id: 7,
                    phase_type_id: 3,
                    needs_review: false,
                    deadline_type: 'submit',
                    deadline_cycle: 2,
                    evaluation_forms: [],
                },
            ],
        })
    })

    // BR-FE-33 — banner draf dua varian, persis teks Global Constraints.
    it('banner draf menyebut versi yang masih dipakai, atau bahwa tahap belum bisa dipakai', () => {
        expect(phaseDraftBannerText('Versi 2')).toBe(
            'Draf belum diterbitkan. Angkatan baru masih memakai Versi 2.'
        )
        expect(phaseDraftBannerText(null)).toBe(
            'Draf belum diterbitkan. Tahap ini belum bisa dipakai periode.'
        )
    })

    it('ringkasan terbit hanya menyebut angkatan lama bila ada', () => {
        expect(phasePublishImpactText({ nextLabel: 'Versi 2', pinnedCohorts: 0 })).toBe(
            'Terbitkan Versi 2? Berlaku untuk angkatan yang belum punya pengajuan.'
        )
        expect(phasePublishImpactText({ nextLabel: 'Versi 3', pinnedCohorts: 2 })).toBe(
            'Terbitkan Versi 3? Berlaku untuk angkatan yang belum punya pengajuan. 2 angkatan sudah memakai versi lama dan tetap memakainya.'
        )
    })
})
