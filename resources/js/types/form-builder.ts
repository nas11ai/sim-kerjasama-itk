export interface FormType {
    id: number
    name: string
}

export interface FieldType {
    id: number
    name: string
}

export interface PhaseType {
    id: number
    name: string
}

export interface FormPhase {
    id: number
    title: string
    description?: string
    is_active: boolean
    form_phase_details?: Array<{ id: number }>
}

export interface SubmissionDateLabel {
    id: number
    name: string
}

export interface SubmissionDate {
    id: number
    datetime: string
    submission_date_label?: SubmissionDateLabel
}

export interface SubmissionPeriod {
    id: number
    name: string
    submission_dates?: SubmissionDate[]
}
