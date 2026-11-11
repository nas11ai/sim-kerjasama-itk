import type { PhaseDetailEditData, StepDeadlineType } from '@/types/form-engine'

// Detail tahap di editor. `key` stabil untuk v-for; detail baru `new-<n>`, detail lama `detail-<id>`.
export type EditableDetail = {
    key: string
    id: number | null
    formAccessControlId: number
    phaseTypeId: number
    needsReview: boolean
    deadlineType: StepDeadlineType
    deadlineCycle: number | null
    deadlineLabel: string
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
    deadline_type: StepDeadlineType
    deadline_cycle: number | null
}

// ADR 0019 — satu pilihan "Jenis langkah"; `value` = "jenis:siklus" (siklus kosong = tanpa siklus).
export type StepKindOption = {
    value: string
    label: string
    type: StepDeadlineType
    cycle: number | null
}

// Cermin DeadlineType::cycleName/stepLabel: romawi sampai X (FormPhaseRequest `max:10`).
const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X']

const kindValue = (type: StepDeadlineType, cycle: number | null) => `${type}:${cycle ?? ''}`

export const stepKindOptions: StepKindOption[] = [
    { value: kindValue('submit', null), label: 'Pengajuan', type: 'submit', cycle: null },
    ...ROMAN.map((numeral, i) => ({
        value: kindValue('submit', i + 1),
        label: `Laporan Monev ${numeral}`,
        type: 'submit' as const,
        cycle: i + 1,
    })),
    { value: kindValue('output', null), label: 'Upload Luaran', type: 'output', cycle: null },
]

export const stepKindKey = (detail: EditableDetail) =>
    kindValue(detail.deadlineType, detail.deadlineCycle)

// BR-FE-26 (W139) — langkah Upload Luaran tidak direview, jadi memilihnya mematikan Perlu Review.
export function applyStepKind(detail: EditableDetail, value: string): EditableDetail {
    const option = stepKindOptions.find((o) => o.value === value)
    if (option === undefined) return detail
    return {
        ...detail,
        deadlineType: option.type,
        deadlineCycle: option.cycle,
        deadlineLabel: option.label,
        needsReview: option.type === 'output' ? false : detail.needsReview,
    }
}

let newCounter = 0

export function toEditableDetails(details: PhaseDetailEditData[]): EditableDetail[] {
    return details.map((detail) => ({
        key: `detail-${detail.id}`,
        id: detail.id,
        formAccessControlId: detail.formAccessControlId,
        phaseTypeId: detail.phaseTypeId,
        needsReview: detail.needsReview,
        deadlineType: detail.deadlineType,
        deadlineCycle: detail.deadlineCycle,
        deadlineLabel: detail.deadlineLabel,
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
        deadlineType: 'submit',
        deadlineCycle: null,
        deadlineLabel: 'Pengajuan',
        submissionCount: 0,
        assignmentCount: 0,
        evaluationFormCount: 0,
    }
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
                deadline_type: detail.deadlineType,
                deadline_cycle: detail.deadlineCycle,
            })
        ),
    }
}
