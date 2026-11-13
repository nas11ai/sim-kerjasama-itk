import type {
    PhaseAccessOption,
    PhaseDetailEditData,
    PhaseEditData,
    StepDeadlineType,
    StepEvaluationFormData,
} from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

// Langkah tahap di editor — isi draf (BR-FE-33). `key` stabil untuk v-for; langkah baru `new-<n>`,
// langkah dari server `step-<i>`. `order` = nomor dari server (W96); langkah baru belum punya.
export type EditableDetail = {
    key: string
    order: number | null
    formAccessControlId: number
    phaseTypeId: number
    needsReview: boolean
    deadlineType: StepDeadlineType
    deadlineCycle: number | null
    deadlineLabel: string
    evaluationForms: StepEvaluationFormData[]
}

// G3-W8 — satu langkah di Pratinjau tahap, baca-saja.
export type PreviewStep = {
    key: string
    title: string
    access: string
    deadlineLabel: string
    needsReview: boolean
    evaluationForms: StepEvaluationFormData[]
}

export type PhaseGeneral = { title: string; description: string; isActive: boolean }

export type PhaseDetailPayload = {
    form_access_control_id: number
    phase_type_id: number
    needs_review: boolean
    deadline_type: StepDeadlineType
    deadline_cycle: number | null
    evaluation_forms: {
        review_evaluation_form_id: number
        is_required: boolean
        is_active: boolean
    }[]
}

// ADR 0019 — satu pilihan "Jenis langkah"; `value` = "jenis:siklus" (siklus kosong = tanpa siklus).
export type StepKindOption = {
    value: string
    label: string
    type: StepDeadlineType
    cycle: number | null
}

// Cermin DeadlineType::cycleName/stepLabel: romawi sampai X (`DeadlineType::MAX_CYCLE`).
export const CYCLE_NUMERALS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X']

const kindValue = (type: StepDeadlineType, cycle: number | null) => `${type}:${cycle ?? ''}`

export const stepKindOptions: StepKindOption[] = [
    { value: kindValue('submit', null), label: 'Pengajuan', type: 'submit', cycle: null },
    ...CYCLE_NUMERALS.map((numeral, i) => ({
        value: kindValue('submit', i + 1),
        label: `Laporan Monev ${numeral}`,
        type: 'submit' as const,
        cycle: i + 1,
    })),
    { value: kindValue('output', null), label: 'Unggah Luaran', type: 'output', cycle: null },
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
    return details.map((detail, index) => ({
        key: `step-${index}`,
        order: detail.order,
        formAccessControlId: detail.formAccessControlId,
        phaseTypeId: detail.phaseTypeId,
        needsReview: detail.needsReview,
        deadlineType: detail.deadlineType,
        deadlineCycle: detail.deadlineCycle,
        deadlineLabel: detail.deadlineLabel,
        evaluationForms: detail.evaluationForms.map((attached) => ({ ...attached })),
    }))
}

export function blankDetail(accessId: number, phaseTypeId: number): EditableDetail {
    newCounter += 1
    return {
        key: `new-${newCounter}`,
        order: null,
        formAccessControlId: accessId,
        phaseTypeId,
        needsReview: false,
        deadlineType: 'submit',
        deadlineCycle: null,
        deadlineLabel: 'Pengajuan',
        evaluationForms: [],
    }
}

// BR-FE-33 — kiriman draf: langkah urut seperti di layar beserta Formulir Evaluasi terpasangnya.
// Urutan langkah diturunkan backend dari Form (W96, NormalisePhaseDetailOrder).
export function toStepsPayload(details: EditableDetail[]) {
    return {
        phase_details: details.map(
            (detail): PhaseDetailPayload => ({
                form_access_control_id: detail.formAccessControlId,
                phase_type_id: detail.phaseTypeId,
                needs_review: detail.needsReview,
                deadline_type: detail.deadlineType,
                deadline_cycle: detail.deadlineCycle,
                evaluation_forms: detail.evaluationForms.map((attached) => ({
                    review_evaluation_form_id: attached.formId,
                    is_required: attached.isRequired,
                    is_active: attached.isActive,
                })),
            })
        ),
    }
}

// Mode buat: bagian langsung tahap beserta draf langkah pertamanya.
export function toPhasePayload(general: PhaseGeneral, details: EditableDetail[]) {
    return {
        title: general.title,
        description: general.description,
        is_active: general.isActive,
        ...toStepsPayload(details),
    }
}

// W93 — judul baris "{Tipe} — {Form}" dan label aksesnya: label tersimpan selama aturan aksesnya
// belum diganti, selain itu dari pilihan akses.
export function stepHeading(
    detail: EditableDetail,
    saved: PhaseDetailEditData[],
    accessOptions: PhaseAccessOption[],
    phaseTypeOptions: FilterOption[]
) {
    const typeName = phaseTypeOptions.find((o) => o.value === String(detail.phaseTypeId))?.label
    const stored = saved.find((s) => s.formAccessControlId === detail.formAccessControlId)
    if (stored)
        return {
            title: [typeName ?? stored.phaseTypeName, stored.formTitle].filter(Boolean).join(' · '),
            access: stored.accessLabel,
        }
    const access = accessOptions.find((o) => o.value === String(detail.formAccessControlId))
    return {
        title: [typeName, access?.formTitle].filter(Boolean).join(' · '),
        access: access?.label ?? '',
    }
}

// BR-FE-33 — banner draf di editor dan kartu pustaka.
export const phaseDraftBannerText = (versionLabel: string | null) =>
    versionLabel === null
        ? 'Draf belum diterbitkan. Tahap ini belum bisa dipakai periode.'
        : `Draf belum diterbitkan. Angkatan baru masih memakai ${versionLabel}.`

// BR-FE-32 — ringkasan konfirmasi Terbitkan: angkatan terpaku tetap di versi lamanya.
export function phasePublishImpactText(impact: PhaseEditData['impact']): string {
    const base = `Terbitkan ${impact.nextLabel}? Berlaku untuk angkatan yang belum punya pengajuan.`
    return impact.pinnedCohorts > 0
        ? `${base} ${impact.pinnedCohorts} angkatan sudah memakai versi lama dan tetap memakainya.`
        : base
}
