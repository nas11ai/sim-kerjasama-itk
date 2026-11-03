import type {
    DeadlineTypeValue,
    PhaseDetailEditData,
    SubmissionDateOption,
} from '@/types/form-engine'

// Detail tahap di editor. `key` stabil untuk v-for; detail baru `new-<n>`, detail lama `detail-<id>`.
export type EditableDetail = {
    key: string
    id: number | null
    formAccessControlId: number
    phaseTypeId: number
    needsReview: boolean
    submissionDateId: number | null
    evaluationDateId: number | null
    revisionDateId: number | null
    currentDeadlines: SubmissionDateOption[]
    submissionCount: number
    assignmentCount: number
    evaluationFormCount: number
}

export type PhaseGeneral = { title: string; description: string; isActive: boolean }

export type PhaseDetailPayload = {
    id?: number
    form_access_control_id: number
    phase_type_id: number
    order: number
    needs_review: boolean
    submission_date_id: number | null
    evaluation_date_id: number | null
    revision_date_id: number | null
}

let newCounter = 0

export function toEditableDetails(details: PhaseDetailEditData[]): EditableDetail[] {
    return details.map((detail) => ({
        key: `detail-${detail.id}`,
        id: detail.id,
        formAccessControlId: detail.formAccessControlId,
        phaseTypeId: detail.phaseTypeId,
        needsReview: detail.needsReview,
        submissionDateId: detail.submissionDateId,
        evaluationDateId: detail.evaluationDateId,
        revisionDateId: detail.revisionDateId,
        currentDeadlines: detail.currentDeadlines,
        submissionCount: detail.submissionCount,
        assignmentCount: detail.assignmentCount,
        evaluationFormCount: detail.evaluationFormCount,
    }))
}

export function blankDetail(accessId: number, phaseTypeId: number): EditableDetail {
    newCounter += 1
    return {
        key: `new-${newCounter}`,
        id: null,
        formAccessControlId: accessId,
        phaseTypeId,
        needsReview: false,
        submissionDateId: null,
        evaluationDateId: null,
        revisionDateId: null,
        currentDeadlines: [],
        submissionCount: 0,
        assignmentCount: 0,
        evaluationFormCount: 0,
    }
}

// W102 — tenggat tersimpan tetap ditawarkan walau di luar `selectableFor`. BR-FE-26 — Batas
// Evaluasi/Revisi hanya dari periode dan siklus Batas Submit detail ini.
export function deadlineChoices(
    options: SubmissionDateOption[],
    type: DeadlineTypeValue,
    detail: EditableDetail
): SubmissionDateOption[] {
    const known = [...options, ...detail.currentDeadlines]
    const unique = known.filter((option, i) => known.findIndex((o) => o.id === option.id) === i)
    const ofType = unique.filter((option) => option.type === type)
    const submit = unique.find((option) => option.id === detail.submissionDateId)
    if (type === 'submit' || submit === undefined) return ofType
    return ofType.filter((o) => o.periodId === submit.periodId && o.cycle === submit.cycle)
}

// W96 — `order` diwajibkan FormPhaseRequest, tetapi NormalisePhaseDetailOrder yang menentukannya.
export function toPhasePayload(general: PhaseGeneral, details: EditableDetail[]) {
    return {
        title: general.title,
        description: general.description,
        is_active: general.isActive,
        phase_details: details.map(
            (detail, index): PhaseDetailPayload => ({
                ...(detail.id === null ? {} : { id: detail.id }),
                form_access_control_id: detail.formAccessControlId,
                phase_type_id: detail.phaseTypeId,
                order: index + 1,
                needs_review: detail.needsReview,
                submission_date_id: detail.submissionDateId,
                evaluation_date_id: detail.needsReview ? detail.evaluationDateId : null,
                revision_date_id: detail.revisionDateId,
            })
        ),
    }
}
